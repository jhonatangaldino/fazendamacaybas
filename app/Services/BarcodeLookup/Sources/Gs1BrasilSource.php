<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 8/11 — GS1 Brasil (API oficial de produtos com GTIN registrado no Brasil).
 * Requer token corporativo GS1. Endpoint e credenciais via env.
 */
class Gs1BrasilSource extends AbstractHttpSource
{
    public function name(): string { return 'gs1brasil'; }
    public function label(): string { return 'GS1 Brasil'; }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['token']);
    }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $endpoint = rtrim($this->config['endpoint'] ?? 'https://api.gs1br.org/v2/produtos', '/');

        $resp = $this->http($timeoutSeconds)
            ->withToken($this->config['token'])
            ->get("{$endpoint}/{$barcode}");

        if (! $resp->successful()) return null;

        $d = $resp->json();
        $nome = $this->str($d['descricao'] ?? $d['nome'] ?? $d['productDescription'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($d['marca'] ?? $d['brand'] ?? null),
            categoria: $this->str($d['segmento'] ?? $d['category'] ?? null),
            descricao: $this->str($d['descricaoLonga'] ?? $d['longDescription'] ?? null),
            imagem_url: $this->str($d['imagem'] ?? $d['imageUrl'] ?? null),
            quantidade_embalagem: $this->str($d['conteudoLiquido'] ?? $d['netContent'] ?? null),
            ncm: $this->str($d['ncm'] ?? null),
            origem: $this->str($d['paisOrigem'] ?? $d['countryOfOrigin'] ?? null),
            atributos: [
                'cnpj_fabricante' => $d['cnpjFabricante'] ?? null,
                'gtin' => $d['gtin'] ?? null,
            ],
        );
    }
}
