<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class FinancialIndexController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('admin.financeiro.transacoes.index');
    }
}
