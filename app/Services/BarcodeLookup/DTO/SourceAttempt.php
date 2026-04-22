<?php

namespace App\Services\BarcodeLookup\DTO;

/**
 * Registro de tentativa de uma única fonte — observabilidade interna.
 * NÃO é exposto ao usuário final; fica disponível em logs e no banco
 * (barcode_lookups.attempts_json) para análise de saúde do pipeline.
 */
final class SourceAttempt
{
    public function __construct(
        public readonly string $name,         // chave técnica (ex: openfoodfacts)
        public readonly string $label,        // nome humano (ex: "Open Food Facts")
        public readonly bool $found,
        public readonly ?int $status,         // HTTP status ou null
        public readonly string $note,         // "OK" | "não encontrado" | "timeout" | "desabilitada" | "erro: ..."
        public readonly float $elapsedMs,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'found' => $this->found,
            'status' => $this->status,
            'note' => $this->note,
            'elapsed_ms' => round($this->elapsedMs, 1),
        ];
    }
}
