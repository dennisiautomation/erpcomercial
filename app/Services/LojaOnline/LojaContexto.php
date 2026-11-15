<?php

namespace App\Services\LojaOnline;

use App\Models\LojaOnline;

/**
 * A loja que está respondendo ESTE pedido HTTP e como os endereços dela são
 * montados (14/11/2026).
 *
 * A mesma loja responde por três caminhos:
 *   - /loja/{slug}/...            (dentro do ERP — sempre funciona, é a prévia);
 *   - {slug}.{dominio_base}/...   (subdomínio da plataforma);
 *   - dominio-do-cliente.com.br/  (domínio próprio).
 * Nos dois últimos o middleware ResolverLojaPorHost reescreve o caminho para o
 * primeiro, então as rotas são UMA só; quem muda é o endereço que a página
 * escreve nos links — por isso toda view usa $ctx->url(), nunca route().
 */
class LojaContexto
{
    public ?LojaOnline $loja = null;

    /** Veio por subdomínio/domínio próprio (links sem o prefixo /loja/{slug}). */
    public bool $porHost = false;

    /** Prévia do editor: lê o rascunho em vez do publicado. */
    public bool $rascunho = false;

    /** Loja fora do ar vista por alguém do ERP da empresa. */
    public bool $previa = false;

    public function url(string $caminho = ''): string
    {
        $caminho = ltrim($caminho, '/');
        $base = $this->porHost ? '' : '/loja/' . ($this->loja?->slug ?? '');
        $url = $base . ($caminho !== '' ? '/' . $caminho : '');
        if ($url === '') {
            $url = '/';
        }
        if ($this->rascunho) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'rascunho=1';
        }

        return $url;
    }

    /** Caminho sem o marcador de rascunho (para a paginação, que carrega a query sozinha). */
    public function caminho(string $caminho = ''): string
    {
        $rascunho = $this->rascunho;
        $this->rascunho = false;
        try {
            return $this->url($caminho);
        } finally {
            $this->rascunho = $rascunho;
        }
    }

    /** Endereço completo (com host) — para o link do pedido que vai por WhatsApp/e-mail. */
    public function urlAbsoluta(string $caminho = ''): string
    {
        return request()->getSchemeAndHttpHost() . $this->url($caminho);
    }

    /** Arquivo do storage público (foto de produto, banner, logo). */
    public static function arquivo(?string $caminho): ?string
    {
        return $caminho ? '/storage/' . ltrim($caminho, '/') : null;
    }
}
