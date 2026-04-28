<?php

namespace App\Domain\Livestock;

use App\Models\Livestock\Animal;

/**
 * Tabela DROVET — Crescimento de fêmeas leiteiras para parição aos 27 meses.
 *
 * Fonte: tabela técnica DROVET+ (Medicina Veterinária & Agropecuária Planejada).
 * Dá o peso-alvo (kg) por idade-meses × tamanho da raça, do nascimento aos 27
 * meses (parição). Aos 18 meses entra a faixa de cobrição.
 *
 * Tamanhos:
 *   - Grande:  Holandês, Pardo Suíço          → 40kg ao nascer, 580kg aos 27m
 *   - Média:   Girolando, Jersolando          → 35kg ao nascer, 510kg aos 27m
 *   - Pequena: Jersey                          → 30kg ao nascer, 450kg aos 27m
 *
 * REGRA CRÍTICA (OBS DROVET): Entre 8 e 12 meses (fase de puberdade), as fêmeas
 * NÃO DEVEM estar com peso MAIOR que o valor referência. Se estiverem, são
 * consideradas gordas e provavelmente estão acumulando gordura no aparelho
 * reprodutivo e mamário — o que compromete a vida útil produtiva.
 *
 * Por isso a avaliação tem comportamento ASSIMÉTRICO:
 *   - Antes da puberdade (≤ 7m):     abaixo do alvo é problema, acima é OK
 *   - Puberdade (8-12m, FASE CRÍTICA): acima do alvo é PROBLEMA grave
 *   - Após puberdade (13-27m):       abaixo do alvo é problema, acima é OK
 *
 * Esta classe é stateless (constants + métodos puros). Pode ser usada em
 * qualquer ponto do app sem instanciar.
 */
class DairyHeiferGrowthTable
{
    /**
     * Peso-alvo (kg) por idade-meses [0..27] × tamanho da raça.
     */
    public const PESOS = [
        'grande' => [
            0 => 40, 1 => 55, 2 => 73, 3 => 91, 4 => 109, 5 => 127, 6 => 145,
            7 => 163, 8 => 181, 9 => 199, 10 => 217, 11 => 235, 12 => 253,
            13 => 263, 14 => 275, 15 => 289, 16 => 310, 17 => 331, 18 => 352,
            19 => 376, 20 => 400, 21 => 424, 22 => 448, 23 => 472, 24 => 496,
            25 => 524, 26 => 552, 27 => 580,
        ],
        'media' => [
            0 => 35, 1 => 48, 2 => 64, 3 => 80, 4 => 95, 5 => 111, 6 => 127,
            7 => 143, 8 => 158, 9 => 174, 10 => 190, 11 => 205, 12 => 221,
            13 => 233, 14 => 246, 15 => 259, 16 => 277, 17 => 294, 18 => 310,
            19 => 330, 20 => 350, 21 => 369, 22 => 389, 23 => 413, 24 => 437,
            25 => 463, 26 => 489, 27 => 510,
        ],
        'pequena' => [
            0 => 30, 1 => 42, 2 => 55, 3 => 69, 4 => 82, 5 => 96, 6 => 109,
            7 => 123, 8 => 136, 9 => 150, 10 => 163, 11 => 176, 12 => 190,
            13 => 204, 14 => 217, 15 => 230, 16 => 244, 17 => 257, 18 => 268,
            19 => 284, 20 => 300, 21 => 315, 22 => 330, 23 => 354, 24 => 378,
            25 => 402, 26 => 426, 27 => 450,
        ],
    ];

    /**
     * Faixa de cobrição (peso mínimo–máximo) aos 18 meses por tamanho.
     * DROVET: cobrição feita FORA dessa faixa = sub-condicionamento ou obesidade.
     */
    public const COBRICAO_RANGE = [
        'grande'  => [340, 360],
        'media'   => [300, 320],
        'pequena' => [260, 280],
    ];

    /**
     * Mapeamento normalizado raça → tamanho.
     * Chaves em lowercase ASCII (sem acento) — a função normalizeBreedName aplica.
     */
    public const BREED_SIZE = [
        'holandes'      => 'grande',
        'holandesa'     => 'grande',
        'pardo suico'   => 'grande',
        'pardo-suico'   => 'grande',
        'girolando'     => 'media',
        'jersolando'    => 'media',
        'jersey'        => 'pequena',
    ];

    /**
     * Fases biológicas (intervalos em meses).
     */
    public const FASE_PRE_PUBERDADE   = [0, 7];
    public const FASE_PUBERDADE_CRIT  = [8, 12];
    public const FASE_POS_PUBERDADE   = [13, 17];
    public const FASE_COBRICAO        = [18, 18];
    public const FASE_GESTACAO        = [19, 26];
    public const FASE_PARICAO         = [27, 27];

    /**
     * Devolve peso-alvo (int kg) para idade × tamanho.
     * idadeMeses fora de [0, 27] retorna null (sem benchmark).
     */
    public static function targetWeight(int $idadeMeses, string $tamanho): ?int
    {
        $key = strtolower($tamanho);
        if (! isset(self::PESOS[$key])) return null;
        return self::PESOS[$key][$idadeMeses] ?? null;
    }

    /**
     * Resolve tamanho da raça pelo nome livre. Devolve null se não conhecida.
     * Tolera capitalização, acento, separadores.
     */
    public static function tamanhoForBreed(?string $breedNome): ?string
    {
        if (empty($breedNome)) return null;
        $normalizado = self::normalizeBreedName($breedNome);
        return self::BREED_SIZE[$normalizado] ?? null;
    }

    /**
     * Avalia o crescimento DROVET de uma fêmea leiteira.
     *
     * Devolve null quando a regra não se aplica (não é fêmea, sem raça leiteira
     * conhecida, sem data_nascimento, sem peso atual, ou idade > 27 meses).
     *
     * Devolve array com:
     *   [
     *     'aplicavel'    => true,
     *     'tamanho'      => 'grande' | 'media' | 'pequena',
     *     'tamanho_label'=> 'Grande' | 'Média' | 'Pequena',
     *     'idade_meses'  => int,
     *     'peso_atual'   => float (kg),
     *     'peso_alvo'    => int (kg),
     *     'desvio_kg'    => float (positivo = acima do alvo, negativo = abaixo),
     *     'desvio_pct'   => float,
     *     'fase'         => 'pre_puberdade' | 'puberdade_critica' | 'pos_puberdade'
     *                       | 'cobricao' | 'gestacao' | 'paricao',
     *     'fase_label'   => str humanizada,
     *     'status'       => 'ok' | 'aviso' | 'alerta',
     *     'titulo'       => str curta,
     *     'texto'        => str humanizada explicando,
     *     'cobricao'     => ['min' => int, 'max' => int] | null  (só na fase 18m+),
     *   ]
     */
    public static function evaluate(Animal $animal): ?array
    {
        // Pré-condições
        if ($animal->sexo !== 'F') return null;
        if (! $animal->data_nascimento) return null;
        if (! $animal->peso_atual) return null;

        $tamanho = self::tamanhoForBreed($animal->breed?->nome);
        if (! $tamanho) return null;

        $idadeMeses = (int) $animal->data_nascimento->diffInMonths(now());
        if ($idadeMeses < 0 || $idadeMeses > 27) return null;

        $alvo = self::targetWeight($idadeMeses, $tamanho);
        if ($alvo === null) return null;

        $pesoAtual = (float) $animal->peso_atual;
        $desvioKg  = $pesoAtual - $alvo;
        $desvioPct = $alvo > 0 ? ($desvioKg / $alvo) * 100 : 0;

        $fase      = self::detectarFase($idadeMeses);
        $faseLabel = self::FASE_LABELS[$fase];
        [$status, $titulo, $texto] = self::classificar($fase, $desvioKg, $desvioPct, $alvo, $pesoAtual, $idadeMeses, $tamanho);

        $cobricao = null;
        if ($idadeMeses >= 18 && isset(self::COBRICAO_RANGE[$tamanho])) {
            [$min, $max] = self::COBRICAO_RANGE[$tamanho];
            $cobricao = ['min' => $min, 'max' => $max];
        }

        return [
            'aplicavel'     => true,
            'tamanho'       => $tamanho,
            'tamanho_label' => self::TAMANHO_LABELS[$tamanho],
            'idade_meses'   => $idadeMeses,
            'peso_atual'    => round($pesoAtual, 1),
            'peso_alvo'     => $alvo,
            'desvio_kg'     => round($desvioKg, 1),
            'desvio_pct'    => round($desvioPct, 1),
            'fase'          => $fase,
            'fase_label'    => $faseLabel,
            'status'        => $status,
            'titulo'        => $titulo,
            'texto'         => $texto,
            'cobricao'      => $cobricao,
            'breed_nome'    => $animal->breed?->nome,
        ];
    }

    public const FASE_LABELS = [
        'pre_puberdade'     => 'Pré-puberdade (0-7 meses)',
        'puberdade_critica' => 'Puberdade — fase crítica (8-12 meses)',
        'pos_puberdade'     => 'Pós-puberdade (13-17 meses)',
        'cobricao'          => 'Cobrição (18 meses)',
        'gestacao'          => 'Gestação (19-26 meses)',
        'paricao'           => 'Parição (27 meses)',
    ];

    public const TAMANHO_LABELS = [
        'grande'  => 'Grande (Holandês, Pardo Suíço)',
        'media'   => 'Média (Girolando, Jersolando)',
        'pequena' => 'Pequena (Jersey)',
    ];

    private static function detectarFase(int $idadeMeses): string
    {
        if ($idadeMeses <= 7)  return 'pre_puberdade';
        if ($idadeMeses <= 12) return 'puberdade_critica';
        if ($idadeMeses <= 17) return 'pos_puberdade';
        if ($idadeMeses === 18) return 'cobricao';
        if ($idadeMeses <= 26) return 'gestacao';
        return 'paricao';
    }

    /**
     * Classifica em ok / aviso / alerta com texto humanizado por fase.
     * Tolerância: ±5% é considerado normal.
     */
    private static function classificar(string $fase, float $desvioKg, float $desvioPct, int $alvo, float $atual, int $idade, string $tamanho): array
    {
        $atualF = number_format($atual, 1, ',', '.');
        $alvoF  = $alvo;
        $desvioAbs = number_format(abs($desvioKg), 1, ',', '.');

        // FASE CRÍTICA (8-12m): acima do alvo é PROBLEMA, abaixo é só aviso
        if ($fase === 'puberdade_critica') {
            if ($desvioPct > 5) {
                return [
                    'alerta',
                    'Acima do peso na fase crítica',
                    "Aos {$idade} meses, o peso-alvo é {$alvoF} kg. Esta fêmea está com {$atualF} kg ({$desvioAbs} kg acima). Conforme a tabela DROVET, fêmeas leiteiras entre 8 e 12 meses NÃO DEVEM estar acima do referência — gordura nessa fase compromete o aparelho reprodutivo e mamário. Reduza concentrado e priorize volumoso.",
                ];
            }
            if ($desvioPct < -10) {
                return [
                    'alerta',
                    'Muito abaixo do peso na fase crítica',
                    "Aos {$idade} meses o peso-alvo é {$alvoF} kg, mas esta fêmea está com {$atualF} kg ({$desvioAbs} kg abaixo). Atraso significativo de crescimento na puberdade compromete a entrada no rebanho. Reveja alimentação e parasitismo.",
                ];
            }
            if ($desvioPct < -5) {
                return [
                    'aviso',
                    'Abaixo do peso ideal',
                    "Aos {$idade} meses o peso-alvo é {$alvoF} kg, esta fêmea está com {$atualF} kg ({$desvioAbs} kg abaixo). Acompanhe a próxima pesagem — se continuar abaixo, ajuste o manejo nutricional.",
                ];
            }
            return [
                'ok',
                'Peso correto na fase crítica',
                "Aos {$idade} meses, peso-alvo {$alvoF} kg. Esta fêmea está com {$atualF} kg — dentro da faixa ideal para a puberdade. Continue o manejo atual.",
            ];
        }

        // COBRIÇÃO (18m): atende a faixa específica?
        if ($fase === 'cobricao') {
            [$cMin, $cMax] = self::COBRICAO_RANGE[$tamanho];
            if ($atual < $cMin) {
                return [
                    'alerta',
                    'Abaixo do peso para cobrição',
                    "Aos 18 meses (cobrição) o peso ideal para raça {$tamanho} é {$cMin}–{$cMax} kg. Esta fêmea está com {$atualF} kg — abaixo do mínimo. Cobertura precoce com peso baixo gera bezerros fracos e pode comprometer o crescimento da matriz.",
                ];
            }
            if ($atual > $cMax) {
                return [
                    'aviso',
                    'Acima do peso para cobrição',
                    "Aos 18 meses (cobrição) o peso ideal para raça {$tamanho} é {$cMin}–{$cMax} kg. Esta fêmea está com {$atualF} kg — acima do máximo. Excesso de gordura dificulta a fertilidade e o parto.",
                ];
            }
            return [
                'ok',
                'Peso ideal para cobrição',
                "Aos 18 meses, peso ideal {$cMin}–{$cMax} kg para raça {$tamanho}. Esta fêmea está com {$atualF} kg — apta à cobrição.",
            ];
        }

        // FASES NÃO-CRÍTICAS (pré + pós-puberdade + gestação + parição)
        // Abaixo é problema, acima dentro de 15% é OK, muito acima é aviso
        if ($desvioPct < -15) {
            return [
                'alerta',
                'Muito abaixo do peso esperado',
                "Aos {$idade} meses, peso-alvo {$alvoF} kg. Esta fêmea está com {$atualF} kg ({$desvioAbs} kg abaixo, " . abs(round($desvioPct,1)) . "%). Atraso grave de crescimento — verifique alimentação, vermifugação e saúde geral.",
            ];
        }
        if ($desvioPct < -5) {
            return [
                'aviso',
                'Abaixo do peso esperado',
                "Aos {$idade} meses o peso-alvo é {$alvoF} kg. Esta fêmea está com {$atualF} kg ({$desvioAbs} kg abaixo). Acompanhe a próxima pesagem.",
            ];
        }
        if ($desvioPct > 20) {
            return [
                'aviso',
                'Bem acima do peso esperado',
                "Aos {$idade} meses, peso-alvo {$alvoF} kg. Esta fêmea está com {$atualF} kg ({$desvioAbs} kg acima). Verifique se não há excesso de concentrado.",
            ];
        }
        return [
            'ok',
            'Peso dentro do esperado',
            "Aos {$idade} meses, peso-alvo {$alvoF} kg. Esta fêmea está com {$atualF} kg — dentro da faixa adequada.",
        ];
    }

    /**
     * Normaliza nome de raça para casar com BREED_SIZE.
     * Lowercase + trim + remoção de acentos comuns.
     */
    private static function normalizeBreedName(string $nome): string
    {
        $nome = trim(mb_strtolower($nome, 'UTF-8'));
        $map = [
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
            'é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c',
        ];
        return strtr($nome, $map);
    }
}
