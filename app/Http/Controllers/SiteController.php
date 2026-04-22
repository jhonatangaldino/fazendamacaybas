<?php

namespace App\Http\Controllers;

use App\Mail\ContatoRecebido;
use App\Models\Cms\Page;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SiteController extends Controller
{
    public function home()
    {
        $page = Page::with(['activeSections' => fn ($q) => $q->orderBy('order_column')])
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
        ]);
    }

    public function newsletter(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Persistência futura: tabela newsletter_subscribers ou integração Mailchimp/Brevo.
        // Hoje: envia um aviso interno para o e-mail "from" do sistema.
        try {
            $email = Setting::getValue('contato.email', config('mail.from.address'));
            Mail::raw("Novo inscrito na newsletter: {$request->email}", fn ($m) => $m
                ->to($email)
                ->subject('[Macaybas] Nova inscrição na newsletter'));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Inscrição recebida! Em breve você receberá novidades.']);
    }

    public function contato(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'mensagem' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $destino = Setting::getValue('contato.email', config('mail.from.address'));
            Mail::raw(
                "Nome: {$data['nome']}\nE-mail: {$data['email']}\nTelefone: ".($data['telefone'] ?? '—')."\n\nMensagem:\n{$data['mensagem']}",
                fn ($m) => $m->to($destino)->replyTo($data['email'], $data['nome'])->subject('[Site] Nova mensagem de contato')
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Mensagem enviada. Obrigado pelo contato!');
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
