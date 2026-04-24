<?php

namespace App\Models;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * IMPORTANTE: este model NÃO usa o trait BelongsToTenant.
 *
 * Motivo: o trait dispara no evento `retrieved` uma chamada a `auth()->check()`.
 * Laravel Auth, por sua vez, faz `User::find()` para resolver o usuário atual —
 * o que re-dispara `retrieved`, criando recursão infinita e stack overflow.
 *
 * O isolamento de User por tenant será tratado pelo middleware na R2, não via
 * event hook. Mantemos `tenant_id` no fillable + relation `tenant()` apenas.
 */
class User extends Authenticatable
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'cpf',
        'telefone',
        'cargo',
        'password',
        'must_change_password',
        'is_active',
        'avatar_path',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active', 'cargo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function isAdminMaster(): bool
    {
        return $this->hasRole('admin_master');
    }

    public function isDonoFazenda(): bool
    {
        return $this->hasRole('dono_fazenda');
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return asset('storage/'.$this->avatar_path);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * URL de destino após login e quando o user logado acessa rota guest.
     *
     * M0 — regra de roteamento por tipo de usuário:
     *   - MASTER (tenant_id NULL) → /master/dashboard (área da plataforma)
     *   - TENANT USER             → /admin/dashboard (área operacional)
     *
     * FALLBACK DEFENSIVO:
     *   A rota `master.dashboard` só será criada em M1. Enquanto ela não existir,
     *   `Route::has('master.dashboard')` retorna false e o master é direcionado ao
     *   fallback operacional — exatamente o comportamento atual pré-M0. Zero
     *   regressão. Quando M1 entrar, a transição acontece automaticamente sem
     *   alteração neste método.
     */
    public function homeUrl(): string
    {
        if ($this->tenant_id === null && Route::has('master.dashboard')) {
            return route('master.dashboard');
        }

        // Tenant user → Hub "O que você quer fazer?" (porta de entrada oficial).
        // O dashboard antigo continua acessível via sidebar e link no rodapé do hub,
        // mas não é mais o destino padrão após login.
        return route('admin.inicio');
    }
}
