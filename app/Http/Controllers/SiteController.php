<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Cms\Support\MapResolver;
use App\Models\Cms\Page;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    /**
     * Cliente default da landing pública quando não há slug na URL ("/").
     * Sempre o tenant fundador (Macaybas) — preservado desde R1.3.
     */
    private const DEFAULT_TENANT_ID = 1;

    public function home()
    {
        // Garante que `app('tenant_id')` está bindado para TODA leitura
        // downstream desta request — SiteController, partials de header/footer
        // e helpers do Setting/MapResolver passam a enxergar o tenant certo.
        // Em "/" não há bind prévio → usa o tenant default (Macaybas).
        // Em "/c/{slug}" o bind já foi feito em homeByCliente().
        if (! app()->bound('tenant_id')) {
            app()->instance('tenant_id', self::DEFAULT_TENANT_ID);
        }
        $tenantId = (int) app('tenant_id');

        $page = Page::with(['activeSections' => fn ($q) => $q->orderBy('order_column')])
            ->where('tenant_id', $tenantId)
            ->where('slug', 'home')
            ->where('is_published', true)
            ->firstOrFail();

        return view('site.home', [
            'sections' => $page->activeSections->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->type,
                'data' => $s->dataForPublic(),
            ])->all(),
            'meta' => [
                'title' => $page->meta_title ?: $page->titulo,
                'description' => $page->meta_description ?: Setting::getValue('seo.default_description'),
                'keywords' => $page->meta_keywords,
                'og_image' => $page->og_image_path ? asset('storage/'.$page->og_image_path) : null,
            ],
            // Resolver do mapa encapsula a prioridade:
            // embed > lat/lng > endereço > null (oculta bloco).
            'map' => MapResolver::resolve(),
        ]);
    }

    /**
     * Landing pública por cliente: GET /c/{slug}.
     *
     * O Laravel resolve o Tenant pelo slug via route-model-binding
     * (`{cliente:slug}`) — slug inexistente → 404 automático.
     *
     * Bindar `app('tenant_id')` antes de delegar para home() garante que
     * toda leitura downstream sensível a tenant (ex.: Setting::getValue() com
     * fallback tenant → global) opere no contexto deste cliente. O bind vive
     * apenas na vida útil da request pública: em FPM o container morre com
     * a request; não contamina outros handlers.
     *
     * Esta fase (FASE 2) só estabelece o roteamento e o contexto. A filtragem
     * do CMS (Page/Section/Menu) por tenant_id é assunto de fase futura e
     * NÃO é feita aqui — o método home() é reaproveitado exatamente como está.
     */
    public function homeByCliente(Tenant $cliente)
    {
        app()->instance('tenant_id', (int) $cliente->id);

        return $this->home();
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev',
            'time' => now()->format('d/m/Y H:i:s'),
        ]);
    }
}
