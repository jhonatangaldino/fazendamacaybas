<?php

namespace App\Http\Controllers\Master\Clientes\Cms;

use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * SettingsController do CMS por cliente.
 *
 * Exibe os settings "per-cliente" (site.*, tema.*, landing.*) para um
 * cliente específico. A listagem inclui:
 *   - Settings já cadastrados para este cliente (tenant_id = $cliente->id)
 *   - Settings globais (tenant_id NULL) — como referência visual; ao salvar,
 *     viram overrides do cliente automaticamente.
 *
 * Settings com prefixo platform ou billing NÃO são expostos aqui — são
 * da plataforma, não do cliente.
 *
 * Gravação: updateOrCreate com `(tenant_id, key)` aproveitando o unique
 * composto — cria override do cliente se não existir, ou atualiza valor.
 */
class SettingsController extends Controller
{
    /** Prefixos de settings relevantes para a landing do cliente. */
    private const CLIENT_PREFIXES = ['site', 'tema', 'landing', 'contato', 'seo', 'social'];

    public function index(Tenant $cliente)
    {
        // Settings do cliente específico
        $clientSettings = Setting::where('tenant_id', $cliente->id)->get();
        $clientByKey = $clientSettings->keyBy('key');

        // Settings globais (fallback) — só expõe chaves de prefixos "de landing"
        $globalSettings = Setting::whereNull('tenant_id')
            ->where(function ($q) {
                foreach (self::CLIENT_PREFIXES as $p) {
                    $q->orWhere('key', 'like', $p . '.%');
                }
            })
            ->get();

        // Merge: cliente sobrescreve global
        $effective = $globalSettings
            ->keyBy('key')
            ->map(function ($global) use ($clientByKey) {
                $override = $clientByKey->get($global->key);
                $s = $override ?: $global;
                // expõe também origem, útil para UI indicar "herdado"
                $s->setAttribute('tenant_override', (bool) $override);
                return $s;
            })
            ->values();

        // Settings do cliente que NÃO têm equivalente global — ainda assim mostra
        foreach ($clientSettings as $cs) {
            if (! $globalSettings->firstWhere('key', $cs->key)) {
                $cs->setAttribute('tenant_override', true);
                $effective->push($cs);
            }
        }

        // Flag de onboarding do cliente (guardada no próprio sistema de
        // Settings via chave `cms.onboarding.completed`, override por cliente).
        // Não aparece como setting editável porque `cms.*` não está em
        // CLIENT_PREFIXES — lido aqui direto para a UI.
        $onboardingCompleted = (bool) Setting::where('tenant_id', $cliente->id)
            ->where('key', 'cms.onboarding.completed')
            ->value('value');

        return Inertia::render('Master/Clientes/Cms/Settings', [
            'cliente' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'slug' => $cliente->slug,
                'landing_url' => url('/c/' . $cliente->slug),
            ],
            'settings' => $effective
                ->sortBy([['group', 'asc'], ['order_column', 'asc']])
                ->values()
                ->groupBy('group'),
            'onboarding_completed' => $onboardingCompleted,
        ]);
    }

    public function update(Request $request, Tenant $cliente)
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($data['settings'] as $item) {
            // Grava como override do cliente (tenant_id = $cliente->id)
            Setting::updateOrCreate(
                ['key' => $item['key'], 'tenant_id' => $cliente->id],
                ['value' => $item['value']]
            );
            Setting::forgetMemory($item['key'], $cliente->id);
        }

        return back()->with('success', 'Alterações publicadas com sucesso.');
    }

    /**
     * Marca o onboarding do cliente como concluído.
     *
     * Guardado no próprio sistema de Settings (override por cliente) em
     * `cms.onboarding.completed` com type=bool. Leitura em `index()` via
     * Setting::where(...)->value('value') interpreta o "1" como truthy.
     *
     * Idempotente — rodar 2× apenas atualiza o timestamp.
     */
    public function completeOnboarding(Tenant $cliente)
    {
        Setting::setValue(
            key: 'cms.onboarding.completed',
            value: true,
            type: 'bool',
            group: 'cms',
            tenantId: $cliente->id,
        );

        return back();
    }

    public function uploadFile(Request $request, Tenant $cliente): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/x-icon,image/vnd.microsoft.icon',
            ],
            'key' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first('file') ?: 'Falha no upload.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) ?: $file->guessExtension();
        $filename = \Illuminate\Support\Str::slug(
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
        ) . '-' . now()->format('YmdHis') . '.' . $ext;

        // Upload em pasta por cliente
        $path = $file->storeAs('settings/cliente-' . $cliente->id, $filename, 'public');

        // Remove arquivo anterior do cliente (se houver)
        $setting = Setting::where('key', $data['key'])
            ->where('tenant_id', $cliente->id)
            ->first();
        if ($setting && $setting->value && Storage::disk('public')->exists($setting->value)) {
            Storage::disk('public')->delete($setting->value);
        }

        Setting::updateOrCreate(
            ['key' => $data['key'], 'tenant_id' => $cliente->id],
            ['value' => $path]
        );
        Setting::forgetMemory($data['key'], $cliente->id);

        return response()->json([
            'ok' => true,
            'message' => 'Imagem atualizada.',
            'key' => $data['key'],
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function removeFile(Request $request, Tenant $cliente): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
        ]);

        $setting = Setting::where('key', $data['key'])
            ->where('tenant_id', $cliente->id)
            ->first();

        if ($setting && $setting->value && Storage::disk('public')->exists($setting->value)) {
            Storage::disk('public')->delete($setting->value);
        }

        if ($setting) {
            $setting->update(['value' => null]);
            Setting::forgetMemory($data['key'], $cliente->id);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Imagem removida.',
            'key' => $data['key'],
        ]);
    }
}
