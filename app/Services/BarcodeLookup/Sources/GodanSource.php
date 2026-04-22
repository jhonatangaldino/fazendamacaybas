<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 3/11 — GODAN (Global Open Data for Agriculture & Nutrition).
 *
 * IMPORTANTE: GODAN não é uma API única — é uma rede de datasets abertos (godan.info).
 * Esta fonte atua como proxy configurável: o operador aponta BARCODE_GODAN_ENDPOINT para
 * um dataset conectado que responda por código de barras (ex: catálogo municipal,
 * cooperativa rural, sindicato, órgão estadual). Enquanto não configurado, retorna null.
 *
 * Contrato esperado do endpoint configurado: GET {endpoint}/{barcode} → JSON com
 * {nome, marca, categoria, descricao, imagem, ...} ou 404.
 */
class GodanSource extends AbstractHttpSource
{
    public function name(): string { return 'godan'; }
    public function label(): string { return 'GODAN (datasets agrícolas abertos)'; }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['endpoint']);
    }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $endpoint = rtrim((string) $this->config['endpoint'], '/');
        $req = $this->http($timeoutSeconds);
        if (! empty($this->config['token'])) {
            $req = $req->withToken($this->config['token']);
        }

        $resp = $req->get("{$endpoint}/{$barcode}");
        if (! $resp->successful()) return null;

        $d = $resp->json();
        if (empty($d)) return null;

        $nome = $this->str($d['nome'] ?? $d['name'] ?? $d['descricao'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($d['marca'] ?? $d['brand'] ?? null),
            categoria: $this->str($d['categoria'] ?? $d['category'] ?? null),
            descricao: $this->str($d['descricao_longa'] ?? $d['description'] ?? null),
            imagem_url: $this->str($d['imagem'] ?? $d['image_url'] ?? null),
            ncm: $this->str($d['ncm'] ?? null),
            atributos: is_array($d['atributos'] ?? null) ? $d['atributos'] : [],
        );
    }
}
