<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
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

    /**
     * Upload de imagem (logo, favicon, etc.).
     * Aceita jpg, jpeg, png, gif, webp, svg, ico. Até 5MB.
     * Retorna JSON com o novo path/url pra o front atualizar em tempo real.
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => [
                'required',
                'file',
                'max:5120', // 5MB
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/x-icon,image/vnd.microsoft.icon',
            ],
            'key' => ['required', 'string', 'exists:settings,key'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first('file') ?: 'Falha no upload.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) ?: $file->guessExtension();
        $filename = \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            .'-'.now()->format('YmdHis')
            .'.'.$ext;

        $path = $file->storeAs('settings', $filename, 'public');

        // Se existia imagem anterior, apaga para não acumular
        $setting = Setting::where('key', $data['key'])->first();
        if ($setting && $setting->value && Storage::disk('public')->exists($setting->value)) {
            Storage::disk('public')->delete($setting->value);
        }

        Setting::where('key', $data['key'])->update(['value' => $path]);
        Cache::forget("setting.{$data['key']}");

        return response()->json([
            'ok' => true,
            'message' => 'Imagem atualizada.',
            'key' => $data['key'],
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Remove uma imagem do setting.
     */
    public function removeFile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'exists:settings,key'],
        ]);

        $setting = Setting::where('key', $data['key'])->first();
        if ($setting && $setting->value && Storage::disk('public')->exists($setting->value)) {
            Storage::disk('public')->delete($setting->value);
        }

        Setting::where('key', $data['key'])->update(['value' => null]);
        Cache::forget("setting.{$data['key']}");

        return response()->json([
            'ok' => true,
            'message' => 'Imagem removida.',
            'key' => $data['key'],
        ]);
    }
}
