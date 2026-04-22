<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! validarCnpjStrict((string) $value)) {
            $fail('O campo :attribute deve ser um CNPJ válido.');
        }
    }
}
