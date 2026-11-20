<?php

namespace App\Services\Plataforma;

use App\Enums\Perfil;
use App\Enums\StatusEmpresa;
use App\Models\CadastroSite;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\PlataformaConfiguracao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Autocontratação pelo site (20/11/2026).
 *
 * UMA chave liga tudo: `site_contratacao_ativa` em plataforma_configuracoes (Admin → Planos).
 *  - desligada (padrão): o site mostra os planos como "Sob consulta", sem preço, e os botões
 *    levam ao WhatsApp/demonstração; /teste-gratis volta para a página inicial.
 *  - ligada: o site mostra os preços e o botão "Testar grátis"; a pessoa se cadastra, confirma
 *    o e-mail e a empresa nasce em teste pelos dias do plano.
 *
 * A cobrança depois do teste NÃO é feita aqui (decisão do Dennis em 20/11: será pelo Inter,
 * via caixa.ia365, a definir). Ao vencer o teste, a empresa de origem 'site' fica bloqueada
 * e é chamada a falar com a equipe.
 */
class ContratacaoSite
{
    public const CHAVE = 'site_contratacao_ativa';

    /** Horas de validade do link de confirmação. */
    public const VALIDADE_HORAS = 48;

    public static function ativa(): bool
    {
        try {
            return PlataformaConfiguracao::get(self::CHAVE, '0') === '1';
        } catch (\Throwable) {
            return false; // tabela fora do ar: o site abre no modo "sob consulta"
        }
    }

    /** Chave do e-mail que recebe o aviso interno de cada cadastro novo (Admin → Planos). */
    public const CHAVE_EMAIL_AVISO = 'site_contratacao_email_aviso';

    /**
     * Para onde vai o aviso "novo teste grátis pelo site". O endereço gravado no painel
     * manda; sem ele, cai no remetente do sistema (`mail.from.address`), como era antes.
     */
    public static function emailAviso(): ?string
    {
        try {
            $v = PlataformaConfiguracao::get(self::CHAVE_EMAIL_AVISO);
        } catch (\Throwable) {
            $v = null;
        }

        return $v ?: (config('mail.from.address') ?: null);
    }

    /** O que está gravado no painel (vazio = usando o remetente do sistema). */
    public static function emailAvisoGravado(): ?string
    {
        try {
            return PlataformaConfiguracao::get(self::CHAVE_EMAIL_AVISO);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function definirEmailAviso(?string $email): void
    {
        PlataformaConfiguracao::set(self::CHAVE_EMAIL_AVISO, $email ? mb_strtolower(trim($email)) : null);
    }

    public static function definir(bool $ativa): void
    {
        PlataformaConfiguracao::set(self::CHAVE, $ativa ? '1' : '0');
    }

    /**
     * Guarda o pedido e devolve [cadastro, token em claro]. O token só existe no e-mail;
     * no banco fica o hash. Pedido anterior não confirmado do mesmo e-mail/CNPJ é descartado.
     */
    public function registrarPedido(array $dados, Plano $plano, ?string $ip): array
    {
        CadastroSite::whereNull('confirmado_em')
            ->where(fn ($q) => $q->where('email', $dados['email'])->orWhere('cnpj', $dados['cnpj']))
            ->delete();

        $token = Str::random(64);

        $cadastro = CadastroSite::create([
            'token_hash' => hash('sha256', $token),
            'email'      => $dados['email'],
            'cnpj'       => $dados['cnpj'],
            'plano_id'   => $plano->id,
            'dados'      => $dados,
            'ip'         => $ip,
            'expira_em'  => now()->addHours(self::VALIDADE_HORAS),
        ]);

        return [$cadastro, $token];
    }

    public function buscarPorToken(string $token): ?CadastroSite
    {
        return CadastroSite::where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * Cria empresa (em teste), a loja Matriz e o dono. Tudo ou nada.
     * A trava no cadastro impede que dois cliques no link criem duas empresas.
     */
    public function confirmar(CadastroSite $cadastro): User
    {
        return DB::transaction(function () use ($cadastro) {
            $cadastro = CadastroSite::whereKey($cadastro->id)->lockForUpdate()->firstOrFail();

            if ($cadastro->confirmado_em) {
                throw new \DomainException('ja_confirmado');
            }

            $d = $cadastro->dados;
            $plano = Plano::findOrFail($cadastro->plano_id);

            if (Empresa::withoutGlobalScopes()->where('cnpj', $d['cnpj'])->exists()) {
                throw new \DomainException('cnpj_em_uso');
            }
            if (User::withoutGlobalScopes()->where('email', $d['email'])->exists()) {
                throw new \DomainException('email_em_uso');
            }

            $endereco = [
                'cep'         => $d['cep'],
                'logradouro'  => $d['logradouro'],
                'numero'      => $d['numero'],
                'complemento' => $d['complemento'] ?? null,
                'bairro'      => $d['bairro'],
                'cidade'      => $d['cidade'],
                'uf'          => $d['uf'],
                'telefone'    => $d['whatsapp'],
            ];

            $empresa = new Empresa(array_merge($endereco, [
                'cnpj'              => $d['cnpj'],
                'razao_social'      => $d['razao_social'],
                'nome_fantasia'     => $d['nome_fantasia'] ?: $d['razao_social'],
                'regime_tributario' => $d['regime_tributario'],
                'email'             => $d['email'],
                'status'            => StatusEmpresa::Ativo,
                'plano'             => $plano->slug,
                'plano_id'          => $plano->id,
                'em_trial'          => true,
                'trial_inicio'      => now(),
                'trial_fim'         => now()->addDays((int) ($plano->dias_trial ?: 14)),
            ]));
            $empresa->origem_cadastro = 'site';
            $empresa->save();

            $unidade = Unidade::withoutGlobalScopes()->create(array_merge($endereco, [
                'empresa_id' => $empresa->id,
                'nome'       => 'Matriz',
                'cnpj'       => $d['cnpj'],
                'status'     => 'ativa',
            ]));

            $user = new User([
                'name'       => $d['nome'],
                'email'      => $d['email'],
                'telefone'   => $d['whatsapp'],
                'empresa_id' => $empresa->id,
                'perfil'     => Perfil::Dono,
                'status'     => 'ativo',
            ]);
            $user->password = $d['senha_hash']; // já veio com hash do pedido
            $user->email_verified_at = now();
            $user->save();
            $user->unidades()->sync([$unidade->id]);

            $cadastro->update(['confirmado_em' => now(), 'empresa_id' => $empresa->id]);

            return $user;
        });
    }
}
