<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Mail\ManualUsuarioMail;
use App\Models\ManualEnvio;
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

        // Histórico de envios (últimos 50) com tracking de abertura
        $envios = ManualEnvio::with(['recipient:id,name,email', 'tenant:id,nome'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'manual_slug' => $e->manual_slug,
                'manual_titulo' => ManualBuilder::find($e->manual_slug)['titulo'] ?? $e->manual_slug,
                'tenant' => ['id' => $e->tenant_id, 'nome' => $e->tenant?->nome ?? '(removido)'],
                'recipient' => [
                    'id' => $e->recipient_id,
                    'name' => $e->recipient?->name ?? '(removido)',
                    'email' => $e->recipient_email,
                ],
                'modo' => $e->modo,
                'tamanho_kb' => $e->tamanho_kb,
                'sent_at' => $e->created_at?->toIso8601String(),
                'sent_at_human' => $e->created_at?->setTimezone('America/Sao_Paulo')?->format('d/m/Y H:i'),
                'opened_at' => $e->opened_at?->toIso8601String(),
                'opened_at_human' => $e->opened_at?->setTimezone('America/Sao_Paulo')?->format('d/m/Y H:i'),
                'open_count' => $e->open_count,
                'first_open_ip' => $e->first_open_ip,
                'aberto' => $e->open_count > 0,
            ])->values();

        return Inertia::render('Master/Manuais/Index', [
            'manuais' => array_values(ManualBuilder::catalog()),
            'tenants' => $tenants,
            'envios' => $envios,
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
     * UTM tracking: a URL contém um token único (parâmetro ?t=) que mapeia
     * pra um registro em manual_envios. Quando o destinatário clica:
     *   - Validamos o token contra DB
     *   - Marcamos opened_at, incrementamos open_count
     *   - Registramos IP + User-Agent
     *
     * O middleware `signed` valida que a URL não foi adulterada (HMAC).
     */
    public function downloadPublico(Request $request, string $slug): Response
    {
        $token = $request->query('t');
        if ($token) {
            // Match no DB → marca como aberto (mesmo se for re-clique)
            $envio = ManualEnvio::where('token', $token)
                ->where('manual_slug', $slug)
                ->first();
            if ($envio) {
                $envio->markOpened(
                    ip: $request->ip() ?? '0.0.0.0',
                    userAgent: $request->userAgent(),
                );
            }
        }

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

        // Bloqueia envio de manuais marcados como NÃO enviáveis (ex.: Manual
        // do Master, que tem detalhes internos não destinados a clientes).
        if (empty($meta['enviavel'])) {
            return back()->with('error',
                'Este manual é de uso interno e não pode ser enviado a clientes. Apenas download.'
            );
        }

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

        // ESTRATÉGIA: sempre LINK (sem anexo). Por que?
        //   1. E-mail leve (~5 KB) renderiza estilizado em qualquer mobile
        //      (Outlook/Hotmail entram em "plain-text mode" pra anexos > 1 MB)
        //   2. Link assinado dá tracking: sabemos quando o cliente abriu
        //   3. Link signed expira em 30 dias — segurança natural
        // O anexo continua disponível via "Baixar" (download direto pelo master).
        try {
            $html = $this->builder->build($slug);
            $filename = $this->builder->filename($slug);
            $masterNome = $request->user()?->name;
            $tamanhoKb = (int) round(strlen($html) / 1024);

            // 1. Cria registro de envio com token único pra rastreamento
            $envio = ManualEnvio::create([
                'token' => ManualEnvio::generateToken(),
                'manual_slug' => $slug,
                'sender_id' => $request->user()->id,
                'tenant_id' => $data['tenant_id'],
                'recipient_id' => $user->id,
                'recipient_email' => $user->email,
                'modo' => 'link',
                'tamanho_kb' => $tamanhoKb,
                'mensagem' => $data['mensagem'] ?? null,
            ]);

            // 2. Gera URL signed COM o token embutido (?t=xxx). Quando o
            //    destinatário clica, downloadPublico() valida + marca opened.
            $downloadUrl = URL::temporarySignedRoute(
                'manuais.publico',
                now()->addDays(30),
                ['slug' => $slug, 't' => $envio->token],
            );

            $modo = 'link';

            Mail::to($user->email)->send(new ManualUsuarioMail(
                destinatario: $user,
                manualTitulo: $meta['titulo'],
                manualHtml: null, // SEMPRE link, nunca anexo
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
                'tamanho_kb' => $tamanhoKb,
                'envio_id' => $envio->id,
                'token' => $envio->token,
            ])
            ->log("Master enviou manual '{$meta['titulo']}' para {$user->name} (link signed)");

        return back()->with('success',
            "Manual enviado para {$user->name} ({$user->email}) com link de download (válido 30 dias)."
        );
    }
}
