<?php

namespace App\Services\BarcodeLookup;

use App\Services\BarcodeLookup\Contracts\BarcodeSource;
use App\Services\BarcodeLookup\DTO\LookupResult;
use App\Services\BarcodeLookup\DTO\ProductResult;
use App\Services\BarcodeLookup\DTO\SourceAttempt;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Orquestrador de lookup de produto por código de barras.
 *
 * Responsabilidades:
 *   - Resolver a cadeia de fontes a partir de config/barcode_lookup.php
 *   - Executar cada fonte em sequência (ordem do array do config)
 *   - Short-circuit na primeira que retornar ProductResult com nome válido
 *   - Aplicar timeout por fonte (config.timeout_seconds)
 *   - Capturar erros (rede, parsing) sem quebrar a cadeia
 *   - Registrar cada tentativa em SourceAttempt (observabilidade)
 *   - Cache negativo opcional (não re-consultar código recém-consultado sem sucesso)
 *
 * Extensibilidade: nova fonte = implementar BarcodeSource e adicionar em config.
 * O serviço não precisa saber quantas fontes existem.
 */
class ProductLookupService
{
    /** @var BarcodeSource[] */
    protected array $sources;

    public function __construct(
        protected array $config,
        protected LoggerInterface $logger,
        protected ?Cache $cache = null,
    ) {
        $this->sources = $this->bootSources($config['sources'] ?? []);
    }

    /** Método principal solicitado pelo contrato. */
    public function getProductByBarcode(string $barcode): LookupResult
    {
        $barcode = $this->normalize($barcode);
        $started = microtime(true);

        if ($barcode === '') {
            return new LookupResult($barcode, null, [], 0.0);
        }

        // Cache negativo: evita repetir toda a cadeia em janelas curtas para o mesmo código
        $cacheKey = 'barcode_lookup:miss:'.$barcode;
        if ($this->cache && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            $this->logger->info('[barcode] cache-miss-hit', ['barcode' => $barcode]);
            return new LookupResult($barcode, null, $cached['attempts'] ?? [], 0.0);
        }

        $timeout = (int) ($this->config['timeout_seconds'] ?? 2);
        $attempts = [];
        $product = null;

        foreach ($this->sources as $source) {
            $attempt = $this->runSource($source, $barcode, $timeout);
            $attempts[] = $attempt;

            if ($attempt->found) {
                $product = $this->lastProduct;
                $this->logger->info('[barcode] hit', [
                    'barcode' => $barcode,
                    'source' => $source->name(),
                    'elapsed_ms' => $attempt->elapsedMs,
                ]);
                break; // short-circuit
            }
        }

        $elapsedTotal = (microtime(true) - $started) * 1000;

        // Log consolidado
        $this->logger->info('[barcode] lookup concluído', [
            'barcode' => $barcode,
            'found' => $product !== null,
            'source_used' => $product?->source,
            'attempts' => array_map(fn ($a) => $a->toArray(), $attempts),
            'elapsed_total_ms' => round($elapsedTotal, 1),
        ]);

        $result = new LookupResult($barcode, $product, $attempts, $elapsedTotal);

        // Cache negativo (não encontrou)
        if (! $product && $this->cache && ($ttl = (int) ($this->config['negative_cache_ttl'] ?? 0)) > 0) {
            $this->cache->put($cacheKey, ['attempts' => $attempts], $ttl);
        }

        return $result;
    }

    /** Instancia SourceAttempt a partir da execução de uma fonte. */
    private ?ProductResult $lastProduct = null;

    private function runSource(BarcodeSource $source, string $barcode, int $timeout): SourceAttempt
    {
        $this->lastProduct = null;

        if (! $source->isEnabled()) {
            return new SourceAttempt(
                name: $source->name(),
                label: $source->label(),
                found: false,
                status: null,
                note: 'desabilitada (sem credenciais ou config)',
                elapsedMs: 0.0,
            );
        }

        $start = microtime(true);
        try {
            $result = $source->lookup($barcode, $timeout);
            $elapsed = (microtime(true) - $start) * 1000;

            if ($result && $result->isValid()) {
                $this->lastProduct = $result;
                return new SourceAttempt(
                    name: $source->name(),
                    label: $source->label(),
                    found: true,
                    status: 200,
                    note: 'OK',
                    elapsedMs: $elapsed,
                );
            }

            return new SourceAttempt(
                name: $source->name(),
                label: $source->label(),
                found: false,
                status: null,
                note: 'não encontrado',
                elapsedMs: $elapsed,
            );
        } catch (Throwable $e) {
            $elapsed = (microtime(true) - $start) * 1000;
            $this->logger->warning('[barcode] falha em fonte', [
                'source' => $source->name(),
                'barcode' => $barcode,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return new SourceAttempt(
                name: $source->name(),
                label: $source->label(),
                found: false,
                status: null,
                note: 'erro: '.substr($e->getMessage(), 0, 200),
                elapsedMs: $elapsed,
            );
        }
    }

    /**
     * Instancia as fontes na ordem declarada em config.
     * Ignora entradas inválidas silenciosamente (não derruba o pipeline).
     */
    protected function bootSources(array $definitions): array
    {
        $sources = [];
        foreach ($definitions as $name => $def) {
            $class = Arr::get($def, 'class');
            if (! $class || ! class_exists($class)) continue;

            try {
                $instance = new $class($def);
                if ($instance instanceof BarcodeSource) {
                    $sources[] = $instance;
                }
            } catch (Throwable $e) {
                $this->logger->warning('[barcode] fonte não instanciada', [
                    'source' => $name,
                    'class' => $class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
        return $sources;
    }

    /** EAN/UPC: só dígitos e letras (ignora espaços, traços, pontos). */
    protected function normalize(string $barcode): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', $barcode) ?? '';
    }

    /** Exposto para testes/UI: lista estado das fontes. */
    public function describeSources(): array
    {
        return array_map(fn (BarcodeSource $s) => [
            'name' => $s->name(),
            'label' => $s->label(),
            'enabled' => $s->isEnabled(),
        ], $this->sources);
    }
}
