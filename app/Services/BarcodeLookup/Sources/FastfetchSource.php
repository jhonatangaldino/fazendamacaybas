<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 4/11 — Fastfetch (fonte configurável).
 *
 * Wrapper genérico pra qualquer serviço interno/privado (ex: lambda AWS da cooperativa
 * com cache próprio + agregação de fontes). Desligado se BARCODE_FASTFETCH_URL vazio.
 *
 * Contrato: GET {url}?ean={barcode} → JSON { nome, marca, categoria, ... } ou 404.
 */
class FastfetchSource extends AbstractHttpSource
{
    public function name(): string { return 'fastfetch'; }
    public function label(): string { return 'Fastfetch'; }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['endpoint']);
    }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $req = $this->http($timeoutSeconds);
        if (! empty($this->config['token'])) {
            $req = $req->withToken($this->config['token']);
        }

        $resp = $req->get($this->config['endpoint'], ['ean' => $barcode]);
        if (! $resp->successful()) return null;

        $d = $resp->json();
        $nome = $this->str($d['nome'] ?? $d['name'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($d['marca'] ?? $d['brand'] ?? null),
            categoria: $this->str($d['categoria'] ?? $d['category'] ?? null),
            descricao: $this->str($d['descricao'] ?? $d['description'] ?? null),
            imagem_url: $this->str($d['imagem'] ?? $d['image'] ?? null),
            quantidade_embalagem: $this->str($d['quantidade'] ?? $d['size'] ?? null),
            ncm: $this->str($d['ncm'] ?? null),
            atributos: is_array($d['atributos'] ?? null) ? $d['atributos'] : [],
        );
    }
}
