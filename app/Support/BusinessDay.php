<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * BusinessDay — utilitários de dia útil para billing.
 *
 * Escopo MVP: ignora apenas sábado e domingo. Feriados nacionais NÃO entram
 * neste cálculo (decisão consciente — adicionar feriados depois muda apenas
 * a lista interna, sem quebrar callers).
 *
 * Por que não usar pacote externo: todos os pacotes brasileiros de feriados
 * (`spatie/business-time`, `brunoklein99/business-day`) trazem peso e dependências
 * que aqui não justificam — temos 1 regra simples (5º dia útil), e ignorar S/D
 * resolve 95% dos casos. Quando precisarmos de feriados, basta listar em config.
 */
class BusinessDay
{
    /**
     * Retorna o n-ésimo dia útil de um mês.
     * Ex: nthOfMonth(2026, 5, 5) → "2026-05-07" (5º dia útil de Maio/2026).
     *
     * @param int $year  ano (ex: 2026)
     * @param int $month mês (1-12)
     * @param int $n     n-ésimo dia útil (1-base; ex: 5 = quinto)
     */
    public static function nthOfMonth(int $year, int $month, int $n): Carbon
    {
        $date = Carbon::create($year, $month, 1);
        $found = 0;

        // Loop limitado: pior caso 7 fins-de-semana num mês = max ~31 iterações.
        // Garantia: nthOfMonth(_, _, 22+) ainda termina dentro do mês — se passar
        // do dia 31 sem encontrar, retorna o último dia útil disponível.
        $last = $date->copy();
        while ($date->month === $month) {
            if ($date->isWeekday()) {
                $found++;
                $last = $date->copy();
                if ($found === $n) {
                    return $date;
                }
            }
            $date->addDay();
        }

        // n maior que o número de dias úteis do mês → retorna o último dia útil.
        return $last;
    }

    /**
     * 5º dia útil do mês — atalho semântico (regra principal do billing).
     */
    public static function fifthOfMonth(int $year, int $month): Carbon
    {
        return self::nthOfMonth($year, $month, 5);
    }

    /**
     * Próximo dia útil >= $from. Se $from já é dia útil, retorna $from.
     */
    public static function nextOnOrAfter(Carbon $from): Carbon
    {
        $d = $from->copy();
        while (! $d->isWeekday()) {
            $d->addDay();
        }
        return $d;
    }

    /**
     * Próximo n-ésimo dia útil a partir de $from (inclusive).
     * Ex: nextNthFrom(now(), 5) → 5º dia útil contando a partir de hoje.
     */
    public static function nextNthFrom(Carbon $from, int $n): Carbon
    {
        $d = $from->copy();
        $found = 0;
        while ($found < $n) {
            if ($d->isWeekday()) {
                $found++;
                if ($found === $n) {
                    return $d;
                }
            }
            $d->addDay();
        }
        return $d;
    }
}
