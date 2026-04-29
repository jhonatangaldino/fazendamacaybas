<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Trait LogsAtividade
 *
 * Habilita auditoria padrão (Spatie Activitylog) com defaults sensatos:
 *   - Loga eventos: created, updated, deleted, restored
 *   - Loga apenas atributos do $fillable (ignora timestamps internos)
 *   - Não loga atualizações vazias (logOnlyDirty)
 *   - Inclui causer (quem fez) automaticamente via Spatie
 *
 * Uso: basta `use LogsAtividade;` no model. Não precisa redefinir
 * `getActivitylogOptions()` a menos que queira customizar.
 *
 * Quando customizar:
 *   - Pra logar campos que NÃO estão no fillable: sobrescrever
 *     getActivitylogOptions() no model.
 *   - Pra excluir campos sensíveis: idem.
 */
trait LogsAtividade
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
