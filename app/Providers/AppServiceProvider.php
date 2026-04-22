<?php

namespace App\Providers;

use App\Services\BarcodeLookup\ProductLookupService;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
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
