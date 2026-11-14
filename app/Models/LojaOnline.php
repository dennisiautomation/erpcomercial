<?php

namespace App\Models;

use App\Services\LojaOnline\ModelosLoja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Loja online (e-commerce) de uma empresa — 14/11/2026.
 *
 * SEM BelongsToEmpresa de propósito (mesma razão do AgenteIaConfig): a vitrine
 * é pública, sem sessão do ERP. Todo acesso filtra empresa_id explicitamente.
 *
 * Duas chaves, de donos diferentes:
 * - `ativo`: a PLATAFORMA liberou o módulo para a empresa (admin; depois, autocontratação);
 * - `publicada`: o LOJISTA pôs a loja no ar. Fora do ar, só quem está logado no
 *   ERP da empresa enxerga (prévia).
 */
class LojaOnline extends Model
{
    protected $table = 'lojas_online';

    protected $fillable = [
        'empresa_id', 'ativo', 'publicada', 'slug', 'dominio', 'nome',
        'unidade_id', 'estoque_id', 'vendedor_id',
        'modelo', 'tema', 'blocos', 'rascunho', 'config', 'publicada_em',
    ];

    /** Opções da loja com o valor de fábrica (o que não está gravado vale isto). */
    public const CONFIG_PADRAO = [
        'vender_sem_estoque' => false,
        'mostrar_estoque' => false,
        'retirada' => true,
        'entrega' => true,
        'pix' => true,
        'cartao' => true,
        'combinar' => true,
        'pedido_minimo' => 0,
        'whatsapp' => '',
        'instagram' => '',
        'email' => '',
        'aviso_topo' => '',
        'texto_rodape' => '',
        'texto_trocas' => '',
        'descricao_busca' => '',
        // redes sociais e WhatsApp
        'facebook' => '', 'tiktok' => '', 'youtube' => '',
        'whatsapp_flutuante' => true, 'whatsapp_mensagem' => 'Olá! Vim pela loja online.',
        'horario_atendimento' => '',
        // promoções da loja
        'frete_gratis_acima' => 0,
        'popup_ativo' => false, 'popup_titulo' => '', 'popup_texto' => '', 'popup_imagem' => '', 'popup_botao' => '', 'popup_link' => '',
        'popup_cupom' => '', 'popup_gatilho' => 'tempo', 'popup_segundos' => 6, 'popup_capturar' => false, 'popup_dias' => 7,
        // avisos por e-mail
        'email_pedidos' => '', 'email_loja_novo' => true, 'email_cliente_recebido' => true, 'email_cliente_confirmado' => true,
        'email_cliente_andamento' => true, 'email_remetente' => '',
        // políticas e cookies
        'politica_privacidade' => '', 'politica_entrega' => '', 'politica_termos' => '', 'cookies_aviso' => true,
        // rastreamento e busca
        'meta_pixel' => '', 'ga4' => '', 'google_ads' => '', 'google_ads_rotulo' => '', 'gtm' => '', 'tiktok_pixel' => '',
        'verificacao_google' => '', 'verificacao_meta' => '',
        'seo_titulo' => '', 'imagem_compartilhar' => '', 'favicon' => '',
    ];

    /** Tem algum rastreador de anúncio/medição configurado? (obriga o aviso de cookies) */
    public function temRastreamento(): bool
    {
        foreach (['meta_pixel', 'ga4', 'google_ads', 'gtm', 'tiktok_pixel'] as $k) {
            if (filled($this->opcao($k))) {
                return true;
            }
        }

        return false;
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'publicada' => 'boolean',
            'tema' => 'array',
            'blocos' => 'array',
            'rascunho' => 'array',
            'config' => 'array',
            'publicada_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class)->withoutGlobalScopes();
    }

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class)->withoutGlobalScopes();
    }

    public static function daEmpresa(int $empresaId): ?self
    {
        return static::where('empresa_id', $empresaId)->first();
    }

    /** A plataforma liberou a loja online para esta empresa? */
    public static function ativaPara(?int $empresaId): bool
    {
        return $empresaId !== null
            && static::where('empresa_id', $empresaId)->where('ativo', true)->exists();
    }

    /** Aberta ao público: liberada pela plataforma, publicada pelo lojista e com loja de origem. */
    public function noAr(): bool
    {
        return $this->ativo && $this->publicada && $this->unidade_id !== null;
    }

    public function opcao(string $chave): mixed
    {
        return ($this->config ?? [])[$chave] ?? self::CONFIG_PADRAO[$chave] ?? null;
    }

    /** Tema efetivo: o do modelo + o que o lojista mudou. */
    public function temaEfetivo(?array $sobrepor = null): array
    {
        $base = ModelosLoja::tema($this->modelo);

        return array_merge($base, array_filter($sobrepor ?? $this->tema ?? [], fn ($v) => $v !== null && $v !== ''));
    }

    /** Blocos da página inicial (publicados). Loja nova usa os do modelo. */
    public function blocosEfetivos(): array
    {
        return $this->blocos ?: ModelosLoja::blocos($this->modelo);
    }

    /**
     * O que o editor está montando: {modelo, tema, blocos}. Sem rascunho, parte
     * do que está publicado.
     */
    public function rascunhoEfetivo(): array
    {
        $r = $this->rascunho ?? [];
        $modelo = $r['modelo'] ?? $this->modelo;

        return [
            'modelo' => $modelo,
            'tema' => array_merge(ModelosLoja::tema($modelo), array_filter($r['tema'] ?? $this->tema ?? [], fn ($v) => $v !== null && $v !== '')),
            'blocos' => $r['blocos'] ?? $this->blocosEfetivos(),
        ];
    }

    /** Domínio-base dos endereços `nome.base` (LOJA_ONLINE_DOMINIO_BASE). Vazio = só por caminho. */
    public static function dominioBase(): string
    {
        return trim((string) config('loja_online.dominio_base', ''), '. ');
    }

    /** Endereço público preferido: domínio próprio, subdomínio ou /loja/{slug}. */
    public function enderecoPublico(): string
    {
        if ($this->dominio) {
            return 'https://' . $this->dominio;
        }
        if (self::dominioBase() !== '') {
            return 'https://' . $this->slug . '.' . self::dominioBase();
        }

        return rtrim((string) config('app.url'), '/') . '/loja/' . $this->slug;
    }

    /** Endereço que sempre funciona (sem depender de DNS): dentro do próprio ERP. */
    public function enderecoInterno(string $caminho = ''): string
    {
        return '/loja/' . $this->slug . ($caminho !== '' ? '/' . ltrim($caminho, '/') : '');
    }

    /** Slug livre a partir de um nome (para a ativação pelo admin). */
    public static function slugLivre(string $nome, ?int $ignorarId = null): string
    {
        $base = Str::slug($nome) ?: 'loja';
        $base = mb_substr($base, 0, 50);
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))->exists()
            || in_array($slug, self::SLUGS_RESERVADOS, true)) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    /** Nomes que não podem virar subdomínio de loja. */
    public const SLUGS_RESERVADOS = [
        'www', 'erp', 'app', 'admin', 'api', 'homolog', 'homolog-erp', 'mail', 'ftp', 'loja', 'lojas',
        'suporte', 'painel', 'crm', 'gersen', 'ia365', 'static', 'cdn',
    ];
}
