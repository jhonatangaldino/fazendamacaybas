<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! validarCpfStrict((string) $value)) {
            $fail('O campo :attribute deve ser um CPF válido.');
        }
    }
}
