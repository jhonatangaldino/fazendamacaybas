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

    /**
     * Invalida cache de tenantSpecies quando animal é criado/atualizado/excluído.
     * Sem isso a sidebar mostra contagem antiga por até 10 minutos depois de
     * cadastrar/excluir/mudar status de animal.
     */
    protected static function booted(): void
    {
        $invalidate = function (Animal $a) {
            \Illuminate\Support\Facades\Cache::forget("tenant_species_with_count.{$a->tenant_id}");
            // Invalidação centralizada das métricas (livestock e qualquer derivada).
            \App\Services\Metrics\MetricsCache::forgetForTenant((int) $a->tenant_id, 'livestock');
        };
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);

        // F8-A3 (QA Deep 2026-04-29) · cascade soft-delete dos eventos.
        // Animal usa SoftDeletes; animal_events tem cascadeOnDelete na FK.
        // Mas cascadeOnDelete dispara só em hard-delete, NÃO em soft-delete.
        // Resultado: animal_events ficavam "órfãos lógicos" (referenciando
        // animal soft-deleted). Aqui marcamos os eventos do animal como
        // deletados também — mantém integridade referencial lógica.
        // animal_events não usa SoftDeletes; usamos hard-delete dos eventos
        // (são informação derivada, não primária).
        static::deleting(function (Animal $a) {
            // Só dispara em soft-delete (forceDelete não passa por aqui)
            if (! $a->isForceDeleting()) {
                AnimalEvent::where('animal_id', $a->id)->delete();
            }
        });

        // F8-A3 · restore também reativa eventos? Não — eventos são deletados
        // no soft-delete. Se quiser restore com histórico preservado, mudar
        // para soft-delete em animal_events também (refactor maior).
    }

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
