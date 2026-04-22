<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cep implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitos = preg_replace('/\D/', '', (string) $value);

        if (strlen($digitos) !== 8) {
            $fail('O campo :attribute deve ser um CEP válido (8 dígitos).');
        }
    }
}
