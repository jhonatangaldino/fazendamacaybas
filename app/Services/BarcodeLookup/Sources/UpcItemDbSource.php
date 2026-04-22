<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 5/11 — UPCItemDB. Com ou sem key:
 *   - Sem key: endpoint /trial (100 consultas/dia por IP)
 *   - Com key: endpoint pago /v1 (maior limite)
 *
 * Respeita 429 (limite diário) silenciosamente — volta null pra seguir na cadeia.
 */
class UpcItemDbSource extends AbstractHttpSource
{
    public function name(): string { return 'upcitemdb'; }
    public function label(): string { return 'UPCItemDB'; }
    public function isEnabled(): bool { return (bool) ($this->config['enabled'] ?? true); }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $hasKey = ! empty($this->config['key']);
        $url = $hasKey
            ? ($this->config['endpoint_paid'] ?? 'https://api.upcitemdb.com/prod/v1/lookup')
            : ($this->config['endpoint_trial'] ?? 'https://api.upcitemdb.com/prod/trial/lookup');

        $req = $this->http($timeoutSeconds);
        if ($hasKey) $req = $req->withHeaders(['user_key' => $this->config['key']]);

        $resp = $req->get($url, ['upc' => $barcode]);

        // Limite atingido — retorna null silenciosamente
        if ($resp->status() === 429) return null;
        if (! $resp->successful()) return null;

        $items = $resp->json('items') ?? [];
        if (empty($items)) return null;
        $i = $items[0];
        $nome = $this->str($i['title'] ?? null);
        if (! $nome) return null;

        return new ProductResult(
            nome: $nome,
            source: $this->label(),
            marca: $this->str($i['brand'] ?? null),
            categoria: $this->str($i['category'] ?? null),
            descricao: $this->str($i['description'] ?? null),
            imagem_url: ! empty($i['images']) ? $this->str($i['images'][0]) : null,
            quantidade_embalagem: $this->str($i['size'] ?? null),
            atributos: [
                'model' => $i['model'] ?? null,
                'weight' => $i['weight'] ?? null,
            ],
        );
    }
}
