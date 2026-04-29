<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * ManualBuilder
 *
 * Lê o manual HTML em `manual/manual-fazenda-macaybas.html` e gera uma versão
 * "self-contained" com TODAS as imagens (`screenshots/...`) convertidas pra
 * data URI base64, num único arquivo .html que abre em qualquer navegador
 * sem dependências externas.
 *
 * Uso: download direto OU anexo de e-mail.
 *
 * Por que não PDF? O manual usa CSS moderno (gradients, grid, sombras, mockups
 * em CSS puro, comparações lado a lado). DomPDF e bibliotecas pure-PHP têm
 * suporte CSS limitado e o resultado fica feio. HTML self-contained preserva
 * fidelidade visual e o usuário pode imprimir/exportar PDF do navegador (já
 * tem @media print configurado).
 */
class ManualBuilder
{
    public const MANUAL_USUARIO = 'manual-fazenda-macaybas';
    public const MANUAL_MASTER = 'manual-master';

    /**
     * Catálogo de manuais disponíveis.
     *
     * - MANUAL_USUARIO: pro dono e equipe da fazenda (cliente final).
     *   ENVIÁVEL pelo master via /master/manuais → Enviar manual.
     *
     * - MANUAL_MASTER: documentação interna da equipe que opera a plataforma
     *   (impersonação, billing, auditoria). NÃO ENVIÁVEL pra clientes.
     *   Apenas download direto pelo master pra estudo interno.
     */
    public static function catalog(): array
    {
        return [
            self::MANUAL_USUARIO => [
                'slug' => self::MANUAL_USUARIO,
                'titulo' => 'Manual do Usuário',
                'descricao' => 'Manual completo pro dono e equipe da fazenda. Cobre todas as telas, ações, perfis e fluxos do dia a dia. Pode ser enviado por e-mail aos clientes.',
                'paginas_aprox' => 65,
                'tamanho_aprox_mb' => 12,
                'publico' => 'Dono da fazenda + funcionários',
                'arquivo_base' => 'manual-fazenda-macaybas.html',
                'enviavel' => true,
            ],
            self::MANUAL_MASTER => [
                'slug' => self::MANUAL_MASTER,
                'titulo' => 'Manual do Master',
                'descricao' => 'Documentação interna da equipe que opera a plataforma. Cobre clientes, planos, cobranças, auditoria, CMS, impersonação. Apenas download — nunca compartilhar com clientes.',
                'paginas_aprox' => 24,
                'tamanho_aprox_mb' => 4,
                'publico' => 'Equipe Master da plataforma (uso interno)',
                'arquivo_base' => 'manual-master.html',
                'enviavel' => false,
            ],
        ];
    }

    /**
     * Retorna meta de um manual pelo slug. null se inexistente.
     */
    public static function find(string $slug): ?array
    {
        return self::catalog()[$slug] ?? null;
    }

    /**
     * Caminho absoluto da pasta `manual/`.
     */
    public static function manualDir(): string
    {
        return base_path('manual');
    }

    /**
     * Caminho absoluto do arquivo HTML base.
     */
    public static function manualPath(string $slug): string
    {
        $meta = self::find($slug);
        if (! $meta) {
            throw new RuntimeException("Manual '{$slug}' não existe.");
        }
        return self::manualDir().DIRECTORY_SEPARATOR.$meta['arquivo_base'];
    }

    /**
     * Gera o HTML self-contained: substitui `<img src="screenshots/...">`
     * por `<img src="data:image/jpeg;base64,...">` com PNG re-comprimido
     * para JPEG (quality 75) — reduz tamanho do anexo de ~45 MB para ~8 MB,
     * cabendo no limite de 25 MB do Gmail/Outlook.
     *
     * Re-encoding usa GD (extensão PHP padrão, presente no Hostinger Business).
     * Se GD indisponível, faz fallback pra base64 do PNG original.
     *
     * Cacheia em runtime — chamadas repetidas no mesmo processo retornam
     * o mesmo conteúdo sem reprocessar.
     */
    public function build(string $slug): string
    {
        static $cache = [];
        if (isset($cache[$slug])) {
            return $cache[$slug];
        }

        $path = self::manualPath($slug);
        if (! File::exists($path)) {
            throw new RuntimeException("Arquivo do manual não encontrado: {$path}");
        }

        $html = File::get($path);
        $manualDir = self::manualDir();
        $hasGd = extension_loaded('gd') && function_exists('imagecreatefrompng');

        // Substitui src="screenshots/..." por data URI.
        // Aceita aspas duplas e simples; só PNG/JPG/GIF/WEBP/SVG.
        $html = preg_replace_callback(
            '/src=(["\'])(screenshots\/[^"\']+\.(png|jpg|jpeg|gif|webp|svg))(\1)/i',
            function ($m) use ($manualDir, $hasGd) {
                $relPath = $m[2];
                $absPath = $manualDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relPath);
                if (! File::exists($absPath)) {
                    // Imagem ausente → mantém placeholder textual (não quebra layout)
                    return 'src="data:image/svg+xml;utf8,'.rawurlencode(
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200"><rect width="400" height="200" fill="#f1f5f9"/><text x="50%" y="50%" font-family="sans-serif" font-size="14" fill="#94a3b8" text-anchor="middle" dominant-baseline="middle">Imagem indisponível</text></svg>'
                    ).'"';
                }

                $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

                // PNG/JPG → re-encode pra JPEG quality 75 via GD.
                // SVG/GIF/WEBP → mantém formato original (não vale a pena converter).
                if ($hasGd && in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
                    $optimized = $this->optimizeAsJpeg($absPath, $ext);
                    if ($optimized !== null) {
                        return 'src="data:image/jpeg;base64,'.base64_encode($optimized).'"';
                    }
                    // Falha do GD → cai pro fallback abaixo
                }

                $mime = match ($ext) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    default => 'application/octet-stream',
                };

                $b64 = base64_encode(File::get($absPath));
                return 'src="data:'.$mime.';base64,'.$b64.'"';
            },
            $html
        );

        $cache[$slug] = $html;
        return $html;
    }

    /**
     * Re-encoda imagem (PNG ou JPG) como JPEG quality 75 usando GD.
     * Retorna bytes do JPEG, ou null se falhar.
     *
     * Por que JPEG q=75? Redução típica de ~80% em screenshots de UI sem
     * perda perceptível. Mantém a leitura clara dos textos do screenshot.
     */
    private function optimizeAsJpeg(string $absPath, string $ext): ?string
    {
        try {
            $img = match ($ext) {
                'png' => @imagecreatefrompng($absPath),
                'jpg', 'jpeg' => @imagecreatefromjpeg($absPath),
                default => null,
            };
            if (! $img) return null;

            // Pra PNGs com transparência: achata sobre fundo branco
            // (JPEG não suporta alpha; sem isso ficaria fundo preto).
            if ($ext === 'png') {
                $w = imagesx($img);
                $h = imagesy($img);
                $bg = imagecreatetruecolor($w, $h);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);
                imagedestroy($img);
                $img = $bg;
            }

            ob_start();
            imagejpeg($img, null, 75);
            $bytes = ob_get_clean();
            imagedestroy($img);
            return $bytes !== false ? $bytes : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Nome do arquivo pra download/anexo (sem path).
     */
    public function filename(string $slug): string
    {
        $meta = self::find($slug);
        $base = $meta['arquivo_base'] ?? "{$slug}.html";
        return $base;
    }
}
