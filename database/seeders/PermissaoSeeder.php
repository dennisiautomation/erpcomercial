<?php

namespace Database\Seeders;

use App\Models\Permissao;
use Illuminate\Database\Seeder;

class PermissaoSeeder extends Seeder
{
    public function run(): void
    {
        $modulos = [
            'empresas' => ['ver', 'criar', 'editar', 'excluir'],
            'unidades' => ['ver', 'criar', 'editar', 'excluir'],
            'funcionarios' => ['ver', 'criar', 'editar', 'excluir'],
            'produtos' => ['ver', 'criar', 'editar', 'excluir'],
            'clientes' => ['ver', 'criar', 'editar', 'excluir'],
            'orcamentos' => ['ver', 'criar', 'editar', 'excluir'],
            'pedidos' => ['ver', 'criar', 'editar', 'excluir'],
            'vendas' => ['ver', 'criar', 'editar', 'excluir'],
            'estoque' => ['ver', 'criar', 'editar', 'excluir'],
            'financeiro' => ['ver', 'criar', 'editar', 'excluir'],
            'notas_fiscais' => ['ver', 'criar', 'editar', 'excluir'],
            'relatorios' => ['ver'],
            'configuracoes' => ['ver', 'criar', 'editar', 'excluir'],
        ];

        foreach ($modulos as $modulo => $acoes) {
            foreach ($acoes as $acao) {
                Permissao::updateOrCreate(
                    ['modulo' => $modulo, 'acao' => $acao],
                    ['descricao' => ucfirst($acao) . ' ' . str_replace('_', ' ', $modulo)]
                );
            }
        }
    }
}
