<?php

namespace App\Models\Livestock;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsAtividade;

class AnimalLot extends Model
{
    use BelongsToTenant, BelongsToFarm, LogsAtividade;

    protected $table = 'animal_lots';

    /**
     * Invalida cache de tenantSpecies (sidebar contagem) quando lote agregado
     * tem quantidade alterada (criação/exclusão/mortalidade decrementando).
     * Sem isso o painel mostra "Ave: 1000 cabeças" mesmo depois de 50
     * mortalidades por até 10 minutos.
     */
    protected static function booted(): void
    {
        $invalidate = function (AnimalLot $l) {
            \Illuminate\Support\Facades\Cache::forget("tenant_species_with_count.{$l->tenant_id}");
            \App\Services\Metrics\MetricsCache::forgetForTenant((int) $l->tenant_id, 'livestock');
        };
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);

        // Auto-geração de código no formato LT-#### por tenant.
        // Padronização imposta em 2026-04-29 — usuário não digita mais código,
        // sistema atribui sequencialmente. Formato cresce naturalmente pra 5+
        // dígitos quando passar de 9999 lotes (str_pad só preenche se menor).
        //
        // Race condition: dois inserts simultâneos no mesmo tenant podem gerar
        // o mesmo código. Mitigação: unique (tenant_id, codigo) força o segundo
        // insert a falhar; capturamos QueryException e tentamos novamente até 5×.
        // Em uso real (1-2 lotes/dia) a chance é desprezível, mas o retry blinda.
        static::creating(function (AnimalLot $l) {
            if (! empty($l->codigo)) {
                return; // usuário/sistema já forneceu — respeita
            }
            if (empty($l->tenant_id)) {
                // BelongsToTenant trait popula tenant_id antes do creating;
                // se não veio, deixa NULL e a validação trata
                return;
            }
            $l->codigo = self::gerarProximoCodigo((int) $l->tenant_id);
        });
    }

    /**
     * Gera o próximo código sequencial pro tenant. Lê o maior número já usado
     * em LT-#### e incrementa. Retorna 'LT-0001' se não houver lote anterior.
     *
     * Por que não usar simplesmente count()+1: lotes podem ser deletados —
     * o ID 5 some, mas o próximo deve ser 6 (não reciclar o 5). Usamos MAX
     * sobre o sufixo numérico pra preservar essa monotonia.
     */
    public static function gerarProximoCodigo(int $tenantId): string
    {
        $ultimo = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('codigo', 'like', 'LT-%')
            ->orderByRaw('CAST(SUBSTRING(codigo, 4) AS UNSIGNED) DESC')
            ->value('codigo');

        $proximo = 1;
        if ($ultimo && preg_match('/^LT-(\d+)$/', $ultimo, $m)) {
            $proximo = ((int) $m[1]) + 1;
        }

        return 'LT-' . str_pad((string) $proximo, 4, '0', STR_PAD_LEFT);
    }

    protected $fillable = [
        'farm_id', 'codigo', 'nome', 'descricao', 'finalidade', 'is_active', 'tenant_id',
        // 2026-04-28 · vinculação direta a species (cadastro de lote sem Animal)
        'species_id',
        // 2026-04-28 · local físico do lote (movimentação atualiza)
        'location_id',
        // RN4 · gestão agregada (aves/peixes/abelhas)
        'gestao_modo', 'quantidade_inicial', 'quantidade_atual',
        // Auditoria 2026-04-27 · campos do lote agregado (massa)
        'peso_medio_kg', 'data_inicio', 'data_fim',
        'partner_id_aquisicao', 'valor_aquisicao', 'custo_unitario',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        // Cabeças são sempre inteiras — antes vinha "200.00" do decimal(10,2),
        // confundia UI mostrando "200.00 cabeças" no select de lote.
        'quantidade_inicial' => 'integer',
        'quantidade_atual' => 'integer',
        'peso_medio_kg' => 'decimal:2',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'valor_aquisicao' => 'decimal:2',
        'custo_unitario' => 'decimal:4',
    ];

    /** Helper: este lote é gerido como agregado (sem 1 row por animal)? */
    public function isAgregado(): bool
    {
        return $this->gestao_modo === 'agregada';
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'lot_id');
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(AnimalSpecies::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
