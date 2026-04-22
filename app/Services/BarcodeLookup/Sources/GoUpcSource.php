<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 9/11 — Go-UPC (go-upc.com). Cobertura ampla internacional, requer API key.
 */
class GoUpcSource extends AbstractHttpSource
{
    public function name(): string { return 'goupc'; }
    public function label(): string { return 'Go-UPC'; }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['key']);
    }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $endpoint = rtrim($this->config['endpoint'] ?? 'https://go-upc.com/api/v1/code', '/');

        $resp = $this->http($timeoutSeconds)
            ->withToken($this->config['key'])
            ->get("{$endpoint}/{$barcode}");

        if (! $resp->successful()) return null;

        $p = $resp->json('product');
        $nome = $this->str($p['name'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($p['brand'] ?? null),
            categoria: $this->str($p['category'] ?? null),
            descricao: $this->str($p['description'] ?? null),
            imagem_url: $this->str($p['imageUrl'] ?? null),
            atributos: [
                'region' => $p['region'] ?? null,
                'specs' => $p['specs'] ?? null,
            ],
        );
    }
}
