<?php

namespace App\Models;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * ManualEnvio — registro de cada envio de manual do Master pro Dono.
 *
 * Funciona como UTM tracking: Master enviou cliente X, manual abriu via
 * link, sistema log opened_at + IP. Master vê na auditoria.
 *
 * Token gerado é embutido na URL signed do e-mail. Quando destinatário
 * clica, o controller valida assinatura + token, marca como aberto.
 */
class ManualEnvio extends Model
{
    protected $table = 'manual_envios';

    protected $fillable = [
        'token', 'manual_slug',
        'sender_id', 'tenant_id', 'recipient_id', 'recipient_email',
        'modo', 'tamanho_kb', 'mensagem',
        'opened_at', 'open_count', 'first_open_ip', 'last_open_ip', 'last_open_user_agent',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'open_count' => 'integer',
        'tamanho_kb' => 'integer',
    ];

    /**
     * Gera token único de 32 chars (hex). Suficientemente entropico
     * pra não ser adivinhável (10^38 combinações).
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('token', $token)->exists());
        return $token;
    }

    /**
     * Marca como aberto. Idempotente — incrementa open_count em cada clique.
     */
    public function markOpened(string $ip, ?string $userAgent = null): void
    {
        $this->open_count = $this->open_count + 1;
        if (! $this->opened_at) {
            $this->opened_at = now();
            $this->first_open_ip = $ip;
        }
        $this->last_open_ip = $ip;
        $this->last_open_user_agent = $userAgent ? mb_substr($userAgent, 0, 500) : null;
        $this->save();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
