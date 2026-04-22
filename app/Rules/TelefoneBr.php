<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelefoneBr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitos = preg_replace('/\D/', '', (string) $value);

        if (! in_array(strlen($digitos), [10, 11], true)) {
            $fail('O campo :attribute deve ser um telefone brasileiro válido (10 ou 11 dígitos).');
        }
    }
}
