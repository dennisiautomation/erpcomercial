<?php

namespace Tests\Traits;

use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;

trait CreatesTestData
{
    protected Empresa $empresa;
    protected Unidade $unidade;

    protected function createTenant(string $suffix = ''): array
    {
        $s = $suffix ?: '1';

        $empresa = Empresa::withoutGlobalScopes()->create([
            'cnpj'              => '12345678000' . str_pad($s, 3, '0', STR_PAD_LEFT),
            'razao_social'      => 'Empresa Teste ' . $s,
            'nome_fantasia'     => 'Teste ' . $s,
            'regime_tributario'  => 'simples_nacional',
            'cep'               => '01001000',
            'logradouro'        => 'Rua Teste',
            'numero'            => '100',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11999999999',
            'email'             => "empresa{$s}@teste.com",
            'plano'             => 'profissional',
            'status'            => 'ativo',
            'em_trial'          => true,
            'trial_inicio'      => now()->toDateString(),
            'trial_fim'         => now()->addDays(30)->toDateString(),
        ]);

        $unidade = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome'       => 'Matriz ' . $s,
            'cnpj'       => $empresa->cnpj,
            'cep'        => '01001000',
            'logradouro' => 'Rua Teste',
            'numero'     => '100',
            'bairro'     => 'Centro',
            'cidade'     => 'São Paulo',
            'uf'         => 'SP',
            'telefone'   => '11999999999',
            'status'     => 'ativa',
        ]);

        return [$empresa, $unidade];
    }

    protected function createUser(Empresa $empresa, Unidade $unidade, string $perfil, array $extra = []): User
    {
        static $counter = 0;
        $counter++;

        return User::withoutGlobalScopes()->create(array_merge([
            'name'       => ucfirst($perfil) . " User {$counter}",
            'email'      => "{$perfil}{$counter}@teste.com",
            'password'   => bcrypt('password'),
            'empresa_id' => $empresa->id,
            'perfil'     => $perfil,
            'status'     => 'ativo',
        ], $extra));
    }

    protected function actingAsUser(User $user, Unidade $unidade): static
    {
        return $this->actingAs($user)->withSession([
            'unidade_id' => $unidade->id,
            'empresa_id' => $user->empresa_id,
        ]);
    }

    protected function createProduto(Empresa $empresa, array $extra = []): Produto
    {
        static $prodCounter = 0;
        $prodCounter++;

        return Produto::withoutGlobalScopes()->create(array_merge([
            'empresa_id'     => $empresa->id,
            'codigo_interno' => str_pad($prodCounter, 6, '0', STR_PAD_LEFT),
            'descricao'      => "Produto Teste {$prodCounter}",
            'unidade_medida' => 'UN',
            'preco_custo'    => 50.00,
            'preco_venda'    => 100.00,
            'status'         => 'ativo',
        ], $extra));
    }

    protected function createCliente(Empresa $empresa, array $extra = []): Cliente
    {
        static $cliCounter = 0;
        $cliCounter++;

        return Cliente::withoutGlobalScopes()->create(array_merge([
            'empresa_id'        => $empresa->id,
            'tipo_pessoa'       => 'pf',
            'cpf_cnpj'          => '0000000' . str_pad($cliCounter, 4, '0', STR_PAD_LEFT),
            'nome_razao_social' => "Cliente Teste {$cliCounter}",
            'cep'               => '01001000',
            'logradouro'        => 'Rua do Cliente',
            'numero'            => '200',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11988888888',
            'status'            => 'ativo',
        ], $extra));
    }

    protected function createFornecedor(Empresa $empresa, array $extra = []): Fornecedor
    {
        static $fornCounter = 0;
        $fornCounter++;

        return Fornecedor::withoutGlobalScopes()->create(array_merge([
            'empresa_id'   => $empresa->id,
            'cpf_cnpj'     => '1111111' . str_pad($fornCounter, 4, '0', STR_PAD_LEFT) . '00',
            'razao_social' => "Fornecedor Teste {$fornCounter}",
            'cep'          => '01001000',
            'logradouro'   => 'Rua Fornecedor',
            'numero'       => '300',
            'bairro'       => 'Industrial',
            'cidade'       => 'São Paulo',
            'uf'           => 'SP',
            'telefone'     => '11977777777',
        ], $extra));
    }

    protected function openCaixa(Empresa $empresa, Unidade $unidade, User $user, float $valorAbertura = 100.00): Caixa
    {
        return Caixa::withoutGlobalScopes()->create([
            'empresa_id'     => $empresa->id,
            'unidade_id'     => $unidade->id,
            'user_id'        => $user->id,
            'numero_caixa'   => 1,
            'valor_abertura' => $valorAbertura,
            'status'         => 'aberto',
            'aberto_em'      => now(),
        ]);
    }
}
