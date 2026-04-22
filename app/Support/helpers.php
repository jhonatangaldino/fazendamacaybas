<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;

if (! function_exists('brl')) {
    /**
     * Formata um valor numérico como moeda BRL: R$ 1.234,56
     */
    function brl(int|float|string|null $valor, bool $comSimbolo = true): string
    {
        if ($valor === null || $valor === '') {
            return $comSimbolo ? 'R$ 0,00' : '0,00';
        }

        $numero = number_format((float) $valor, 2, ',', '.');

        return $comSimbolo ? 'R$ '.$numero : $numero;
    }
}

if (! function_exists('brlParse')) {
    /**
     * Converte string "R$ 1.234,56" ou "1.234,56" de volta para float 1234.56
     */
    function brlParse(?string $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        $limpo = preg_replace('/[^\d,-]/', '', $valor);
        $limpo = str_replace(',', '.', preg_replace('/\.(?=\d{3})/', '', $limpo));

        return (float) $limpo;
    }
}

if (! function_exists('dataBR')) {
    /**
     * 22/04/2026
     */
    function dataBR(CarbonInterface|string|null $data): ?string
    {
        if (! $data) {
            return null;
        }

        return Carbon::parse($data)->timezone(config('app.timezone'))->format('d/m/Y');
    }
}

if (! function_exists('dataHoraBR')) {
    /**
     * 22/04/2026 14:35
     */
    function dataHoraBR(CarbonInterface|string|null $data): ?string
    {
        if (! $data) {
            return null;
        }

        return Carbon::parse($data)->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }
}

if (! function_exists('dataHoraBRCompleta')) {
    /**
     * 22/04/2026 14:35:07
     */
    function dataHoraBRCompleta(CarbonInterface|string|null $data): ?string
    {
        if (! $data) {
            return null;
        }

        return Carbon::parse($data)->timezone(config('app.timezone'))->format('d/m/Y H:i:s');
    }
}

if (! function_exists('parseDataBR')) {
    /**
     * Converte "22/04/2026" ou "22/04/2026 14:35" para Carbon.
     */
    function parseDataBR(?string $data): ?Carbon
    {
        if (! $data) {
            return null;
        }

        $formatos = ['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'];
        foreach ($formatos as $formato) {
            try {
                return Carbon::createFromFormat($formato, $data, config('app.timezone'));
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }
}

if (! function_exists('cpfMask')) {
    /**
     * 000.000.000-00
     */
    function cpfMask(?string $cpf): ?string
    {
        if (! $cpf) {
            return null;
        }

        $cpf = preg_replace('/\D/', '', $cpf);
        $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);

        return substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);
    }
}

if (! function_exists('cnpjMask')) {
    /**
     * 00.000.000/0000-00
     */
    function cnpjMask(?string $cnpj): ?string
    {
        if (! $cnpj) {
            return null;
        }

        $cnpj = preg_replace('/\D/', '', $cnpj);
        $cnpj = str_pad($cnpj, 14, '0', STR_PAD_LEFT);

        return substr($cnpj, 0, 2).'.'.substr($cnpj, 2, 3).'.'.substr($cnpj, 5, 3).'/'.substr($cnpj, 8, 4).'-'.substr($cnpj, 12, 2);
    }
}

if (! function_exists('cpfCnpjMask')) {
    /**
     * Aplica máscara de CPF ou CNPJ conforme a quantidade de dígitos.
     */
    function cpfCnpjMask(?string $doc): ?string
    {
        if (! $doc) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $doc);

        return strlen($digitos) === 14 ? cnpjMask($digitos) : cpfMask($digitos);
    }
}

if (! function_exists('telefoneMask')) {
    /**
     * (31) 99999-9999 ou (31) 9999-9999
     */
    function telefoneMask(?string $tel): ?string
    {
        if (! $tel) {
            return null;
        }

        $tel = preg_replace('/\D/', '', $tel);

        if (strlen($tel) === 11) {
            return '('.substr($tel, 0, 2).') '.substr($tel, 2, 5).'-'.substr($tel, 7, 4);
        }

        if (strlen($tel) === 10) {
            return '('.substr($tel, 0, 2).') '.substr($tel, 2, 4).'-'.substr($tel, 6, 4);
        }

        return $tel;
    }
}

if (! function_exists('cepMask')) {
    /**
     * 00000-000
     */
    function cepMask(?string $cep): ?string
    {
        if (! $cep) {
            return null;
        }

        $cep = preg_replace('/\D/', '', $cep);
        $cep = str_pad($cep, 8, '0', STR_PAD_LEFT);

        return substr($cep, 0, 5).'-'.substr($cep, 5, 3);
    }
}

if (! function_exists('placaMask')) {
    /**
     * AAA-9999 ou AAA9A99 (Mercosul)
     */
    function placaMask(?string $placa): ?string
    {
        if (! $placa) {
            return null;
        }

        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));

        if (strlen($placa) !== 7) {
            return $placa;
        }

        // Mercosul: ABC1D23
        if (ctype_alpha($placa[4])) {
            return $placa;
        }

        // Antiga: ABC-1234
        return substr($placa, 0, 3).'-'.substr($placa, 3, 4);
    }
}

if (! function_exists('validarCpf')) {
    function validarCpf(?string $cpf): bool
    {
        if (! $cpf) {
            return false;
        }

        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('validarCnpj')) {
    function validarCnpj(?string $cnpj): bool
    {
        if (! $cnpj) {
            return false;
        }

        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += (int) $cnpj[$i] * $pesos1[$i];
        }
        $d1 = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);

        if ((int) $cnpj[12] !== $d1) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += (int) $cnpj[$i] * $pesos2[$i];
        }
        $d2 = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);

        return (int) $cnpj[13] === $d2;
    }
}

if (! function_exists('apenasDigitos')) {
    function apenasDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }
}
