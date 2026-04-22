<?php

namespace App\Services\BarcodeLookup\Contracts;

use App\Services\BarcodeLookup\DTO\ProductResult;

/**
 * Contrato mínimo para qualquer fonte de lookup.
 * Implementações devem ser stateless e thread-safe (containerizáveis).
 */
interface BarcodeSource
{
    /** Chave técnica única (snake_case). Usada como ID em logs/config. */
    public function name(): string;

    /** Nome legível para dashboards internos. */
    public function label(): string;

    /** Se a fonte está apta a responder (config + credenciais). */
    public function isEnabled(): bool;

    /**
     * Consulta o produto pelo código.
     * Retorna:
     *   - ProductResult com nome quando acha
     *   - null quando não acha (sem lançar exceção)
     *
     * Deve:
     *   - Respeitar o timeout recebido
     *   - Lançar Throwable apenas em erros verdadeiros (timeout, 5xx, parsing)
     *     — o orquestrador captura e registra como tentativa com falha
     *
     * @param  string  $barcode  EAN-13/UPC/Code128 normalizado (só dígitos, sem formatação)
     * @param  int  $timeoutSeconds  máximo da chamada de rede
     */
    public function lookup(string $barcode, int $timeoutSeconds): ?ProductResult;
}
