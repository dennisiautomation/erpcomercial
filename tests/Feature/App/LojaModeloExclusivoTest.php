<?php

namespace Tests\Feature\App;

use App\Models\LojaOnline;
use App\Services\LojaOnline\ModelosLoja;
use Tests\TestCase;

/** Modelo exclusivo da loja online (17/11/2026, MISS MERLINDA): só a loja dona enxerga. */
class LojaModeloExclusivoTest extends TestCase
{
    public function test_exclusivo_nunca_entra_na_lista_publica(): void
    {
        $this->assertCount(6, ModelosLoja::todos());
        $this->assertArrayNotHasKey('merlinda', ModelosLoja::todos());
        $this->assertArrayHasKey('merlinda', ModelosLoja::catalogo());
        $this->assertTrue(ModelosLoja::existe('merlinda'));
    }

    public function test_para_loja_so_traz_o_exclusivo_da_propria_loja(): void
    {
        $comum = new LojaOnline(['modelo' => 'vitrine']);
        $dona = new LojaOnline(['modelo' => 'merlinda', 'modelo_exclusivo' => 'merlinda']);

        $this->assertSame(array_keys(ModelosLoja::todos()), array_keys(ModelosLoja::paraLoja($comum)));
        $this->assertSame('merlinda', array_key_first(ModelosLoja::paraLoja($dona)));
        $this->assertArrayNotHasKey('gilda', ModelosLoja::fontesDaLoja($comum));
        $this->assertArrayHasKey('gilda', ModelosLoja::fontesDaLoja($dona));
    }

    public function test_bloco_exclusivo_so_passa_na_higiene_com_o_modelo(): void
    {
        $bloco = [['tipo' => 'lojas_fisicas', 'titulo' => 'Nossas lojas', 'texto' => 'x']];

        $this->assertSame([], ModelosLoja::limparBlocos($bloco));
        $this->assertSame([], ModelosLoja::limparBlocos($bloco, [], 'vitrine'));
        $this->assertSame('lojas_fisicas', ModelosLoja::limparBlocos($bloco, [], 'merlinda')[0]['tipo']);
        $this->assertArrayNotHasKey('lojas_fisicas', ModelosLoja::tiposDeBloco());
    }

    public function test_tema_do_exclusivo_e_valido_e_os_publicos_nao_mudaram(): void
    {
        $tema = ModelosLoja::tema('merlinda');
        $this->assertSame($tema, array_merge($tema, ModelosLoja::limparTema($tema)), 'o tema do modelo passa pela própria higiene');
        $this->assertSame('gilda', $tema['fonte_titulo']);
        $this->assertSame('#1746d1', ModelosLoja::tema('vitrine')['cor_primaria']);
        $this->assertSame(ModelosLoja::tema('vitrine'), ModelosLoja::tema('inexistente'));
    }
}
