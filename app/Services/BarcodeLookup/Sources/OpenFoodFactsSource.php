<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 2/11 — Open Food Facts (público, sem key).
 * Excelente cobertura para alimentos (rações, sal mineral, suplementos).
 */
class OpenFoodFactsSource extends AbstractHttpSource
{
    public function name(): string { return 'openfoodfacts'; }
    public function label(): string { return 'Open Food Facts'; }
    public function isEnabled(): bool { return (bool) ($this->config['enabled'] ?? true); }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $endpoint = rtrim($this->config['endpoint'] ?? 'https://world.openfoodfacts.org/api/v2/product', '/');
        $resp = $this->http($timeoutSeconds)->get("{$endpoint}/{$barcode}.json");

        if (! $resp->successful()) return null;

        $data = $resp->json();
        if (($data['status'] ?? 0) !== 1 || empty($data['product'])) return null;

        $p = $data['product'];
        $nome = $this->str($p['product_name_pt'] ?? null) ?? $this->str($p['product_name'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->firstCsv($p['brands'] ?? null),
            categoria: $this->firstCsv($p['categories'] ?? null),
            descricao: $this->str($p['generic_name_pt'] ?? $p['generic_name'] ?? null),
            imagem_url: $this->str($p['image_front_small_url'] ?? null),
            quantidade_embalagem: $this->str($p['quantity'] ?? null),
            atributos: [
                'nutriscore' => $p['nutriscore_grade'] ?? null,
                'paises' => $p['countries'] ?? null,
            ],
        );
    }
}
