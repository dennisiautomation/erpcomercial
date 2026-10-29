<?php

namespace Tests\Unit;

use App\Services\Pos\PosCupomService;
use PHPUnit\Framework\TestCase;

/** Erro da NFC-e mostrado na maquininha: legível, sem URL nem "cURL error" (29/10/2026, cópia de produção). */
class PosMensagemNfceTest extends TestCase
{
    public function test_falha_de_rede_vira_sem_conexao(): void
    {
        $erro = 'NFC-e rejeitada: cURL error 7: Failed to connect to api.focusnfe.com.br:443 after 0 ms: Could not connect to server '
            . '(see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.focusnfe.com.br/v2/nfce?ref=nfce-1';
        $this->assertSame('Sem conexão com o emissor de notas agora.', PosCupomService::mensagemNfce($erro));
        $this->assertSame('Sem conexão com o emissor de notas agora.', PosCupomService::mensagemNfce('cURL error 28: Operation timed out'));
    }

    public function test_rejeicao_da_sefaz_fica_legivel_e_curta(): void
    {
        $this->assertSame('Rejeição 778: Informado NCM inexistente.', PosCupomService::mensagemNfce('NFC-e rejeitada: Rejeição 778: Informado NCM inexistente'));
        $longo = PosCupomService::mensagemNfce(str_repeat('Erro de validação do item ', 20) . 'https://x.test/a');
        $this->assertLessThanOrEqual(161, mb_strlen($longo));
        $this->assertStringNotContainsString('http', $longo);
        $this->assertNull(PosCupomService::mensagemNfce(null));
        $this->assertNull(PosCupomService::mensagemNfce('  '));
    }
}
