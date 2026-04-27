<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RouteByHost — resolve o "contexto" do request baseado no Host HTTP.
 *
 * Reestruturação 2026-04-27: o sistema passa a servir múltiplos hosts:
 *
 *   1. fazendamacaybas.com.br (raiz) → 'master_landing'
 *   2. app.fazendamacaybas.com.br → 'app' (ERP)
 *   3. app.fazendamacaybas.com.br/c/{slug} → 'tenant_app'
 *   4. fazendadotiao.com.br (domínio próprio) → 'tenant_domain'
 *   5. localhost / IP / outros → 'legacy' (modo compatível, não bloqueia)
 *
 * Coloca em $request->attributes:
 *   - 'request_context' (string)
 *   - 'expected_tenant_id' (?int)
 *   - 'resolved_tenant' (?Tenant)
 *
 * Modo de bloqueio:
 *   - Se config('app.strict_host_gate') = true → context 'unknown' aborta 404
 *   - Senão (default) → context 'legacy' deixa passar livre, sem expected_tenant_id
 *     (compatível com sistema atual durante transição)
 *
 * Quem ATIVA o modo estrito: setar STRICT_HOST_GATE=true no .env apenas
 * APÓS a higienização e cadastro do tenant master estarem completos.
 */
class RouteByHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $appHost = $this->normalizeHost(config('app.url', ''));
        $appSubHost = $appHost ? 'app.' . $appHost : '';

        $context = 'legacy';
        $expectedTenantId = null;
        $resolvedTenant = null;

        if ($appHost && $host === $appHost) {
            // raiz → landing master
            $context = 'master_landing';
            try {
                $master = Tenant::master();
                $resolvedTenant = $master;
                $expectedTenantId = $master?->id;
            } catch (\Throwable $e) {
                // DB pode estar fora ou coluna ainda não migrada — degrada para legacy
                $context = 'legacy';
            }
        } elseif ($appSubHost && $host === $appSubHost) {
            // app.* → ERP
            $segments = explode('/', trim($request->path(), '/'));
            if (count($segments) >= 2 && $segments[0] === 'c') {
                $slug = $segments[1];
                try {
                    $tenant = Tenant::where('slug', $slug)->first();
                    if ($tenant !== null) {
                        $context = 'tenant_app';
                        $resolvedTenant = $tenant;
                        $expectedTenantId = $tenant->id;
                    } else {
                        $context = 'unknown';
                    }
                } catch (\Throwable $e) {
                    $context = 'legacy';
                }
            } else {
                $context = 'app';
            }
        } else {
            // host externo → tenta resolver por tenants.domains
            try {
                $tenant = Tenant::findByDomain($host);
                if ($tenant !== null) {
                    $context = 'tenant_domain';
                    $resolvedTenant = $tenant;
                    $expectedTenantId = $tenant->id;
                }
            } catch (\Throwable $e) {
                // tabela ainda sem coluna domains, ou DB fora — segue legacy
            }
        }

        $request->attributes->set('request_context', $context);
        $request->attributes->set('expected_tenant_id', $expectedTenantId);
        if ($resolvedTenant !== null) {
            $request->attributes->set('resolved_tenant', $resolvedTenant);
        }

        // Modo estrito (APÓS migração completa)
        if ($context === 'unknown' && config('app.strict_host_gate', false)) {
            if (config('app.debug')) {
                abort(404, "Host não reconhecido: {$host}. Esperado: {$appHost} ou {$appSubHost} ou domínio cadastrado em tenants.domains.");
            }
            abort(404);
        }

        return $next($request);
    }

    private function normalizeHost(string $url): string
    {
        if ($url === '') return '';
        $parsed = parse_url($url);
        return strtolower($parsed['host'] ?? $url);
    }
}
