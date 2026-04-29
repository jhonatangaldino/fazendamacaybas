<?php

namespace App\Providers;

use App\Services\BarcodeLookup\ProductLookupService;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ProductLookupService — orquestrador de consulta de produto por código de barras.
        // Singleton: reusa as instâncias das 11 fontes por request (evita boot repetido).
        $this->app->singleton(ProductLookupService::class, function ($app) {
            return new ProductLookupService(
                config: $app['config']->get('barcode_lookup', []),
                logger: $app->make(LoggerInterface::class),
                cache: $app->make(Cache::class),
            );
        });
    }

    public function boot(): void
    {
        Carbon::setLocale('pt_BR');
        date_default_timezone_set(config('app.timezone', 'America/Sao_Paulo'));

        Paginator::useTailwind();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureMailNotifications();
        $this->configureImpersonationGate();

        // Observer que mantém financial_accounts.saldo_atual sincronizado com
        // transações pagas (receitas somam, despesas subtraem). Antes disso,
        // o saldo ficava preso em saldo_inicial mesmo com transações registradas.
        \App\Models\Financial\FinancialTransaction::observe(\App\Observers\FinancialTransactionObserver::class);

        // Popula tenant_id + farm_id em todo Activity registrado pelo Spatie
        // Activitylog. Garante que master, ao listar auditoria, veja a qual
        // cliente + fazenda a ação pertence — mesmo que tenha sido feita
        // via impersonação (sessão master entrando como cliente).
        \Spatie\Activitylog\Models\Activity::observe(\App\Observers\ActivityLogTenantFarmObserver::class);
    }

    /**
     * M8.B — Gate::before especial para master durante impersonação.
     *
     * Durante impersonação ativa, o master (user.tenant_id NULL) precisa
     * operar o tenant impersonado. Mas no modelo M8, master tem apenas
     * `platform.*` no banco — nunca ganhou `operational.*`. Sem este Gate,
     * rotas que exigem `permission:operational.rebanho.view` bloqueariam
     * Jhonatan impersonando.
     *
     * SOLUÇÃO (opção c do desenho M8):
     *   Gate::before intercepta TODO check de permission/ability.
     *   Se for master COM session('impersonation') ativa para ele → libera.
     *   Senão → retorna null e deixa Spatie decidir normalmente.
     *
     * Propriedades:
     *   - Só ativa quando impersonação existe E pertence a este master (defesa
     *     anti-hijack, mesmo pattern do ResolveTenant branch 2)
     *   - Master PURO (sem impersonação) NÃO recebe super-poderes — só as
     *     permissions que ele realmente tem (platform.*)
     *   - Tenant user NUNCA é afetado (user.tenant_id !== null → return null)
     *   - Retornar null não decide nada; Spatie continua o fluxo normal
     */
    protected function configureImpersonationGate(): void
    {
        Gate::before(function ($user, $ability) {
            // Tenant user ou unauthenticated: lógica normal
            if ($user === null || $user->tenant_id !== null) {
                return null;
            }

            // Master: só libera tudo se estiver em impersonação ATIVA dele próprio
            $imp = session('impersonation');
            if (is_array($imp)
                && isset($imp['impersonator_user_id'])
                && (int) $imp['impersonator_user_id'] === (int) $user->id) {
                return true;
            }

            // Master puro: sem super-poderes — Spatie verifica platform.* normalmente
            return null;
        });
    }

    /**
     * Customiza as mensagens padrão de autenticação do Laravel para pt-BR.
     * Laravel usa Notifications com textos hardcoded em inglês — aqui sobrescrevemos.
     */
    protected function configureMailNotifications(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $primeiroNome = $notifiable->name ? explode(' ', $notifiable->name)[0] : '';

            return (new MailMessage)
                ->subject('Redefinição de senha — Fazenda Macaybas')
                ->greeting($primeiroNome ? "Olá, {$primeiroNome}!" : 'Olá!')
                ->line('Recebemos uma solicitação de redefinição de senha para sua conta no sistema da Fazenda Macaybas.')
                ->action('Redefinir minha senha', $url)
                ->line('Este link expira em **60 minutos** por motivo de segurança.')
                ->line('Se você não solicitou a redefinição, pode ignorar este e-mail com segurança — nenhuma alteração será feita.')
                ->salutation('Atenciosamente,<br>Equipe Fazenda Macaybas');
        });

        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            $primeiroNome = $notifiable->name ? explode(' ', $notifiable->name)[0] : '';

            return (new MailMessage)
                ->subject('Confirme seu e-mail — Fazenda Macaybas')
                ->greeting($primeiroNome ? "Olá, {$primeiroNome}!" : 'Olá!')
                ->line('Por favor, confirme seu endereço de e-mail clicando no botão abaixo:')
                ->action('Confirmar e-mail', $url)
                ->line('Se você não criou esta conta, pode ignorar este e-mail.')
                ->salutation('Atenciosamente,<br>Equipe Fazenda Macaybas');
        });
    }
}
