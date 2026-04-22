<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LivestockIndexController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('admin.rebanho.animais.index');
    }
}
