<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::orderBy('group')->orderBy('order_column')->get()
            ->groupBy('group');

        return Inertia::render('Admin/Cms/Settings', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($data['settings'] as $item) {
            Setting::where('key', $item['key'])->update(['value' => $item['value']]);
            Cache::forget("setting.{$item['key']}");
        }

        return back()->with('success', 'Configurações atualizadas.');
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'max:2048'],
            'key' => ['required', 'string', 'exists:settings,key'],
        ]);

        $path = $request->file('file')->store('settings', 'public');

        Setting::where('key', $request->key)->update(['value' => $path]);
        Cache::forget("setting.{$request->key}");

        return back()->with('success', 'Imagem atualizada.');
    }
}
