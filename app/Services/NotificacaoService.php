<?php

namespace App\Services;

use App\Models\ContaReceber;
use App\Models\EstoqueComodato;
use App\Models\Notificacao;
use App\Models\Produto;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

class NotificacaoService
{
    /**
     * Cria uma nova notificação.
     */
    public static function criar(
        int $userId,
        int $empresaId,
        string $tipo,
        string $titulo,
        ?string $mensagem = null,
        ?string $url = null,
        string $icone = 'bell',
        string $cor = 'primary'
    ): Notificacao {
        return Notificacao::withoutGlobalScopes()->create([
            'user_id' => $userId,
            'empresa_id' => $empresaId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url,
            'icone' => $icone,
            'cor' => $cor,
        ]);
    }

    /**
     * Gera alertas automáticos para o usuário com base no estado do sistema.
     * Evita duplicatas verificando se já existe notificação não-lida do mesmo tipo.
     */
    public static function gerarAlertas(int $userId, int $empresaId): void
    {
        // Contas a receber vencidas
        $vencidas = ContaReceber::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', now())
            ->count();

        if ($vencidas > 0) {
            $jaExiste = Notificacao::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('tipo', 'conta_vencida')
                ->where('lida', false)
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if (! $jaExiste) {
                self::criar(
                    $userId,
                    $empresaId,
                    'conta_vencida',
                    "{$vencidas} conta(s) a receber vencida(s)",
                    'Verifique as contas pendentes',
                    route('app.contas-receber.index'),
                    'exclamation-triangle',
                    'danger'
                );
            }
        }

        // Estoque baixo (produtos com estoque_minimo > 0 onde o saldo <= mínimo).
        //
        // NÃO existe coluna `produtos.estoque` — o saldo é a soma da ÚLTIMA
        // movimentação de cada par (produto, unidade), igual ao RelatorioController.
        // A versão antiga consultava a coluna inexistente e estourava; como o
        // DashboardController engole a exceção, este alerta e todos os seguintes
        // (trial expirando) nunca dispararam. Ver armadilha 43.
        $estoqueBaixo = DB::table('produtos as p')
            ->leftJoinSub(
                DB::table('estoque_movimentacoes as e')
                    ->select('e.produto_id', DB::raw('SUM(e.quantidade_posterior) as saldo'))
                    ->whereIn('e.id', function ($q) use ($empresaId) {
                        $q->selectRaw('MAX(id)')
                            ->from('estoque_movimentacoes')
                            ->where('empresa_id', $empresaId)
                            ->groupBy('produto_id', 'unidade_id');
                    })
                    ->groupBy('e.produto_id'),
                's',
                fn ($j) => $j->on('s.produto_id', '=', 'p.id')
            )
            ->where('p.empresa_id', $empresaId)
            ->where('p.estoque_minimo', '>', 0)
            ->whereRaw('COALESCE(s.saldo, 0) <= p.estoque_minimo')
            ->count();

        if ($estoqueBaixo > 0) {
            $jaExiste = Notificacao::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('tipo', 'alerta_estoque')
                ->where('lida', false)
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if (! $jaExiste) {
                self::criar(
                    $userId,
                    $empresaId,
                    'alerta_estoque',
                    "{$estoqueBaixo} produto(s) com estoque baixo",
                    'Verifique os produtos com estoque abaixo do mínimo',
                    route('app.relatorios.estoque'),
                    'box-seam',
                    'warning'
                );
            }
        }

        // Peças em poder de terceiros com a data prevista de retorno vencida
        $comodatosAtrasados = EstoqueComodato::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->atrasado()
            ->count();

        if ($comodatosAtrasados > 0) {
            $jaExiste = Notificacao::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('tipo', 'comodato_atrasado')
                ->where('lida', false)
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if (! $jaExiste) {
                self::criar(
                    $userId,
                    $empresaId,
                    'comodato_atrasado',
                    "{$comodatosAtrasados} peça(s) não voltaram na data prevista",
                    'Veja com quem estão e cobre o retorno',
                    route('app.comodatos.index', ['situacao' => 'atrasado']),
                    'arrow-counterclockwise',
                    'danger'
                );
            }
        }

        // Trial expirando
        $empresa = Empresa::find($empresaId);
        if ($empresa) {
            $trialDias = $empresa->diasRestantesTrial();
            if ($trialDias > 0 && $trialDias <= 7) {
                $jaExiste = Notificacao::withoutGlobalScopes()
                    ->where('user_id', $userId)
                    ->where('tipo', 'trial_expirando')
                    ->where('lida', false)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->exists();

                if (! $jaExiste) {
                    self::criar(
                        $userId,
                        $empresaId,
                        'trial_expirando',
                        "Seu trial expira em {$trialDias} dia(s)",
                        'Assine um plano para continuar usando o sistema',
                        route('app.plano.index'),
                        'clock-history',
                        'primary'
                    );
                }
            }
        }
    }

    /**
     * Conta notificações não lidas do usuário.
     */
    public static function contarNaoLidas(int $userId): int
    {
        return Notificacao::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('lida', false)
            ->count();
    }
}
