<?php

namespace App\Domain\Cms\Support;

use App\Models\Setting;

/**
 * MapResolver — resolve o bloco de mapa da landing pública.
 *
 * Respeita o contexto de `app('tenant_id')` implicitamente: Setting::getValue()
 * faz fallback tenant → global → default. Quando nenhuma das 3 fontes de dados
 * está preenchida, retorna null para que a view oculte o bloco inteiro.
 *
 * Prioridade (em ordem):
 *   1. landing.map.google_embed    — iframe completo OU URL do Google Maps
 *                                    (se for HTML completo, só o src é extraído,
 *                                    sanitizando contra scripts embutidos)
 *   2. landing.map.latitude + longitude — gera iframe por coordenadas
 *   3. landing.map.endereco        — gera iframe por endereço textual
 *                                    (fallback adicional: legacy `contato.endereco`)
 *   4. tudo vazio                  — retorna null (bloco é ocultado)
 *
 * Retorno:
 *   null | ['iframe_src' => string, 'nome_local' => ?string]
 *
 * Nunca retorna HTML cru — sempre URL de iframe — para evitar XSS mesmo quando
 * o master colou um `<iframe>` completo no embed.
 */
class MapResolver
{
    /**
     * @return array{iframe_src: string, nome_local: ?string}|null
     */
    public static function resolve(): ?array
    {
        $nomeLocal = self::str(Setting::getValue('landing.map.nome_local'));

        // 1) Embed manual — iframe completo ou URL direta
        $embed = self::str(Setting::getValue('landing.map.google_embed'));
        if ($embed !== '') {
            $src = self::extractIframeSrc($embed);
            if ($src !== null) {
                return ['iframe_src' => $src, 'nome_local' => $nomeLocal ?: null];
            }
        }

        // 2) Latitude + longitude
        $lat = self::str(Setting::getValue('landing.map.latitude'));
        $lng = self::str(Setting::getValue('landing.map.longitude'));
        if ($lat !== '' && $lng !== '') {
            $latNum = (float) str_replace(',', '.', $lat);
            $lngNum = (float) str_replace(',', '.', $lng);

            return [
                'iframe_src' => "https://maps.google.com/maps?q={$latNum},{$lngNum}&z=16&hl=pt-BR&output=embed",
                'nome_local' => $nomeLocal ?: null,
            ];
        }

        // 3) Endereço textual — SOMENTE landing.map.endereco, respeitando
        //    a prioridade do brief. O tenant 1 (Macaybas) recebe esse valor
        //    via migration de backfill (2024_02_05_000003).
        $endereco = self::str(Setting::getValue('landing.map.endereco'));
        if ($endereco !== '') {
            $enc = urlencode($endereco);

            return [
                'iframe_src' => "https://maps.google.com/maps?q={$enc}&z=14&hl=pt-BR&output=embed",
                'nome_local' => $nomeLocal ?: $endereco,
            ];
        }

        // 4) Nenhuma das fontes preenchidas
        return null;
    }

    /**
     * Extrai o atributo `src` de um iframe completo. Se o input já for uma URL
     * http(s) pura, retorna como está. Qualquer outro conteúdo → null (inválido).
     * Isso evita renderização de HTML arbitrário.
     */
    private static function extractIframeSrc(string $raw): ?string
    {
        // Caso 1: usuário colou o bloco <iframe ... src="..."></iframe>
        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $raw, $m)) {
            $src = trim($m[1]);
            if (self::isValidHttpUrl($src)) {
                return $src;
            }
        }

        // Caso 2: usuário colou só a URL
        if (self::isValidHttpUrl($raw)) {
            return $raw;
        }

        return null;
    }

    private static function isValidHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }

    private static function str(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
