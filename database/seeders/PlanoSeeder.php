<?php

namespace Database\Seeders;

use App\Models\Plano;
use Illuminate\Database\Seeder;

class PlanoSeeder extends Seeder
{
    public function run(): void
    {
        $planos = [
            [
                'nome'                   => 'Basico',
                'slug'                   => 'basico',
                'descricao'              => 'Ideal para quem esta comecando. Controle basico de vendas, estoque e financeiro.',
                'preco_mensal'           => 97.00,
                'preco_anual'            => 970.00,
                'max_unidades'           => 1,
                'max_usuarios'           => 5,
                'max_produtos'           => 500,
                'max_notas_mes'          => 100,
                'pdv_habilitado'         => true,
                'fiscal_habilitado'      => false,
                'multilojas_habilitado'  => false,
                'os_habilitado'          => false,
                'contratos_habilitado'   => false,
                'conciliacao_habilitada' => false,
                'dre_habilitado'         => false,
                'boletos_habilitado'     => false,
                'api_habilitada'         => false,
                'dias_trial'             => 14,
                'ativo'                  => true,
                'ordem'                  => 1,
            ],
            [
                'nome'                   => 'Profissional',
                'slug'                   => 'profissional',
                'descricao'              => 'Para empresas em crescimento. Multilojas, fiscal, OS e contratos inclusos.',
                'preco_mensal'           => 197.00,
                'preco_anual'            => 1970.00,
                'max_unidades'           => 3,
                'max_usuarios'           => 15,
                'max_produtos'           => 5000,
                'max_notas_mes'          => 500,
                'pdv_habilitado'         => true,
                'fiscal_habilitado'      => true,
                'multilojas_habilitado'  => true,
                'os_habilitado'          => true,
                'contratos_habilitado'   => true,
                'conciliacao_habilitada' => false,
                'dre_habilitado'         => false,
                'boletos_habilitado'     => false,
                'api_habilitada'         => false,
                'dias_trial'             => 14,
                'ativo'                  => true,
                'ordem'                  => 2,
            ],
            [
                'nome'                   => 'Enterprise',
                'slug'                   => 'enterprise',
                'descricao'              => 'Solucao completa sem limites. Todos os modulos, API, conciliacao, DRE e boletos.',
                'preco_mensal'           => 397.00,
                'preco_anual'            => 3970.00,
                'max_unidades'           => 999,
                'max_usuarios'           => 999,
                'max_produtos'           => 999999,
                'max_notas_mes'          => 999999,
                'pdv_habilitado'         => true,
                'fiscal_habilitado'      => true,
                'multilojas_habilitado'  => true,
                'os_habilitado'          => true,
                'contratos_habilitado'   => true,
                'conciliacao_habilitada' => true,
                'dre_habilitado'         => true,
                'boletos_habilitado'     => true,
                'api_habilitada'         => true,
                'dias_trial'             => 30,
                'ativo'                  => true,
                'ordem'                  => 3,
            ],
        ];

        foreach ($planos as $plano) {
            Plano::updateOrCreate(['slug' => $plano['slug']], $plano);
        }
    }
}
