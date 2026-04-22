<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 10/11 — UPCDatabase.org. Comunitário, requer key gratuita.
 */
class UpcDatabaseSource extends AbstractHttpSource
{
    public function name(): string { return 'upcdatabase'; }
    public function label(): string { return 'UPCDatabase.org'; }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['key']);
    }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $endpoint = rtrim($this->config['endpoint'] ?? 'https://api.upcdatabase.org/product', '/');

        $resp = $this->http($timeoutSeconds)
            ->withToken($this->config['key'])
            ->get("{$endpoint}/{$barcode}");

        if (! $resp->successful()) return null;

        $d = $resp->json();
        $nome = $this->str($d['title'] ?? $d['description'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($d['brand'] ?? null),
            categoria: $this->str($d['category'] ?? null),
            descricao: $this->str($d['description'] ?? null),
            imagem_url: null,
            quantidade_embalagem: $this->str($d['size'] ?? null),
        );
    }
}
