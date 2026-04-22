<?php

namespace App\Services\BarcodeLookup\DTO;

/**
 * Resultado final do serviço após percorrer a cadeia de fallback.
 *  - product: ProductResult ou null (se nenhuma fonte acertou)
 *  - attempts: log de cada fonte (ordem = sequência executada)
 *  - elapsed_total_ms: duração total
 */
final class LookupResult
{
    /**
     * @param  SourceAttempt[]  $attempts
     */
    public function __construct(
        public readonly string $barcode,
        public readonly ?ProductResult $product,
        public readonly array $attempts,
        public readonly float $elapsedTotalMs,
    ) {}

    public function found(): bool
    {
        return $this->product !== null;
    }

    public function sourceUsed(): ?string
    {
        return $this->product?->source;
    }

    public function toArray(): array
    {
        return [
            'barcode' => $this->barcode,
            'found' => $this->found(),
            'source' => $this->sourceUsed(),
            'product' => $this->product?->toArray(),
            'attempts' => array_map(fn ($a) => $a->toArray(), $this->attempts),
            'elapsed_total_ms' => round($this->elapsedTotalMs, 1),
        ];
    }
}
