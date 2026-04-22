<?php

/**
 * Validadores brasileiros com suporte a CNPJ alfanumérico (Resolução CGSIM 2026+).
 * Os helpers aqui podem ser usados tanto pelas Rules quanto por qualquer outro ponto
 * do backend. Frontend tem versão paralela em resources/js/utils/br-validators.js.
 */

if (! function_exists('apenasDigitos')) {
    function apenasDigitos(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }
}

if (! function_exists('apenasAlfaNum')) {
    function apenasAlfaNum(?string $v): string
    {
        return preg_replace('/[^0-9A-Z]/', '', strtoupper((string) $v));
    }
}

if (! function_exists('validarCpfStrict')) {
    function validarCpfStrict(?string $v): bool
    {
        $d = apenasDigitos($v);
        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($c = 0; $c < $t; $c++) {
                $sum += (int) $d[$c] * (($t + 1) - $c);
            }
            $dv = ((10 * $sum) % 11) % 10;
            if ((int) $d[$c] !== $dv) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('cnpjCalcDv')) {
    /**
     * Calcula um DV do CNPJ (alfanumérico-aware).
     * Cada caractere vale (charCodeAt - 48): '0'=0...'9'=9, 'A'=17, 'B'=18, etc.
     */
    function cnpjCalcDv(string $body, array $pesos): int
    {
        $soma = 0;
        $n = strlen($body);
        for ($i = 0; $i < $n; $i++) {
            $v = ord($body[$i]) - 48;
            $soma += $v * $pesos[$i];
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}

if (! function_exists('validarCnpjStrict')) {
    /**
     * Aceita CNPJ numérico tradicional OU alfanumérico (corpo com letras, DV sempre numérico).
     */
    function validarCnpjStrict(?string $v): bool
    {
        $s = apenasAlfaNum($v);
        if (strlen($s) !== 14) {
            return false;
        }

        $corpo = substr($s, 0, 12);
        $dv = substr($s, 12);

        if (! preg_match('/^\d{2}$/', $dv)) {
            return false;
        }
        if (preg_match('/^(.)\1{13}$/', $s)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $dv1 = cnpjCalcDv($corpo, $pesos1);
        if ($dv1 !== (int) $dv[0]) {
            return false;
        }
        $dv2 = cnpjCalcDv($corpo.$dv[0], $pesos2);

        return $dv2 === (int) $dv[1];
    }
}

if (! function_exists('validarCpfCnpjStrict')) {
    function validarCpfCnpjStrict(?string $v): bool
    {
        $d = apenasAlfaNum($v);
        if (strlen($d) === 11) {
            return validarCpfStrict($d);
        }
        if (strlen($d) === 14) {
            return validarCnpjStrict($d);
        }

        return false;
    }
}
