<?php

namespace App\Services\LojaOnline;

use App\Models\LojaOnline;
use Illuminate\Support\Facades\Http;

/**
 * Domínio próprio da loja online, do jeito simples (16/11/2026).
 *
 * O lojista digita o domínio, a tela mostra QUAL registro criar no site onde ele
 * comprou o domínio e o botão "Conferir" diz em que pé está:
 *
 *   aguardando → o domínio ainda não aponta para o servidor da loja;
 *   dns_ok     → aponta certo; falta o certificado de segurança (o cadeado);
 *   ativo      → abre com cadeado: a loja já responde por ele.
 *
 * O certificado NÃO é emitido daqui (o app não mexe no nginx do servidor): quem
 * emite é a rotina do servidor (scripts/loja-dominios-host.sh), que pergunta ao
 * `php artisan loja:dominios` quais domínios estão prontos. Onde ela não está
 * instalada, a equipe emite à mão e marca "no ar" no admin.
 */
class DominioLoja
{
    public const SITUACOES = [
        'aguardando' => ['Aguardando o apontamento', 'warning'],
        'dns_ok' => ['Apontado — falta o cadeado', 'info'],
        'ativo' => ['No ar', 'success'],
    ];

    /** Terminações em que o "domínio principal" tem três partes (loja.com.br). */
    private const SUFIXOS_DUPLOS = ['com.br', 'net.br', 'org.br', 'ind.br', 'art.br', 'eco.br', 'app.br', 'dev.br', 'log.br', 'tec.br', 'srv.br', 'far.br', 'co.uk', 'com.pt', 'com.ar'];

    /** Trocados nos testes: fn (string $dominio): string[] de IPs  e  fn (string $dominio): bool. */
    public static $resolver = null;

    public static $https = null;

    /** Tira http://, barra, caminho e espaços do que o lojista colou. */
    public static function normalizar(?string $texto): string
    {
        $d = mb_strtolower(trim((string) $texto));
        $d = preg_replace('#^[a-z]+://#', '', $d);
        $d = preg_replace('#[/?\#].*$#', '', $d);

        return trim(preg_replace('/\s+/', '', $d), '.');
    }

    /** Mensagem de erro para o domínio digitado, ou null se serve. */
    public static function erro(string $dominio, LojaOnline $loja): ?string
    {
        if (! preg_match('/^([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$/', $dominio) || mb_strlen($dominio) > 190) {
            return 'Digite só o domínio, como www.sualoja.com.br — sem espaço e sem acento.';
        }
        $hostsErp = array_filter(array_merge((array) config('loja_online.hosts_erp', []), [parse_url((string) config('app.url'), PHP_URL_HOST)]));
        $base = LojaOnline::dominioBase();
        if (in_array($dominio, $hostsErp, true) || ($base !== '' && ($dominio === $base || str_ends_with($dominio, '.' . $base)))) {
            return 'Este endereço é da própria plataforma. Use um domínio seu.';
        }
        if (LojaOnline::where('dominio', $dominio)->where('id', '!=', $loja->id)->exists()) {
            return 'Este domínio já está em uso por outra loja.';
        }

        return null;
    }

    /** IPs do servidor da loja (LOJA_ONLINE_IP; sem a chave, o IP do endereço do ERP). */
    public static function ipsDoServidor(): array
    {
        $fixos = array_filter(array_map('trim', explode(',', (string) config('loja_online.ip_servidor', ''))));
        if ($fixos !== []) {
            return array_values($fixos);
        }
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host !== '' && ! filter_var($host, FILTER_VALIDATE_IP) ? self::resolver($host) : array_filter([$host]);
    }

    /** Nome para onde um CNAME deve apontar (LOJA_ONLINE_CNAME; sem a chave, o endereço do ERP). */
    public static function alvoCname(): string
    {
        return trim((string) config('loja_online.cname_alvo', '')) ?: (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    /** O domínio é o principal (sualoja.com.br) ou tem algo na frente (www.sualoja.com.br)? */
    public static function principal(string $dominio): bool
    {
        $partes = explode('.', $dominio);
        $duplo = in_array(implode('.', array_slice($partes, -2)), self::SUFIXOS_DUPLOS, true);

        return count($partes) <= ($duplo ? 3 : 2);
    }

    /**
     * Os registros que o lojista precisa criar onde comprou o domínio.
     *
     * @return array<int, array{tipo: string, nome: string, valor: string, nota: string}>
     */
    public static function registros(string $dominio): array
    {
        $ip = self::ipsDoServidor()[0] ?? '';
        if (self::principal($dominio)) {
            return [['tipo' => 'A', 'nome' => '@', 'valor' => $ip,
                'nota' => 'Em alguns sites o nome fica em branco, ou é o próprio domínio, em vez de @.']];
        }
        $frente = implode('.', array_slice(explode('.', $dominio), 0, self::principalPartes($dominio)));

        // Sem um nome para apontar (o endereço do ERP é um IP): registro A direto.
        if (filter_var(self::alvoCname(), FILTER_VALIDATE_IP) || self::alvoCname() === '') {
            return [['tipo' => 'A', 'nome' => $frente, 'valor' => $ip, 'nota' => 'O nome é só a parte da frente do domínio, sem o resto.']];
        }

        return [['tipo' => 'CNAME', 'nome' => $frente, 'valor' => self::alvoCname(),
            'nota' => 'Se o site não aceitar CNAME, crie um registro do tipo A com o mesmo nome e o valor ' . $ip . '.']];
    }

    /** Quantas partes vêm antes do domínio principal (www.loja.com.br → 1). */
    private static function principalPartes(string $dominio): int
    {
        $partes = explode('.', $dominio);
        $duplo = in_array(implode('.', array_slice($partes, -2)), self::SUFIXOS_DUPLOS, true);

        return max(1, count($partes) - ($duplo ? 3 : 2));
    }

    /** @return string[] IPs para onde o domínio aponta hoje. */
    public static function resolver(string $dominio): array
    {
        if (self::$resolver) {
            return array_values((array) (self::$resolver)($dominio));
        }
        $ips = @gethostbynamel($dominio);

        return is_array($ips) ? array_values(array_unique($ips)) : [];
    }

    /** O domínio já abre com cadeado válido? */
    public static function comCadeado(string $dominio): bool
    {
        if (self::$https) {
            return (bool) (self::$https)($dominio);
        }
        try {
            return Http::timeout(6)->connectTimeout(4)->withoutRedirecting()->get('https://' . $dominio . '/')->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Confere o domínio da loja e grava em que pé está.
     *
     * @return array{status: string, mensagem: string}
     */
    public static function conferir(LojaOnline $loja): array
    {
        $dominio = (string) $loja->dominio;
        if ($dominio === '') {
            return ['status' => '', 'mensagem' => 'Nenhum domínio informado.'];
        }

        $esperados = self::ipsDoServidor();
        $achados = self::resolver($dominio);

        if ($achados === []) {
            $status = 'aguardando';
            $obs = 'Ainda não achamos o apontamento. Depois de criar o registro, pode levar de alguns minutos a algumas horas para valer.';
        } elseif (array_intersect($achados, $esperados) === []) {
            $status = 'aguardando';
            $obs = 'O domínio aponta para ' . implode(', ', array_slice($achados, 0, 3)) . ', que não é o servidor da loja'
                . ($esperados ? ' (' . $esperados[0] . ')' : '') . '. Confira o registro criado.';
        } elseif (self::comCadeado($dominio)) {
            $status = 'ativo';
            $obs = 'Tudo certo: a loja já abre por este domínio, com cadeado.';
        } elseif ($loja->dominio_status === 'ativo') {
            // Já estava no ar (marcado pela rotina do servidor ou pela equipe): não rebaixa por causa do teste do cadeado.
            $status = 'ativo';
            $obs = 'Tudo certo: a loja já abre por este domínio.';
        } else {
            $status = 'dns_ok';
            $obs = config('loja_online.certificado_automatico')
                ? 'Apontamento certo. O cadeado de segurança sai sozinho em até 30 minutos — não precisa fazer mais nada.'
                : 'Apontamento certo. Falta o cadeado de segurança, que a equipe da IA365 emite — peça pelo suporte informando este domínio.';
        }

        $loja->forceFill(['dominio_status' => $status, 'dominio_obs' => $obs, 'dominio_conferido_em' => now()])->save();

        return ['status' => $status, 'mensagem' => $obs];
    }
}
