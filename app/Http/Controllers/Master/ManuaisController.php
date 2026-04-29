<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Mail\ManualUsuarioMail;
use App\Models\User;
use App\Services\ManualBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * ManuaisController — distribuição de manuais (Master)
 *
 * Funcionalidades:
 *   1. Listar manuais disponíveis (catálogo do ManualBuilder)
 *   2. Download direto do manual (HTML self-contained com imagens base64)
 *   3. AJAX: lista de tenants ativos (pra filtro de envio)
 *   4. AJAX: lista de "donos da fazenda" ativos de um tenant (destinatários)
 *   5. Disparo de envio por e-mail com manual em anexo
 *
 * Acesso: somente pelo grupo /master (auth + enforce.master).
 *
 * Por que filtrar só "dono_fazenda" + ativo?
 *   - Evita que o master mande manual pra funcionário operacional (que não usa
 *     todas as funcionalidades) ou pra usuário desativado (que não vai mais
 *     usar o sistema).
 *   - Foca no decisor: o dono é quem cobra, treina equipe e dá feedback.
 */
class ManuaisController extends Controller
{
    public function __construct(private readonly ManualBuilder $builder) {}

    /**
     * Lista de manuais disponíveis. Renderiza Inertia com o catálogo
     * e a lista de tenants ativos (necessária pro modal de envio).
     */
    public function index(): InertiaResponse
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome', 'cidade', 'estado'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'nome' => $t->nome,
                'cidade' => $t->cidade,
                'estado' => $t->estado,
            ])
            ->values();

        return Inertia::render('Master/Manuais/Index', [
            'manuais' => array_values(ManualBuilder::catalog()),
            'tenants' => $tenants,
        ]);
    }

    /**
     * Download do manual em HTML self-contained (master logado).
     *
     * O arquivo é gerado em runtime: lê o manual.html base, otimiza as imagens
     * (PNG → JPEG q=75 via GD) e embute como base64. Resultado: arquivo único
     * com ~8MB que abre em qualquer navegador, mesmo offline.
     */
    public function download(string $slug): Response
    {
        $meta = ManualBuilder::find($slug);
        abort_if(! $meta, 404, 'Manual não encontrado');

        $html = $this->builder->build($slug);
        $filename = $this->builder->filename($slug);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /**
     * Download público via signed URL (sem auth).
     *
     * Usado como fallback quando o anexo do e-mail é grande demais. O
     * recipiente clica no link do e-mail (válido 30 dias) e baixa direto.
     * O middleware `signed` valida a assinatura HMAC — sem signature válida
     * a request retorna 403.
     */
    public function downloadPublico(string $slug): Response
    {
        return $this->download($slug);
    }

    /**
     * AJAX · lista de "donos da fazenda" ATIVOS de um tenant específico.
     *
     * Filtros aplicados:
     *   - tenant_id = {tenant}
     *   - is_active = true
     *   - hasRole('dono_fazenda')
     *
     * Returns: [{ id, name, email, cargo }]
     */
    public function donos(Tenant $tenant): JsonResponse
    {
        $donos = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'dono_fazenda'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'cargo'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'cargo' => $u->cargo,
            ])
            ->values();

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
            ],
            'donos' => $donos,
        ]);
    }

    /**
     * Dispara envio do manual por e-mail.
     *
     * Validações de segurança:
     *   1. user_id existe e pertence ao tenant_id informado
     *   2. user está ATIVO (is_active = true)
     *   3. user tem role 'dono_fazenda'
     *
     * Qualquer falha → 422 com mensagem clara, sem expor dados de outros tenants.
     */
    public function enviar(Request $request, string $slug): RedirectResponse
    {
        $meta = ManualBuilder::find($slug);
        abort_if(! $meta, 404, 'Manual não encontrado');

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'mensagem' => ['nullable', 'string', 'max:1500'],
        ], [
            'tenant_id.required' => 'Selecione um cliente.',
            'user_id.required' => 'Selecione o usuário destinatário.',
        ]);

        // Garante que o user pertence ao tenant escolhido + é dono ativo.
        // Não confiamos no payload — re-valida no banco.
        $user = User::query()
            ->where('id', $data['user_id'])
            ->where('tenant_id', $data['tenant_id'])
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'dono_fazenda'))
            ->first();

        if (! $user) {
            return back()->withErrors([
                'user_id' => 'Usuário inválido: precisa ser dono da fazenda, ativo e pertencer ao cliente selecionado.',
            ])->withInput();
        }

        // Monta o HTML self-contained e decide: anexo OU link signed.
        // Limite seguro pra anexo de e-mail = 20 MB (Gmail/Outlook bloqueiam
        // > 25 MB; deixamos margem). Acima disso → link com URL assinada
        // de 30 dias, gerada via Laravel signed routes.
        try {
            $html = $this->builder->build($slug);
            $filename = $this->builder->filename($slug);
            $masterNome = $request->user()?->name;
            $tamanhoBytes = strlen($html);
            $limiteAnexoBytes = 20 * 1024 * 1024; // 20 MB

            $modo = $tamanhoBytes <= $limiteAnexoBytes ? 'anexo' : 'link';
            $downloadUrl = null;
            if ($modo === 'link') {
                $downloadUrl = URL::temporarySignedRoute(
                    'manuais.publico',
                    now()->addDays(30),
                    ['slug' => $slug],
                );
            }

            Mail::to($user->email)->send(new ManualUsuarioMail(
                destinatario: $user,
                manualTitulo: $meta['titulo'],
                manualHtml: $modo === 'anexo' ? $html : null,
                manualFilename: $filename,
                remetenteNome: $masterNome,
                mensagemPersonalizada: $data['mensagem'] ?? null,
                downloadUrl: $downloadUrl,
            ));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar manual por e-mail', [
                'manual' => $slug,
                'tenant_id' => $data['tenant_id'],
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error',
                'Não foi possível enviar o e-mail agora. Tente novamente ou reporte ao suporte.'
            )->withInput();
        }

        // Auditoria — registra no activity log que o master enviou o manual.
        activity('master')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties([
                'tenant_id' => $data['tenant_id'],
                'manual' => $slug,
                'destinatario_email' => $user->email,
                'modo' => $modo,
                'tamanho_kb' => round($tamanhoBytes / 1024),
            ])
            ->log("Master enviou manual '{$meta['titulo']}' para {$user->name} (modo: {$modo})");

        $sufixo = $modo === 'anexo'
            ? 'com manual em anexo'
            : 'com link de download (manual grande pra anexar)';

        return back()->with('success',
            "Manual enviado para {$user->name} ({$user->email}) {$sufixo}."
        );
    }
}
