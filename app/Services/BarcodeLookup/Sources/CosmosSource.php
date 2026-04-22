<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 7/11 — Cosmos (BlueSoft). Integra com GS1 Brasil, cobertura forte em produtos BR.
 * Requer token gratuito (cadastro em cosmos.bluesoft.com.br).
 */
class CosmosSource extends AbstractHttpSource
{
    public function name(): string { return 'cosmos'; }
    public function label(): string { return 'Cosmos (BlueSoft)'; }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['token']);
    }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $endpoint = rtrim($this->config['endpoint'] ?? 'https://api.cosmos.bluesoft.com.br/gtins', '/');

        $resp = $this->http($timeoutSeconds)
            ->withHeaders([
                'X-Cosmos-Token' => $this->config['token'],
                'User-Agent' => 'Cosmos-API-Request',
            ])
            ->get("{$endpoint}/{$barcode}.json");

        if (! $resp->successful()) return null;

        $d = $resp->json();
        $nome = $this->str($d['description'] ?? $d['title'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($d['brand']['name'] ?? null),
            categoria: $this->str($d['ncm']['description'] ?? null),
            descricao: $this->str($d['description'] ?? null),
            imagem_url: $this->str($d['thumbnail'] ?? null),
            quantidade_embalagem: $this->str($d['gross_weight'] ?? null),
            ncm: $this->str($d['ncm']['code'] ?? null),
            origem: $this->str($d['brand']['country'] ?? null),
            atributos: [
                'avg_price' => $d['avg_price'] ?? null,
                'gtin' => $d['gtin'] ?? null,
            ],
        );
    }
}
