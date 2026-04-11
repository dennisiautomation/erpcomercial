<?php

namespace Database\Seeders;

use App\Models\Caixa;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\Fornecedor;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmpresaDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Vincular plano Enterprise
        $plano = Plano::where('slug', 'enterprise')->first();

        $empresa = Empresa::withoutGlobalScopes()->updateOrCreate(
            ['cnpj' => '12345678000190'],
            [
                'razao_social' => 'Empresa Demo Ltda',
                'nome_fantasia' => 'Demo Store',
                'regime_tributario' => 'simples_nacional',
                'cep' => '01001000',
                'logradouro' => 'Praca da Se',
                'numero' => '100',
                'bairro' => 'Se',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'telefone' => '11999999999',
                'email' => 'demo@empresa.com',
                'plano_id' => $plano?->id,
                'em_trial' => true,
                'trial_inicio' => now(),
                'trial_fim' => now()->addDays(30),
                'status' => 'ativo',
            ]
        );

        $unidade = Unidade::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'cnpj' => '12345678000190'],
            [
                'nome' => 'Loja Centro',
                'cep' => '01001000',
                'logradouro' => 'Praca da Se',
                'numero' => '100',
                'bairro' => 'Se',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'telefone' => '11999999999',
                'status' => 'ativa',
            ]
        );

        // Configuracao Fiscal da unidade
        ConfiguracaoFiscal::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id],
            [
                'focus_token' => 'token_demo_homologacao',
                'ambiente' => 'homologacao',
                'emissao_fiscal_ativa' => false,
                'tipo_cupom_pdv' => 'nao_fiscal',
                'serie_nfe' => '1',
                'serie_nfce' => '1',
            ]
        );

        // Dono da empresa
        $dono = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'dono@demo.com'],
            [
                'name' => 'Joao Silva',
                'password' => Hash::make('dono123'),
                'empresa_id' => $empresa->id,
                'cpf' => '12345678901',
                'telefone' => '11988888888',
                'perfil' => 'dono',
                'status' => 'ativo',
            ]
        );

        // Gerente
        $gerente = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'gerente@demo.com'],
            [
                'name' => 'Maria Santos',
                'password' => Hash::make('gerente123'),
                'empresa_id' => $empresa->id,
                'cpf' => '98765432101',
                'telefone' => '11977777777',
                'perfil' => 'gerente',
                'status' => 'ativo',
            ]
        );

        // Vendedor
        $vendedor = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'vendedor@demo.com'],
            [
                'name' => 'Carlos Oliveira',
                'password' => Hash::make('vendedor123'),
                'empresa_id' => $empresa->id,
                'cpf' => '11122233344',
                'telefone' => '11966666666',
                'perfil' => 'vendedor',
                'comissao_percentual' => 5.00,
                'status' => 'ativo',
            ]
        );

        // Caixa
        $caixaUser = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'caixa@demo.com'],
            [
                'name' => 'Ana Costa',
                'password' => Hash::make('caixa123'),
                'empresa_id' => $empresa->id,
                'cpf' => '55566677788',
                'telefone' => '11955555555',
                'perfil' => 'caixa',
                'status' => 'ativo',
            ]
        );

        // Vincular ALL users a unidade
        $allUsers = [$dono, $gerente, $vendedor, $caixaUser];
        foreach ($allUsers as $user) {
            $user->unidades()->syncWithoutDetaching([$unidade->id]);
        }

        // Caixa aberto para o usuario caixa
        Caixa::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'user_id' => $caixaUser->id, 'status' => 'aberto'],
            [
                'numero_caixa' => 1,
                'valor_abertura' => 200.00,
                'aberto_em' => now(),
            ]
        );

        // Categorias
        $eletronicos = Categoria::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'nome' => 'Eletronicos'],
            ['status' => 'ativa']
        );

        $acessorios = Categoria::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'nome' => 'Acessorios'],
            ['parent_id' => $eletronicos->id, 'status' => 'ativa']
        );

        // Produtos
        $prod1 = Produto::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'codigo_interno' => 'PROD001'],
            [
                'descricao' => 'Notebook Dell Inspiron 15',
                'unidade_medida' => 'UN',
                'categoria_id' => $eletronicos->id,
                'ncm' => '84713012',
                'origem' => 0,
                'preco_custo' => 3500.00,
                'markup' => 42.86,
                'preco_venda' => 4999.90,
                'estoque_minimo' => 5,
                'cfop' => '5102',
                'status' => 'ativo',
            ]
        );

        $prod2 = Produto::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'codigo_interno' => 'PROD002'],
            [
                'descricao' => 'Mouse Logitech MX Master 3',
                'unidade_medida' => 'UN',
                'categoria_id' => $acessorios->id,
                'ncm' => '84716053',
                'origem' => 1,
                'preco_custo' => 350.00,
                'markup' => 71.43,
                'preco_venda' => 599.90,
                'estoque_minimo' => 10,
                'cfop' => '5102',
                'status' => 'ativo',
            ]
        );

        $prod3 = Produto::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'codigo_interno' => 'PROD003'],
            [
                'descricao' => 'Teclado Mecanico Redragon Kumara',
                'unidade_medida' => 'UN',
                'categoria_id' => $acessorios->id,
                'ncm' => '84716053',
                'origem' => 1,
                'preco_custo' => 180.00,
                'markup' => 66.67,
                'preco_venda' => 299.90,
                'estoque_minimo' => 10,
                'cfop' => '5102',
                'status' => 'ativo',
            ]
        );

        // EstoqueMovimentacao para cada produto (entrada inicial de 100 unidades)
        foreach ([$prod1, $prod2, $prod3] as $produto) {
            EstoqueMovimentacao::withoutGlobalScopes()->updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'unidade_id' => $unidade->id,
                    'produto_id' => $produto->id,
                    'tipo' => 'entrada',
                ],
                [
                    'quantidade' => 100,
                    'quantidade_anterior' => 0,
                    'quantidade_posterior' => 100,
                    'user_id' => $dono->id,
                ]
            );
        }

        // Clientes
        Cliente::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'cpf_cnpj' => '99988877766'],
            [
                'tipo_pessoa' => 'pf',
                'nome_razao_social' => 'Pedro Almeida',
                'cep' => '01310100',
                'logradouro' => 'Av. Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'telefone' => '11944444444',
                'email' => 'pedro@email.com',
                'status' => 'ativo',
            ]
        );

        // Fornecedor
        Fornecedor::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'cpf_cnpj' => '11222333000144'],
            [
                'razao_social' => 'Distribuidora Tech Ltda',
                'nome_fantasia' => 'TechDist',
                'cep' => '09015000',
                'logradouro' => 'Rua das Industrias',
                'numero' => '500',
                'bairro' => 'Centro',
                'cidade' => 'Santo Andre',
                'uf' => 'SP',
                'telefone' => '1143211234',
                'email' => 'contato@techdist.com',
                'condicoes_comerciais' => '30/60/90 dias - Boleto',
            ]
        );
    }
}
