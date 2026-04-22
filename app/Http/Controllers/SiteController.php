<?php

namespace App\Http\Controllers;

use App\Models\Cms\Page;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function home()
    {
        $page = Page::with(['activeSections' => fn ($q) => $q->orderBy('order_column')])
            ->where('slug', 'home')
            ->where('is_published', true)
            ->firstOrFail();

        return view('site.home', [
            'sections' => $page->activeSections->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->type,
                'data' => $s->dataForPublic(),
            ])->all(),
            'meta' => [
                'title' => $page->meta_title ?: $page->titulo,
                'description' => $page->meta_description ?: Setting::getValue('seo.default_description'),
                'keywords' => $page->meta_keywords,
                'og_image' => $page->og_image_path ? asset('storage/'.$page->og_image_path) : null,
            ],
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev',
            'time' => now()->format('d/m/Y H:i:s'),
        ]);
    }
}
