<?php

namespace App\Domain\Tenancy\Detector;

/**
 * Registro estático de eventos já logados pelo detector nesta request.
 *
 * Propósito único: evitar log-spam.
 * Quando uma listagem carrega 100 animais, o trait BelongsToTenant dispara
 * `retrieved` 100 vezes. Sem deduplicação, 100 linhas idênticas no log.
 * Aqui garantimos que um `(model × kind × par tenant)` é logado 1x por request.
 *
 * Static property (não singleton via container) porque:
 *   - PHP-FPM zera entre requests automaticamente (sem reset manual)
 *   - Queue workers zeram via `queue:work --max-time` ou processos efêmeros
 *   - Uso thread-safe em shared hosting (sem sharing de memória entre processos)
 *
 * Trait BelongsToTenant tem property estática PRÓPRIA por classe (limitação
 * do PHP para static em traits). Por isso centralizamos aqui.
 */
class TenancyDetectorRegistry
{
    /** @var array<string, true> chaves únicas já logadas nesta request. */
    private static array $logged = [];

    /**
     * Marca o evento como logado e retorna true se é a primeira vez.
     * Usado no padrão `if (!Registry::shouldLog($key)) return;`.
     */
    public static function shouldLog(string $key): bool
    {
        if (isset(self::$logged[$key])) {
            return false;
        }
        self::$logged[$key] = true;

        return true;
    }

    /**
     * Limpa o registro. Útil em testes ou re-execução manual.
     */
    public static function reset(): void
    {
        self::$logged = [];
    }

    /**
     * Quantos eventos distintos foram logados nesta request.
     * Exposto para métricas ou debug em ambiente dev.
     */
    public static function count(): int
    {
        return count(self::$logged);
    }
}
