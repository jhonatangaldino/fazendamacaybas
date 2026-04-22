<?php

namespace App\Services\BarcodeLookup\DTO;

/**
 * Resposta unificada de qualquer fonte de lookup.
 * Único campo obrigatório: nome. Demais são opcionais (enriquecimento progressivo).
 */
final class ProductResult
{
    public function __construct(
        public readonly string $nome,
        public readonly string $source,
        public readonly ?string $marca = null,
        public readonly ?string $categoria = null,
        public readonly ?string $descricao = null,
        public readonly ?string $imagem_url = null,
        public readonly ?string $quantidade_embalagem = null,
        public readonly ?string $ncm = null,
        public readonly ?string $origem = null,
        public readonly array $atributos = [],
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'nome' => $this->nome,
            'marca' => $this->marca,
            'categoria' => $this->categoria,
            'descricao' => $this->descricao,
            'imagem_url' => $this->imagem_url,
            'quantidade_embalagem' => $this->quantidade_embalagem,
            'ncm' => $this->ncm,
            'origem' => $this->origem,
            'atributos' => $this->atributos,
        ];
    }

    /** Resultado parcial: pelo menos um nome válido. */
    public function isValid(): bool
    {
        return trim($this->nome) !== '';
    }
}
