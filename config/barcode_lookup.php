<?php

use App\Services\BarcodeLookup\Sources\BarcodeLookupComSource;
use App\Services\BarcodeLookup\Sources\CosmosSource;
use App\Services\BarcodeLookup\Sources\FastfetchSource;
use App\Services\BarcodeLookup\Sources\GodanSource;
use App\Services\BarcodeLookup\Sources\GoUpcSource;
use App\Services\BarcodeLookup\Sources\Gs1BrasilSource;
use App\Services\BarcodeLookup\Sources\IbgeSource;
use App\Services\BarcodeLookup\Sources\LocalDatabaseSource;
use App\Services\BarcodeLookup\Sources\OpenFoodFactsSource;
use App\Services\BarcodeLookup\Sources\UpcDatabaseSource;
use App\Services\BarcodeLookup\Sources\UpcItemDbSource;

/*
 * Configuração da cadeia de fallback para lookup de produto por código de barras.
 *
 * A ordem do array define a sequência de consulta. O ProductLookupService executa
 * cada fonte até obter um resultado com nome válido (short-circuit).
 *
 * Timeout: 2s por fonte (config.timeout_seconds)
 * Cada fonte respeita sua própria chave 'enabled' (permite desligar sem remover código).
 * Variáveis sensíveis (tokens/keys) vêm do .env — nunca hard-coded.
 */
return [

    'timeout_seconds' => (int) env('BARCODE_LOOKUP_TIMEOUT', 2),

    // Cache de resultados "não encontrado" por N segundos pra evitar re-consulta cara
    'negative_cache_ttl' => (int) env('BARCODE_LOOKUP_NEGATIVE_TTL', 3600),

    /*
     * Ordem de fallback (obrigatória por requisito de produto):
     *   1. Local  → 2. OFF  → 3. GODAN  → 4. Fastfetch  → 5. UPCItemDB
     *   → 6. IBGE → 7. Cosmos → 8. GS1 Brasil → 9. GoUPC → 10. UPCDatabase → 11. Barcode Lookup
     */
    'sources' => [

        'local' => [
            'class' => LocalDatabaseSource::class,
            'enabled' => env('BARCODE_SOURCE_LOCAL', true),
        ],

        'openfoodfacts' => [
            'class' => OpenFoodFactsSource::class,
            'enabled' => env('BARCODE_SOURCE_OFF', true),
            'endpoint' => 'https://world.openfoodfacts.org/api/v2/product',
        ],

        'godan' => [
            'class' => GodanSource::class,
            // GODAN não é uma API única: é uma rede de datasets agrícolas abertos.
            // Configure BARCODE_GODAN_ENDPOINT apontando para o dataset que sua fazenda usa
            // (ex: endpoint municipal, sindicato rural, cooperativa). Desligada se não setar.
            'enabled' => (bool) env('BARCODE_GODAN_ENDPOINT'),
            'endpoint' => env('BARCODE_GODAN_ENDPOINT'),
            'token' => env('BARCODE_GODAN_TOKEN'),
        ],

        'fastfetch' => [
            'class' => FastfetchSource::class,
            // Fastfetch é um wrapper configurável — aponte BARCODE_FASTFETCH_URL para um serviço
            // interno/privado (ex: lambda da cooperativa com cache próprio).
            'enabled' => (bool) env('BARCODE_FASTFETCH_URL'),
            'endpoint' => env('BARCODE_FASTFETCH_URL'),
            'token' => env('BARCODE_FASTFETCH_TOKEN'),
        ],

        'upcitemdb' => [
            'class' => UpcItemDbSource::class,
            'enabled' => env('BARCODE_SOURCE_UPCITEMDB', true),
            // Se BARCODE_UPCITEMDB_KEY vazio, usa o endpoint trial (limite 100/dia)
            'endpoint_trial' => 'https://api.upcitemdb.com/prod/trial/lookup',
            'endpoint_paid' => 'https://api.upcitemdb.com/prod/v1/lookup',
            'key' => env('BARCODE_UPCITEMDB_KEY'),
        ],

        'ibge' => [
            'class' => IbgeSource::class,
            // IBGE não tem API direta de EAN→produto. A fonte serve pra enriquecer
            // com origem (prefixo do EAN) + NCM quando o código já traz essa info.
            // Sempre ativa (funciona offline).
            'enabled' => env('BARCODE_SOURCE_IBGE', true),
        ],

        'cosmos' => [
            'class' => CosmosSource::class,
            'enabled' => (bool) env('BARCODE_COSMOS_TOKEN'),
            'endpoint' => 'https://api.cosmos.bluesoft.com.br/gtins',
            'token' => env('BARCODE_COSMOS_TOKEN'),
        ],

        'gs1brasil' => [
            'class' => Gs1BrasilSource::class,
            'enabled' => (bool) env('BARCODE_GS1_TOKEN'),
            'endpoint' => env('BARCODE_GS1_ENDPOINT', 'https://api.gs1br.org/v2/produtos'),
            'token' => env('BARCODE_GS1_TOKEN'),
        ],

        'goupc' => [
            'class' => GoUpcSource::class,
            'enabled' => (bool) env('BARCODE_GOUPC_KEY'),
            'endpoint' => 'https://go-upc.com/api/v1/code',
            'key' => env('BARCODE_GOUPC_KEY'),
        ],

        'upcdatabase' => [
            'class' => UpcDatabaseSource::class,
            'enabled' => (bool) env('BARCODE_UPCDB_KEY'),
            'endpoint' => 'https://api.upcdatabase.org/product',
            'key' => env('BARCODE_UPCDB_KEY'),
        ],

        'barcodelookup' => [
            'class' => BarcodeLookupComSource::class,
            'enabled' => (bool) env('BARCODE_LOOKUP_KEY'),
            'endpoint' => 'https://api.barcodelookup.com/v3/products',
            'key' => env('BARCODE_LOOKUP_KEY'),
        ],
    ],
];
