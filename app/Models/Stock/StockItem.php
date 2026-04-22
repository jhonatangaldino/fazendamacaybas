<?php

namespace App\Models\Stock;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'codigo', 'nome', 'descricao', 'unidade', 'marca',
        'estoque_minimo', 'estoque_maximo', 'custo_medio', 'tipo', 'registro_ms', 'is_active',
    ];

    protected $casts = [
        'estoque_minimo' => 'decimal:4',
        'estoque_maximo' => 'decimal:4',
        'custo_medio' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    public function saldoAtual(?int $warehouseId = null): float
    {
        $query = $this->movements();
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $entradas = (float) (clone $query)->whereIn('tipo', ['entrada', 'ajuste'])->sum('quantidade');
        $saidas = (float) (clone $query)->where('tipo', 'saida')->sum('quantidade');

        return $entradas - $saidas;
    }
}
