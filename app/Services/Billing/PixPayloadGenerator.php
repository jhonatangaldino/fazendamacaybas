<?php

namespace App\Services\Billing;

/**
 * PixPayloadGenerator — gera payload EMV BR Code para PIX copia-e-cola.
 *
 * Implementa o padrão BACEN (EMVCo Merchant Presented Mode) com CRC16-CCITT-FALSE.
 * A string gerada é tecnicamente válida e pode ser escaneada por apps PIX reais,
 * porém aponta para chave fictícia — nenhuma transação acontecerá efetivamente.
 * Quando a integração com gateway real chegar, basta trocar a `pixKey` pelas
 * credenciais do PSP e a mesma estrutura segue funcionando.
 *
 * Formato dos campos EMV: `IIDDXXXX`
 *   II = ID (2 dígitos)
 *   DD = tamanho do valor (2 dígitos)
 *   XX = valor
 *
 * Exemplo de payload gerado:
 *   000201
 *   26 XX 0014br.gov.bcb.pix 01 YY {pixKey}
 *   52040000
 *   5303986
 *   54 ZZ {valor}
 *   5802BR
 *   59 ZZ {merchantName}
 *   60 ZZ {city}
 *   62 ZZ 0503{txid}
 *   6304{crc16}
 */
class PixPayloadGenerator
{
    /** Valores default — sobrescritos via config('billing.pix.*') quando existir */
    private const DEFAULT_KEY = 'billing@macaybas.saas';
    private const DEFAULT_MERCHANT = 'FAZENDA MACAYBAS SAAS';
    private const DEFAULT_CITY = 'JANAUBA';

    /**
     * Gera o payload completo (copia-e-cola).
     *
     * @param  float   $amount     Valor em reais (ex.: 99.90)
     * @param  string  $txid       Identificador único da transação (até 25 chars alfanuméricos)
     * @param  string|null $merchantName Sobrescreve nome do recebedor
     * @param  string|null $city         Sobrescreve cidade
     * @param  string|null $pixKey       Sobrescreve chave PIX
     */
    public function build(
        float $amount,
        string $txid,
        ?string $merchantName = null,
        ?string $city = null,
        ?string $pixKey = null,
    ): string {
        $merchantName = $this->sanitize($merchantName ?? self::DEFAULT_MERCHANT, 25);
        $city = $this->sanitize($city ?? self::DEFAULT_CITY, 15);
        $pixKey = $pixKey ?? self::DEFAULT_KEY;
        $txid = $this->sanitizeTxid($txid);
        $amountStr = number_format($amount, 2, '.', '');

        // Campo 26 — Merchant Account Information (PIX)
        $merchantAccount = $this->field('00', 'br.gov.bcb.pix')
            . $this->field('01', $pixKey);

        // Campo 62 — Additional Data Field Template
        $additionalData = $this->field('05', $txid);

        // Assembly do payload SEM o CRC
        $payload = $this->field('00', '01')                          // Payload Format Indicator
            . $this->field('01', '12')                               // Point of Initiation (12 = dinâmico c/ txid)
            . $this->field('26', $merchantAccount)                   // Merchant Account (sub-campos)
            . $this->field('52', '0000')                             // Merchant Category Code
            . $this->field('53', '986')                              // Currency (BRL)
            . $this->field('54', $amountStr)                         // Amount
            . $this->field('58', 'BR')                               // Country
            . $this->field('59', $merchantName)                      // Merchant Name
            . $this->field('60', $city)                              // City
            . $this->field('62', $additionalData)                    // Additional Data
            . '6304';                                                // CRC field header (sem valor ainda)

        // Calcula CRC16-CCITT-FALSE sobre o payload completo (incluindo "6304")
        $crc = $this->crc16($payload);

        return $payload . $crc;
    }

    /**
     * Gera um TXID legível baseado no tenant + número da fatura.
     *
     * Por que legível: o TXID aparece no extrato bancário do master quando
     * o pagamento cai. Com UUID aleatório (versão antiga) era impossível
     * identificar de qual cliente veio o PIX. Com sigla do tenant + número
     * da fatura, o master consegue conciliar visualmente sem abrir o sistema.
     *
     * Formato: SSSAAAAMMNNNN (3 letras sigla + ano-mês + sequencial)
     *   Ex.: "FAZ20260400037" → tenant Faz*, abril/2026, fatura 37
     *   Cabe folgado nos 25 chars do limite EMV (BACEN).
     *
     * Quando $tenantNome e $invoiceNumero são vazios → fallback UUID
     * (mantém compatibilidade com chamadas legadas / cobranças avulsas).
     */
    public function generateTxid(string $tenantNome = '', string $invoiceNumero = ''): string
    {
        if ($tenantNome === '' && $invoiceNumero === '') {
            $raw = strtoupper(str_replace('-', '', (string) \Illuminate\Support\Str::uuid()));
            return substr($raw, 0, 25);
        }
        $sigla = $this->siglaFromName($tenantNome);
        $numero = preg_replace('/[^A-Z0-9]/i', '', $invoiceNumero) ?? '';
        $txid = strtoupper($sigla . $numero);
        $txid = preg_replace('/[^A-Z0-9]/', '', $txid) ?? '';
        return substr($txid, 0, 25) ?: $this->generateTxid();
    }

    /**
     * Extrai 3 letras significativas do nome do tenant para uso no TXID.
     * Ignora palavras genéricas comuns no agro (FAZENDA, SITIO, GRANJA, etc.)
     * pra que dois clientes "Fazenda X" e "Fazenda Y" não fiquem com a
     * mesma sigla "FAZ".
     */
    private function siglaFromName(string $nome): string
    {
        $genericas = [
            'FAZENDA', 'SITIO', 'GRANJA', 'CHACARA', 'HARAS', 'ESTANCIA',
            'AGROPECUARIA', 'AGRO', 'RURAL', 'LTDA', 'ME', 'EIRELI', 'SA',
            'DA', 'DE', 'DO', 'DAS', 'DOS', 'E',
        ];
        $ascii = strtoupper(\Illuminate\Support\Str::ascii($nome));
        $palavras = preg_split('/\s+/', trim($ascii)) ?: [];
        $significativas = array_values(array_filter($palavras, fn ($p) => $p !== '' && ! in_array($p, $genericas, true)));
        $base = $significativas[0] ?? ($palavras[0] ?? 'CLI');
        $clean = preg_replace('/[^A-Z0-9]/', '', $base) ?? '';
        return substr($clean !== '' ? $clean : 'CLI', 0, 3);
    }

    /**
     * CRC16-CCITT-FALSE
     * Polinômio: 0x1021
     * Init:      0xFFFF
     * Sem XOR out, big endian, output em hex uppercase com zero-padding (4 chars)
     */
    private function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Monta um field EMV (IIDDXXX).
     */
    private function field(string $id, string $value): string
    {
        $len = strlen($value);
        return $id . str_pad((string) $len, 2, '0', STR_PAD_LEFT) . $value;
    }

    /**
     * Normaliza strings (remove acentos, vírgulas etc.) e trunca.
     * EMV é ASCII-only em campos texto.
     */
    private function sanitize(string $v, int $max): string
    {
        $v = \Illuminate\Support\Str::ascii($v);
        $v = preg_replace('/[^A-Za-z0-9@\.\s\-]/', '', $v) ?? '';
        $v = strtoupper(trim($v));
        return substr($v, 0, $max);
    }

    private function sanitizeTxid(string $v): string
    {
        $v = preg_replace('/[^A-Za-z0-9]/', '', $v) ?? '';
        return substr($v, 0, 25) ?: '***';
    }
}
