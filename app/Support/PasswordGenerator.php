<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * PasswordGenerator — gera senhas temporárias previsivelmente legíveis.
 *
 * Padrão Macaybas: 8 caracteres alfanuméricos sem ambíguos
 * (sem 0/O, 1/l/I) — fácil de digitar no celular sem confundir.
 *
 * Usa random_int (CSPRNG) para garantir aleatoriedade criptográfica.
 *
 *   $senha = PasswordGenerator::make();           // 8 chars padrão
 *   $senha = PasswordGenerator::make(12);         // tamanho customizado
 *   $senha = PasswordGenerator::make(8, false);   // sem misturar maiúsculas
 */
class PasswordGenerator
{
    /**
     * Caracteres permitidos (sem ambíguos):
     *   - Letras maiúsculas: ABCDEFGHJKLMNPQRSTUVWXYZ (sem I, O)
     *   - Letras minúsculas: abcdefghjkmnpqrstuvwxyz (sem i, l, o)
     *   - Dígitos: 23456789 (sem 0, 1)
     */
    private const CHARS_MIXED = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    private const CHARS_UPPER_ONLY = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function make(int $length = 8, bool $mixedCase = true): string
    {
        if ($length < 4) {
            throw new \InvalidArgumentException('Senha temporária deve ter no mínimo 4 caracteres.');
        }

        $chars = $mixedCase ? self::CHARS_MIXED : self::CHARS_UPPER_ONLY;
        $max = strlen($chars) - 1;

        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }

        return $out;
    }
}
