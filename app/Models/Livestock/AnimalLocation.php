<?php

namespace App\Models\Livestock;

use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsAtividade;

/**
 * Localização física onde um animal se encontra.
 *
 * É o **PASTO, piquete, curral, baia, tanque ou galpão** — lugar físico,
 * não grupo lógico. Distinto de AnimalLot (que representa agrupamento).
 *
 * Exemplos reais:
 *   - "Pasto das palmeiras" (tipo=pasto, 20 ha)
 *   - "Piquete Norte" (tipo=piquete, 3 ha)
 *   - "Curral de manejo" (tipo=curral)
 *   - "Tanque A" (tipo=tanque, piscicultura)
 *
 * Um mesmo lote pode passar por várias localizações (rotação);
 * uma mesma localização pode receber animais de vários lotes.
 */
class AnimalLocation extends Model
{
    use BelongsToTenant, BelongsToFarm, LogsAtividade;

    protected $table = 'animal_locations';

    protected $fillable = [
        'tenant_id', 'farm_id',
        'codigo', 'nome', 'tipo',
        'area_ha', 'capacidade_ua',
        'observacoes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'area_ha' => 'decimal:2',
        'capacidade_ua' => 'decimal:2',
    ];

    /** Tipos válidos (espelho do enum da UI). */
    public const TIPOS = [
        'pasto' => 'Pasto',
        'piquete' => 'Piquete',
        'curral' => 'Curral',
        'baia' => 'Baia',
        'tanque' => 'Tanque',
        'galpao' => 'Galpão',
        'outro' => 'Outro',
    ];

    /**
     * Auto-geração de código no formato MP-##### por tenant.
     * Padronização imposta em 2026-04-29 — usuário não digita mais código,
     * sistema atribui sequencialmente. Espelha a lógica de AnimalLot::booted
     * mas com prefixo MP- (Manejo de Pasto/Piquete/Curral/etc.) e 5 dígitos
     * (fazenda grande tem mais piquetes que lotes — antecipamos).
     *
     * Race condition: dois inserts simultâneos no mesmo tenant podem gerar
     * o mesmo código. Mitigação: unique (tenant_id, codigo) força o segundo
     * a falhar — em uso real (1-2 cadastros/dia) chance é desprezível.
     */
    protected static function booted(): void
    {
        static::creating(function (AnimalLocation $l) {
            if (! empty($l->codigo)) {
                return; // usuário/sistema forneceu código explícito — respeita
            }
            if (empty($l->tenant_id)) {
                // BelongsToTenant trait popula antes do creating; se não veio,
                // deixa NULL e a validação trata
                return;
            }
            $l->codigo = self::gerarProximoCodigo((int) $l->tenant_id);
        });
    }

    /**
     * Gera o próximo código sequencial pro tenant. Lê o maior número já usado
     * em MP-##### e incrementa. Retorna 'MP-00001' se não houver location.
     *
     * Por que MAX e não count()+1: locations podem ser excluídas — o id 5
     * some, mas o próximo deve ser 6 (não reciclar). Usamos MAX sobre o sufixo
     * numérico pra preservar essa monotonia.
     */
    public static function gerarProximoCodigo(int $tenantId): string
    {
        $ultimo = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('codigo', 'like', 'MP-%')
            ->orderByRaw('CAST(SUBSTRING(codigo, 4) AS UNSIGNED) DESC')
            ->value('codigo');

        $proximo = 1;
        if ($ultimo && preg_match('/^MP-(\d+)$/', $ultimo, $m)) {
            $proximo = ((int) $m[1]) + 1;
        }

        return 'MP-' . str_pad((string) $proximo, 5, '0', STR_PAD_LEFT);
    }

    public function scopeAtivos($q)
    {
        return $q->where('is_active', true);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'location_id');
    }
}
