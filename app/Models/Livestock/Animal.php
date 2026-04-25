<?php

namespace App\Models\Livestock;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Farm;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Animal extends Model
{
    use LogsActivity, SoftDeletes;
    use BelongsToTenant, BelongsToFarm;

    protected $fillable = [
        'farm_id', 'species_id', 'breed_id', 'lot_id', 'location_id',
        'mae_id', 'pai_id',
        'identificacao', 'nome', 'numero_registro', 'sexo', 'data_nascimento',
        'peso_nascimento', 'peso_atual', 'origem', 'partner_id',
        'data_aquisicao', 'valor_aquisicao', 'status', 'data_saida',
        'categoria', 'observacoes', 'photo_path',
        'tenant_id',
    ];

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    protected $casts = [
        'data_nascimento' => 'date',
        'data_aquisicao' => 'date',
        'data_saida' => 'date',
        'peso_nascimento' => 'decimal:2',
        'peso_atual' => 'decimal:2',
        'valor_aquisicao' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['identificacao', 'nome', 'status', 'lot_id', 'location_id', 'peso_atual'])
            ->logOnlyDirty();
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(AnimalSpecies::class, 'species_id');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(AnimalBreed::class, 'breed_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AnimalLot::class, 'lot_id');
    }

    /**
     * Localização física atual do animal (pasto, piquete, curral, etc).
     * Distinto do lote (grupo lógico). Um mesmo lote pode mudar de local
     * (rotação de pasto) sem mudar de identidade.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(AnimalLocation::class, 'location_id');
    }

    public function mae(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'mae_id');
    }

    public function pai(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'pai_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnimalEvent::class, 'animal_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeAtivos($q)
    {
        return $q->where('status', 'ativo');
    }
}
