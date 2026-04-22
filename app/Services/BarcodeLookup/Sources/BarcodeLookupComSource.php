<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 11/11 — Barcode Lookup (barcodelookup.com). Última camada paga/ampla.
 */
class BarcodeLookupComSource extends AbstractHttpSource
{
    public function name(): string { return 'barcodelookup'; }
    public function label(): string { return 'Barcode Lookup'; }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['key']);
    }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $resp = $this->http($timeoutSeconds)
            ->get($this->config['endpoint'] ?? 'https://api.barcodelookup.com/v3/products', [
                'barcode' => $barcode,
                'key' => $this->config['key'],
            ]);

        if (! $resp->successful()) return null;

        $products = $resp->json('products') ?? [];
        if (empty($products)) return null;
        $p = $products[0];

        $nome = $this->str($p['product_name'] ?? $p['title'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($p['brand'] ?? $p['manufacturer'] ?? null),
            categoria: $this->str($p['category'] ?? null),
            descricao: $this->str($p['description'] ?? null),
            imagem_url: ! empty($p['images']) ? $this->str($p['images'][0]) : null,
            quantidade_embalagem: $this->str($p['size'] ?? null),
            atributos: [
                'mpn' => $p['mpn'] ?? null,
                'asin' => $p['asin'] ?? null,
            ],
        );
    }
}
