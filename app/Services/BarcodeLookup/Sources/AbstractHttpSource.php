<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\Contracts\BarcodeSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Base para fontes HTTP: encapsula timeout, headers padrão e helpers de resposta.
 * Subclasses apenas implementam name(), label(), isEnabled() e lookup().
 */
abstract class AbstractHttpSource implements BarcodeSource
{
    public function __construct(protected array $config = []) {}

    protected function http(int $timeoutSeconds): PendingRequest
    {
        return Http::timeout($timeoutSeconds)
            ->connectTimeout($timeoutSeconds)
            ->acceptJson()
            ->withUserAgent('Macaybas-BarcodeLookup/1.0');
    }

    /** Pega o primeiro item de uma string separada por vírgulas (ex: "Nestlé, Food" → "Nestlé"). */
    protected function firstCsv(?string $s): ?string
    {
        if ($s === null || $s === '') return null;
        $first = explode(',', $s)[0] ?? null;
        return $first ? trim($first) : null;
    }

    /** Sanitiza string garantindo string ou null. */
    protected function str(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        return is_scalar($v) ? trim((string) $v) : null;
    }
}
