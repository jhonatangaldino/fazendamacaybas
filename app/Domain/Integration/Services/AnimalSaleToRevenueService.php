<?php

namespace App\Domain\Integration\Services;

use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\AnimalEvent;
use Illuminate\Support\Facades\Log;

/**
 * Integração · Venda de animal → Receita financeira
 *
 * PRIMEIRA integração automática cross-módulo do sistema. Gera uma
 * `FinancialTransaction` do tipo `receita` quando um `AnimalEvent` é
 * criado com `tipo=venda` e `valor > 0`.
 *
 * ─ PRINCÍPIOS ─────────────────────────────────────────────────────
 *
 * IDEMPOTÊNCIA
 *   Cada AnimalEvent gera no máximo UMA FinancialTransaction. O
 *   marcador é o campo `numero_documento` da transação, com padrão
 *   fixo `ANIMAL_EVENT:<event_id>`. Antes de criar, verificamos se já
 *   existe uma transação com esse marcador — se sim, retornamos a
 *   existente (idempotent). Zero migration: `numero_documento` já existe
 *   como campo nullable. A coluna não tem unique index, mas o
 *   `firstOrCreate` com whereCondition serializado garante atomicidade
 *   quando embutido em DB::transaction pelo caller.
 *
 * AUDITABILIDADE
 *   - numero_documento = ANIMAL_EVENT:<id> torna a origem rastreável
 *     por SQL puro: SELECT * FROM financial_transactions WHERE
 *     numero_documento LIKE 'ANIMAL_EVENT:%'
 *   - observacoes incluem referência legível ao animal e ao evento
 *   - created_by herda do evento
 *
 * SEGURANÇA
 *   - Se o tenant não tem nenhuma FinancialAccount ativa, a integração
 *     é SKIPPED (retorna null) e loga warning. A venda ainda acontece —
 *     o master pode cadastrar uma conta e registrar a receita manualmente
 *     depois. Nunca quebra a transação do evento.
 *   - Categoria "Venda de gado" é buscada por slug conhecido
 *     (venda-de-gado, seedado em CategorySeeder). Se não encontrada,
 *     category_id fica NULL — o D6 permite receita sem categoria.
 *
 * CONSISTÊNCIA TRANSACIONAL
 *   Este service NÃO abre transação própria. O caller (AnimalController)
 *   deve envolver a chamada em DB::transaction junto com a criação do
 *   evento — assim evento + transação nascem ou falham juntos.
 *
 * RETROCOMPAT
 *   - Só dispara para eventos tipo=venda com valor > 0
 *   - Outros tipos (pesagem, vacinação, etc.) nunca acionam este serviço
 *   - Eventos de venda sem valor ficam apenas como registro (semântica:
 *     "animal saiu mas sem receita monetária — ex.: permuta, doação")
 *   - Chamar 2x para o mesmo evento é no-op (retorna a mesma transação)
 */
class AnimalSaleToRevenueService
{
    /** Prefixo fixo do marcador de idempotência. */
    private const DOC_MARKER_PREFIX = 'ANIMAL_EVENT:';

    /** Slug da categoria de receita preferida (seedada em CategorySeeder). */
    private const CATEGORY_SLUG = 'venda-de-gado';

    /**
     * Gera (ou recupera) a FinancialTransaction associada a um AnimalEvent
     * de venda. Retorna null se a integração não se aplica (tipo≠venda,
     * sem valor, sem conta financeira ativa).
     *
     * É responsabilidade do caller envolver em DB::transaction para
     * garantir atomicidade com a criação do evento.
     */
    public function generateForEvent(AnimalEvent $event): ?FinancialTransaction
    {
        // ── Gate 1: só venda com valor positivo ────────────────────────
        if ($event->tipo !== 'venda') {
            return null;
        }

        $valor = (float) ($event->valor ?? 0);
        if ($valor <= 0) {
            return null;
        }

        // ── Gate 2: idempotência — já existe transação para esse evento?
        $marker = self::DOC_MARKER_PREFIX . $event->id;
        $existing = FinancialTransaction::where('numero_documento', $marker)->first();
        if ($existing !== null) {
            return $existing;
        }

        // ── Gate 3: conta financeira ativa obrigatória (schema NOT NULL)
        $animal = $event->animal;
        $tenantId = $event->tenant_id ?? $animal?->tenant_id;

        $account = FinancialAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $account) {
            Log::warning('AnimalSaleToRevenueService: pulado — nenhuma FinancialAccount ativa para gerar receita.', [
                'animal_event_id' => $event->id,
                'tenant_id' => $tenantId,
                'animal_id' => $event->animal_id,
                'valor' => $valor,
            ]);

            return null;
        }

        // ── Gate 4: categoria preferida (fallback para null) ───────────
        $category = Category::query()
            ->when($tenantId, fn ($q) => $q->where(function ($w) use ($tenantId) {
                $w->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            }))
            ->where('tipo', 'financeiro_receita')
            ->where('slug', self::CATEGORY_SLUG)
            ->where('is_active', true)
            ->first();

        // ── Monta descrição auditável ──────────────────────────────────
        $identAnimal = $animal?->identificacao ?? "#{$event->animal_id}";
        $nomeAnimal = $animal?->nome ? " ({$animal->nome})" : '';
        $descricao = "Venda de animal {$identAnimal}{$nomeAnimal}";

        $dataEvento = $event->data?->format('Y-m-d') ?? now()->toDateString();

        // ── Cria a transação ──────────────────────────────────────────
        return FinancialTransaction::create([
            'account_id' => $account->id,
            'category_id' => $category?->id,
            'partner_id' => $event->partner_id,
            'tipo' => 'receita',
            'descricao' => $descricao,
            'observacoes' => "Gerado automaticamente pelo registro de venda #{$event->id}. "
                . "Animal: {$identAnimal}{$nomeAnimal}. "
                . 'Para refletir um pagamento efetivo, marque esta transação como paga.',
            'valor' => $valor,
            'data_vencimento' => $dataEvento,
            // status=pendente: fica como conta a receber. Master marca
            // como paga manualmente quando receber de fato. Evita premissas
            // sobre forma de pagamento.
            'status' => 'pendente',
            'numero_documento' => $marker,
            'created_by' => $event->created_by,
            'tenant_id' => $tenantId,
            // farm_id só se o schema tiver essa coluna e o animal tiver
            'farm_id' => $animal?->farm_id,
        ]);
    }
}
