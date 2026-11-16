<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Produto;
use App\Services\LojaOnline\GradeLoja;
use Illuminate\Http\Request;

/**
 * Grade (tamanho, cor…) no CADASTRO do produto — 16/11/2026.
 *
 * Vale para qualquer empresa, com ou sem loja online: a grade é só uma
 * amarração entre produtos comuns (ver GradeLoja). Quem mexe é quem tem
 * 'editar' em Produtos. A tela é o quadro "Tamanhos e cores" do cadastro.
 */
class ProdutoGradeController extends Controller
{
    private function voltar(Produto $produto, string $tipo, string $mensagem)
    {
        return redirect()->to(route('app.produtos.edit', $produto) . '#grade')->with($tipo, $mensagem);
    }

    public function gerar(Request $request, Produto $produto)
    {
        $dados = $request->validate([
            'atributo' => ['required', 'array', 'min:1', 'max:' . GradeLoja::MAXIMO_ATRIBUTOS],
            'atributo.*' => ['nullable', 'string', 'max:20'],
            'valores' => ['required', 'array'],
            'valores.*' => ['nullable', 'string', 'max:400'],
        ]);
        try {
            $criadas = GradeLoja::gerar($produto, GradeLoja::atributosDaTela($dados['atributo'], $dados['valores']));
        } catch (\InvalidArgumentException $e) {
            return $this->voltar($produto, 'error', $e->getMessage());
        }

        return $this->voltar($produto, 'success', "Pronto: {$criadas} produtos novos, com estoque zero. Dê entrada no estoque de cada um como em qualquer produto.");
    }

    public function acrescentar(Request $request, Produto $produto)
    {
        $dados = $request->validate(['atributo' => ['required', 'string', 'max:20'], 'valores' => ['required', 'string', 'max:400']]);
        $principal = GradeLoja::principalDe($produto);
        try {
            if (! $principal) {
                throw new \InvalidArgumentException('Este produto não está em uma grade.');
            }
            $criadas = GradeLoja::acrescentar($principal, $dados['atributo'], GradeLoja::valores($dados['valores']));
        } catch (\InvalidArgumentException $e) {
            return $this->voltar($produto, 'error', $e->getMessage());
        }

        return $this->voltar($produto, 'success', "Pronto: {$criadas} produtos novos, com estoque zero.");
    }

    public function juntar(Request $request, Produto $produto)
    {
        $dados = $request->validate(['codigo' => ['required', 'string', 'max:50'], 'opcoes' => ['required', 'array'], 'opcoes.*' => ['nullable', 'string', 'max:30']]);
        $principal = GradeLoja::principalDe($produto);
        $termo = trim($dados['codigo']);
        $outro = Produto::where('codigo_interno', str_pad($termo, 6, '0', STR_PAD_LEFT))->first()
            ?? Produto::where('codigo_interno', $termo)->first()
            ?? Produto::where('codigo_barras', $termo)->first()
            ?? Produto::where('sku', $termo)->first();
        try {
            if (! $principal) {
                throw new \InvalidArgumentException('Este produto não está em uma grade.');
            }
            if (! $outro) {
                throw new \InvalidArgumentException('Não achei produto com o código ' . $termo . '.');
            }
            GradeLoja::juntar($principal, $outro, $dados['opcoes']);
        } catch (\InvalidArgumentException $e) {
            return $this->voltar($produto, 'error', $e->getMessage());
        }

        return $this->voltar($produto, 'success', 'Produto ' . $outro->codigo_interno . ' entrou na grade.');
    }

    /** Tira da grade a variação informada (ou o próprio produto). Tirar o principal desfaz a grade. */
    public function tirar(Request $request, Produto $produto)
    {
        $alvo = $request->filled('variacao_id') ? Produto::findOrFail((int) $request->input('variacao_id')) : $produto;
        if (! $produto->grade_id || (int) $alvo->grade_id !== (int) $produto->grade_id) {
            return $this->voltar($produto, 'error', 'Este produto não faz parte desta grade.');
        }
        $eraPrincipal = (int) $alvo->grade_id === (int) $alvo->id;
        GradeLoja::tirar($alvo);

        return $this->voltar($produto, 'success', $eraPrincipal
            ? 'Grade desfeita. Os produtos continuam cadastrados, cada um por si.'
            : 'Produto ' . $alvo->codigo_interno . ' tirado da grade (ele continua cadastrado).');
    }

    public function preco(Produto $produto)
    {
        if (! $produto->grade_id) {
            return $this->voltar($produto, 'error', 'Este produto não está em uma grade.');
        }
        $n = GradeLoja::precoParaTodas($produto);

        return $this->voltar($produto, 'success', "Preço deste produto aplicado aos outros {$n} da grade.");
    }

    public function foto(Produto $produto)
    {
        try {
            if (! $produto->grade_id) {
                throw new \InvalidArgumentException('Este produto não está em uma grade.');
            }
            $n = GradeLoja::fotoParaTodas($produto);
        } catch (\InvalidArgumentException $e) {
            return $this->voltar($produto, 'error', $e->getMessage());
        }

        return $this->voltar($produto, 'success', "Foto deste produto copiada para os outros {$n} da grade.");
    }
}
