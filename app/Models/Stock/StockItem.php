<?php

namespace App\Models\Stock;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsAtividade;

class StockItem extends Model
{
    use SoftDeletes, LogsAtividade;
    use BelongsToTenant, BelongsToFarm;

    protected static function booted(): void
    {
        $invalidate = fn (StockItem $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'estoque');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'category_id', 'codigo', 'codigo_barras', 'nome', 'descricao', 'unidade', 'marca',
        'estoque_minimo', 'estoque_maximo', 'custo_medio', 'tipo', 'registro_ms', 'is_active',
        'tenant_id', 'farm_id',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
