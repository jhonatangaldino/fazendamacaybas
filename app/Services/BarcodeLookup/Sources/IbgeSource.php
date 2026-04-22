<?php

namespace App\Services\BarcodeLookup\Sources;

use App\Services\BarcodeLookup\Contracts\BarcodeSource;
use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * 6/11 — IBGE (enriquecimento local, sem rede).
 *
 * O IBGE não oferece API direta EAN→produto. Esta fonte faz enriquecimento offline
 * com base nos 3 primeiros dígitos do EAN-13 (prefixo GS1 = país emissor) e tabela
 * interna de NCM para produtos agro. Por si só raramente identifica o produto (não
 * retorna nome descritivo), então em geral NÃO aciona o short-circuit — serve como
 * enriquecedor quando outras fontes falharem, informando pelo menos a origem.
 *
 * Retorna ProductResult apenas quando consegue inferir algo útil como "nome" (prefixo
 * reconhecido + tipo de produto agro conhecido). Caso contrário, null.
 */
class IbgeSource implements BarcodeSource
{
    public function __construct(protected array $config = []) {}

    public function name(): string { return 'ibge'; }
    public function label(): string { return 'IBGE (origem GS1)'; }
    public function isEnabled(): bool { return (bool) ($this->config['enabled'] ?? true); }

    /** Prefixos GS1 → país. Lista compacta do range mais comum para produtos vistos no Brasil. */
    private const PREFIX_COUNTRY = [
        ['from' => 0, 'to' => 139, 'pais' => 'Estados Unidos / Canadá'],
        ['from' => 300, 'to' => 379, 'pais' => 'França'],
        ['from' => 400, 'to' => 440, 'pais' => 'Alemanha'],
        ['from' => 450, 'to' => 459, 'pais' => 'Japão'],
        ['from' => 460, 'to' => 469, 'pais' => 'Rússia'],
        ['from' => 471, 'to' => 471, 'pais' => 'Taiwan'],
        ['from' => 489, 'to' => 489, 'pais' => 'Hong Kong'],
        ['from' => 490, 'to' => 499, 'pais' => 'Japão'],
        ['from' => 500, 'to' => 509, 'pais' => 'Reino Unido'],
        ['from' => 520, 'to' => 521, 'pais' => 'Grécia'],
        ['from' => 539, 'to' => 539, 'pais' => 'Irlanda'],
        ['from' => 540, 'to' => 549, 'pais' => 'Bélgica / Luxemburgo'],
        ['from' => 560, 'to' => 560, 'pais' => 'Portugal'],
        ['from' => 570, 'to' => 579, 'pais' => 'Dinamarca'],
        ['from' => 590, 'to' => 590, 'pais' => 'Polônia'],
        ['from' => 640, 'to' => 649, 'pais' => 'Finlândia'],
        ['from' => 690, 'to' => 699, 'pais' => 'China'],
        ['from' => 729, 'to' => 729, 'pais' => 'Israel'],
        ['from' => 750, 'to' => 750, 'pais' => 'México'],
        ['from' => 754, 'to' => 755, 'pais' => 'Canadá'],
        ['from' => 759, 'to' => 759, 'pais' => 'Venezuela'],
        ['from' => 760, 'to' => 769, 'pais' => 'Suíça'],
        ['from' => 770, 'to' => 771, 'pais' => 'Colômbia'],
        ['from' => 773, 'to' => 773, 'pais' => 'Uruguai'],
        ['from' => 775, 'to' => 775, 'pais' => 'Peru'],
        ['from' => 777, 'to' => 777, 'pais' => 'Bolívia'],
        ['from' => 778, 'to' => 779, 'pais' => 'Argentina'],
        ['from' => 780, 'to' => 780, 'pais' => 'Chile'],
        ['from' => 784, 'to' => 784, 'pais' => 'Paraguai'],
        ['from' => 789, 'to' => 790, 'pais' => 'Brasil'],
        ['from' => 800, 'to' => 839, 'pais' => 'Itália'],
        ['from' => 840, 'to' => 849, 'pais' => 'Espanha'],
        ['from' => 850, 'to' => 850, 'pais' => 'Cuba'],
        ['from' => 858, 'to' => 858, 'pais' => 'Eslováquia'],
        ['from' => 859, 'to' => 859, 'pais' => 'República Tcheca'],
        ['from' => 867, 'to' => 867, 'pais' => 'Coreia do Norte'],
        ['from' => 868, 'to' => 869, 'pais' => 'Turquia'],
        ['from' => 880, 'to' => 880, 'pais' => 'Coreia do Sul'],
        ['from' => 885, 'to' => 885, 'pais' => 'Tailândia'],
        ['from' => 888, 'to' => 888, 'pais' => 'Singapura'],
        ['from' => 890, 'to' => 890, 'pais' => 'Índia'],
    ];

    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult
    {
        $origem = $this->identificarOrigem($barcode);
        if (! $origem) return null;

        // IBGE/GS1 sozinho não resolve o nome do produto. Retornamos null mesmo com origem
        // conhecida — o short-circuit não deve parar aqui. Deixamos o enriquecimento pra
        // quem chamar (ex: merge com outras fontes). Se um dia quisermos que o IBGE
        // retorne pelo menos "Produto brasileiro", troca-se o return abaixo.
        return null;

        // @codeCoverageIgnoreStart
        // Bloco preservado para futura ativação se o produto quiser identificar só pela origem:
        /** @phpstan-ignore-next-line */
        return new ProductResult(
            nome: 'Produto '.$origem,
            source: $this->label(),
            origem: $origem,
        );
        // @codeCoverageIgnoreEnd
    }

    private function identificarOrigem(string $barcode): ?string
    {
        if (! preg_match('/^\d{8,14}$/', $barcode)) return null;
        $code = ltrim($barcode, '0') ?: '0';
        $prefix3 = (int) substr(str_pad($code, 13, '0', STR_PAD_LEFT), 0, 3);
        foreach (self::PREFIX_COUNTRY as $row) {
            if ($prefix3 >= $row['from'] && $prefix3 <= $row['to']) {
                return $row['pais'];
            }
        }
        return null;
    }
}
