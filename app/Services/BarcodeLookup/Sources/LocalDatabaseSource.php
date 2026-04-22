<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Models\Stock\StockItem;
use App\Services\BarcodeLookup\Contracts\BarcodeSource;
use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 1/11 — Base própria. Checa se o produto já foi cadastrado na fazenda.
 * Sempre primeira fonte: evita hit externo quando o item já é conhecido.
 */
class LocalDatabaseSource implements BarcodeSource
{
    public function __construct(protected array $config = []) {}

    public function name(): string { return 'local'; }
    public function label(): string { return 'Cadastro local'; }
    public function isEnabled(): bool { return (bool) ($this->config['enabled'] ?? true); }

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $item = StockItem::with('category:id,nome')
            ->where('codigo_barras', $barcode)
            ->orWhere('codigo', $barcode)
            ->first();

        if (! $item) return null;

        return new ProductResult(
            nome: $item->nome,
            source: $this->label(),
            marca: $item->marca,
            categoria: $item->category?->nome,
            descricao: $item->descricao,
            atributos: [
                'stock_item_id' => $item->id,
                'codigo_interno' => $item->codigo,
                'unidade' => $item->unidade,
                'tipo' => $item->tipo,
                'saldo_atual' => $item->saldoAtual(),
            ],
        );
    }
}
