<?php

namespace App\Models\Livestock;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalEvent extends Model
{
    use BelongsToTenant, BelongsToFarm;

    protected $table = 'animal_events';

    protected $fillable = [
        'animal_id', 'lot_id', 'quantidade_animais',
        'tipo', 'data', 'peso', 'vacina', 'medicamento',
        'dose', 'via_aplicacao', 'responsavel', 'valor', 'partner_id',
        'lot_origem_id', 'lot_destino_id',
        'location_origem_id', 'location_destino_id',
        'observacoes', 'created_by',
        'tenant_id', 'farm_id',
        // Ordenha (manhã/tarde — padrão DROVET)
        'producao_litros', 'litros_manha', 'litros_tarde', 'ordenhas',
        // Eventos agregados em lote (Ave/Peixe)
        'quantidade_ovos', 'peso_medio_amostra', 'quantidade_amostra',
        'kg_racao', 'ph', 'temperatura_agua', 'oxigenio_dissolvido',
        // Reprodução
        'gestacao_dias', 'data_prevista_parto',
    ];

    protected $casts = [
        'data' => 'date',
        'peso' => 'decimal:2',
        'dose' => 'decimal:3',
        'valor' => 'decimal:2',
        'quantidade_animais' => 'integer',
        'quantidade_ovos' => 'integer',
        'quantidade_amostra' => 'integer',
        'peso_medio_amostra' => 'decimal:3',
        'kg_racao' => 'decimal:2',
        'ph' => 'decimal:2',
        'temperatura_agua' => 'decimal:1',
        'oxigenio_dissolvido' => 'decimal:2',
        'litros_manha' => 'decimal:1',
        'litros_tarde' => 'decimal:1',
        'producao_litros' => 'decimal:1',
        'ordenhas' => 'array',
        'data_prevista_parto' => 'date',
    ];

    /**
     * Helper: este evento é agregado (afeta um lote inteiro/parcial sem
     * registro individual)?
     */
    public function isAgregado(): bool
    {
        return $this->animal_id === null && $this->lot_id !== null;
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AnimalLot::class, 'lot_id');
    }

    public function lotOrigem(): BelongsTo
    {
        return $this->belongsTo(AnimalLot::class, 'lot_origem_id');
    }

    public function lotDestino(): BelongsTo
    {
        return $this->belongsTo(AnimalLot::class, 'lot_destino_id');
    }

    public function locationOrigem(): BelongsTo
    {
        return $this->belongsTo(AnimalLocation::class, 'location_origem_id');
    }

    public function locationDestino(): BelongsTo
    {
        return $this->belongsTo(AnimalLocation::class, 'location_destino_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
