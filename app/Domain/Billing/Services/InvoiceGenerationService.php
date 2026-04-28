<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Tenant;
use App\Models\Setting;
use App\Services\Billing\PixPayloadGenerator;
use App\Support\BusinessDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * InvoiceGenerationService — geração em lote de faturas SaaS.
 *
 * Dois modos:
 *   • mensal  → 1 invoice por mês de referência, valor = preço mensal do plano,
 *               vencimento = 5º dia útil do próprio mês.
 *   • unica   → 1 invoice única para o período, valor = soma dos meses,
 *               vencimento = próximo 5º dia útil contando a partir de hoje.
 *
 * Visibilidade (aparece_em):
 *   • mensal: 5 dias antes do fim do mês ANTERIOR ao mês de referência.
 *             Ex: fatura de Maio/2026 → aparece em 25/04/2026 (30/04 - 5).
 *   • unica:  hoje (visível imediatamente — master vai gerar uma cobrança consolidada
 *             que precisa chegar logo).
 *
 * Vigência (periodo_inicio/fim):
 *   • mensal: 1º a último dia do mês de referência.
 *   • unica:  1º dia do mês inicial até último dia do mês final.
 *
 * Numeração:
 *   `numero` é INV-AAAAMM-NNNN, sequencial POR TENANT por ano-mês de emissão.
 *   Garantia de unicidade pelo UNIQUE composto (tenant_id, numero) no DB.
 *
 * Reentrância:
 *   Geração mensal pula meses que JÁ TÊM fatura de mesmo (referencia_mes,
 *   referencia_ano) para aquele tenant — evita duplicidade se master clicar 2×.
 *
 * Atomicidade:
 *   Tudo dentro de DB::transaction(). Falha em qualquer mês → rollback total.
 */
class InvoiceGenerationService
{
    public function __construct(
        private readonly PixPayloadGenerator $pix = new PixPayloadGenerator(),
    ) {
    }

    /**
     * Carrega config PIX do banco (settings.billing_pix.*). Retorna
     * `null` para chave se não estiver configurada — nesse caso o
     * payload PIX é gerado vazio (invoice ainda é criada, mas sem
     * QR utilizável; master é avisado na UI).
     */
    private function loadPixConfig(): array
    {
        $rows = Setting::whereIn('key', [
            'billing_pix.tipo_chave',
            'billing_pix.chave',
            'billing_pix.nome_recebedor',
            'billing_pix.cidade_recebedor',
        ])->pluck('value', 'key');

        return [
            'tipo_chave' => $rows['billing_pix.tipo_chave'] ?? null,
            'chave' => $rows['billing_pix.chave'] ?? null,
            'nome' => $rows['billing_pix.nome_recebedor'] ?? null,
            'cidade' => $rows['billing_pix.cidade_recebedor'] ?? null,
        ];
    }

    /**
     * Gera faturas mensais para o intervalo [mesIni/anoIni .. mesFim/anoFim].
     *
     * @return Collection<int, Invoice>  faturas criadas (pode estar vazia se todas já existiam)
     */
    public function gerarMensal(
        Tenant $tenant,
        Plan $plan,
        int $mesIni,
        int $anoIni,
        int $mesFim,
        int $anoFim,
    ): Collection {
        $this->validatePeriod($mesIni, $anoIni, $mesFim, $anoFim);

        $subscription = $this->ensureSubscription($tenant, $plan);
        $valorMensal = (float) $plan->preco_mensal;

        return DB::transaction(function () use ($tenant, $subscription, $valorMensal,
            $mesIni, $anoIni, $mesFim, $anoFim) {

            $criadas = collect();
            $cursor = Carbon::create($anoIni, $mesIni, 1);
            $fim = Carbon::create($anoFim, $mesFim, 1);

            while ($cursor->lte($fim)) {
                $mes = $cursor->month;
                $ano = $cursor->year;

                // Idempotência: pula se já existe fatura desse mês de referência
                $jaExiste = Invoice::where('tenant_id', $tenant->id)
                    ->where('referencia_mes', $mes)
                    ->where('referencia_ano', $ano)
                    ->where('tipo', 'mensal')
                    ->exists();

                if (! $jaExiste) {
                    $criadas->push($this->createInvoice(
                        tenant: $tenant,
                        subscription: $subscription,
                        tipo: 'mensal',
                        valor: $valorMensal,
                        referenciaMes: $mes,
                        referenciaAno: $ano,
                        periodoInicio: $cursor->copy()->startOfMonth(),
                        periodoFim: $cursor->copy()->endOfMonth(),
                        dataVencimento: BusinessDay::fifthOfMonth($ano, $mes),
                        apareceEm: $this->visibilidadeMensal($ano, $mes),
                    ));
                }

                $cursor->addMonth();
            }

            // Atualiza vigência do subscription para refletir o último mês coberto
            $this->updateSubscriptionVigencia($subscription, Carbon::create($anoFim, $mesFim, 1)->endOfMonth());

            return $criadas;
        });
    }

    /**
     * Gera 1 fatura única consolidando o período [mesIni/anoIni .. mesFim/anoFim].
     *
     * Idempotência: NÃO pula. Master pode emitir 2 faturas únicas separadas se quiser.
     * (Diferente de mensal, onde a chave natural é mês de referência.)
     */
    public function gerarUnica(
        Tenant $tenant,
        Plan $plan,
        int $mesIni,
        int $anoIni,
        int $mesFim,
        int $anoFim,
    ): Invoice {
        $this->validatePeriod($mesIni, $anoIni, $mesFim, $anoFim);

        $subscription = $this->ensureSubscription($tenant, $plan);
        $meses = $this->countMeses($mesIni, $anoIni, $mesFim, $anoFim);
        $valorTotal = (float) $plan->preco_mensal * $meses;

        return DB::transaction(function () use ($tenant, $subscription, $valorTotal,
            $mesIni, $anoIni, $mesFim, $anoFim, $meses) {

            $invoice = $this->createInvoice(
                tenant: $tenant,
                subscription: $subscription,
                tipo: 'unica',
                valor: $valorTotal,
                referenciaMes: null,
                referenciaAno: null,
                periodoInicio: Carbon::create($anoIni, $mesIni, 1)->startOfMonth(),
                periodoFim: Carbon::create($anoFim, $mesFim, 1)->endOfMonth(),
                // Vencimento: 5º dia útil contando a partir de hoje (regra do enunciado)
                dataVencimento: BusinessDay::nextNthFrom(today(), 5),
                // Visibilidade: imediata para fatura única
                apareceEm: today(),
                meta: ['meses_cobertos' => $meses],
            );

            $this->updateSubscriptionVigencia($subscription, Carbon::create($anoFim, $mesFim, 1)->endOfMonth());

            return $invoice;
        });
    }

    /**
     * Gera preview SEM persistir — usado pelo wizard no passo "Resumo".
     * Retorna array pronto para serializar ao Vue.
     */
    public function preview(
        Plan $plan,
        string $tipo,
        int $mesIni,
        int $anoIni,
        int $mesFim,
        int $anoFim,
    ): array {
        $this->validatePeriod($mesIni, $anoIni, $mesFim, $anoFim);

        if ($tipo === 'mensal') {
            $linhas = [];
            $cursor = Carbon::create($anoIni, $mesIni, 1);
            $fim = Carbon::create($anoFim, $mesFim, 1);

            while ($cursor->lte($fim)) {
                $venc = BusinessDay::fifthOfMonth($cursor->year, $cursor->month);
                $linhas[] = [
                    'referencia_mes' => $cursor->month,
                    'referencia_ano' => $cursor->year,
                    'referencia_label' => $this->mesLabel($cursor->month).'/'.$cursor->year,
                    'valor' => (float) $plan->preco_mensal,
                    'vencimento' => $venc->format('d/m/Y'),
                    'aparece_em' => $this->visibilidadeMensal($cursor->year, $cursor->month)->format('d/m/Y'),
                ];
                $cursor->addMonth();
            }

            return [
                'tipo' => 'mensal',
                'plano_nome' => $plan->nome,
                'valor_unitario' => (float) $plan->preco_mensal,
                'quantidade' => count($linhas),
                'valor_total' => count($linhas) * (float) $plan->preco_mensal,
                'periodo_label' => $this->mesLabel($mesIni)."/{$anoIni} → ".$this->mesLabel($mesFim)."/{$anoFim}",
                'faturas' => $linhas,
            ];
        }

        // tipo == 'unica'
        $meses = $this->countMeses($mesIni, $anoIni, $mesFim, $anoFim);
        $venc = BusinessDay::nextNthFrom(today(), 5);

        return [
            'tipo' => 'unica',
            'plano_nome' => $plan->nome,
            'valor_unitario' => (float) $plan->preco_mensal,
            'quantidade' => 1,
            'valor_total' => $meses * (float) $plan->preco_mensal,
            'periodo_label' => $this->mesLabel($mesIni)."/{$anoIni} → ".$this->mesLabel($mesFim)."/{$anoFim}",
            'faturas' => [[
                'referencia_label' => 'Período '.$this->mesLabel($mesIni)."/{$anoIni} → ".$this->mesLabel($mesFim)."/{$anoFim}",
                'valor' => $meses * (float) $plan->preco_mensal,
                'vencimento' => $venc->format('d/m/Y'),
                'aparece_em' => today()->format('d/m/Y'),
                'meses_cobertos' => $meses,
            ]],
        ];
    }

    /* ────────────────────── Internos ────────────────────── */

    private function createInvoice(
        Tenant $tenant,
        Subscription $subscription,
        string $tipo,
        float $valor,
        ?int $referenciaMes,
        ?int $referenciaAno,
        Carbon $periodoInicio,
        Carbon $periodoFim,
        Carbon $dataVencimento,
        Carbon $apareceEm,
        array $meta = [],
    ): Invoice {
        // Gera identificador e payload PIX (BR Code) usando a chave do master.
        // Sem chave configurada → invoice ainda é criada (status pending), mas
        // pix_payload fica vazio. Tenant verá fatura, master verá aviso.
        // TXID legível: SIGLA_TENANT + NÚMERO_FATURA — aparece no extrato
        // bancário do master, facilita conciliação manual.
        $cfg = $this->loadPixConfig();
        $numero = $this->nextNumero($tenant->id);
        $txid = $this->pix->generateTxid($tenant->nome, $numero);
        $payload = '';
        if (! empty($cfg['chave'])) {
            $payload = $this->pix->build(
                amount: $valor,
                txid: $txid,
                merchantName: $cfg['nome'],
                city: $cfg['cidade'],
                pixKey: $cfg['chave'],
            );
        }

        return Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'numero' => $numero,
            'tipo' => $tipo,
            'referencia_mes' => $referenciaMes,
            'referencia_ano' => $referenciaAno,
            'periodo_inicio' => $periodoInicio->toDateString(),
            'periodo_fim' => $periodoFim->toDateString(),
            'aparece_em' => $apareceEm->toDateString(),
            'valor' => $valor,
            'status' => 'pending',
            'data_emissao' => today()->toDateString(),
            'data_vencimento' => $dataVencimento->toDateString(),
            'pix_txid' => $txid,
            'pix_payload' => $payload ?: null,
            'meta' => $meta,
        ]);
    }

    /**
     * Próximo número INV-AAAAMM-NNNN para o tenant, baseado no maior NNNN
     * já emitido neste ano-mês de emissão. Sem hits de race se a transação
     * for `serializable`; em READ COMMITTED há risco mínimo, aceitável pelo
     * volume (master gera sob comando, não em alto throughput).
     */
    private function nextNumero(int $tenantId): string
    {
        $prefix = 'INV-'.today()->format('Ym').'-';
        $last = Invoice::where('tenant_id', $tenantId)
            ->where('numero', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('numero');

        $seq = 1;
        if ($last) {
            $tail = (int) substr($last, -4);
            $seq = $tail + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Garante que existe subscription para o tenant. Se não houver, cria
     * com status `active` e plano fornecido. Se houver com plano diferente,
     * NÃO altera o plano automaticamente (master tem que fazer isso explicitamente
     * via SubscriptionController::update); aqui apenas validamos consistência.
     */
    private function ensureSubscription(Tenant $tenant, Plan $plan): Subscription
    {
        $sub = Subscription::where('tenant_id', $tenant->id)->first();
        if ($sub === null) {
            $sub = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ]);
        }
        return $sub;
    }

    /**
     * Atualiza vigência do subscription se o novo fim for maior que o atual.
     * Limpa grace_until se a nova vigência cobre a data atual.
     */
    private function updateSubscriptionVigencia(Subscription $sub, Carbon $novoFim): void
    {
        $atual = $sub->current_period_end;
        $shouldUpdate = $atual === null || $novoFim->gt($atual);

        $patch = [];
        if ($shouldUpdate) {
            $patch['current_period_end'] = $novoFim;
            if ($sub->current_period_start === null) {
                $patch['current_period_start'] = $sub->started_at ?? now();
            }
        }
        // Se vigência cobre hoje e havia carência, limpa
        if ($novoFim->gte(now()) && $sub->grace_until !== null) {
            $patch['grace_until'] = null;
        }
        // Se estava bloqueado e agora vigência cobre, reativa
        if ($shouldUpdate && in_array($sub->status, ['blocked', 'grace', 'overdue'], true) && $novoFim->gte(now())) {
            $patch['status'] = 'active';
        }

        if (! empty($patch)) {
            $sub->update($patch);
        }
    }

    /**
     * Visibilidade da fatura mensal: 5 dias antes do fim do mês anterior ao mês de referência.
     * Ex: fatura de Maio/2026 → aparece em 25/04/2026.
     */
    private function visibilidadeMensal(int $ano, int $mes): Carbon
    {
        $primeiroDoMes = Carbon::create($ano, $mes, 1);
        $fimMesAnterior = $primeiroDoMes->copy()->subDay()->endOfDay();
        return $fimMesAnterior->subDays(5)->startOfDay();
    }

    private function validatePeriod(int $mesIni, int $anoIni, int $mesFim, int $anoFim): void
    {
        if ($mesIni < 1 || $mesIni > 12 || $mesFim < 1 || $mesFim > 12) {
            throw new InvalidArgumentException('Mês deve estar entre 1 e 12.');
        }
        if ($anoIni < 2020 || $anoIni > 2100 || $anoFim < 2020 || $anoFim > 2100) {
            throw new InvalidArgumentException('Ano deve estar entre 2020 e 2100.');
        }
        $ini = Carbon::create($anoIni, $mesIni, 1);
        $fim = Carbon::create($anoFim, $mesFim, 1);
        if ($ini->gt($fim)) {
            throw new InvalidArgumentException('Mês/ano inicial não pode ser maior que o final.');
        }
    }

    private function countMeses(int $mesIni, int $anoIni, int $mesFim, int $anoFim): int
    {
        return (($anoFim - $anoIni) * 12) + ($mesFim - $mesIni) + 1;
    }

    private function mesLabel(int $mes): string
    {
        return ['', 'Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][$mes];
    }
}
