<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * PWA — manifest, service worker, ícones e página offline.
 *
 * Tudo é servido por rota (e não por arquivo em public/) de propósito:
 * o deploy padrão empacota só `app database resources routes config`,
 * e public/ fica congelado no que a imagem trouxe (armadilha 46).
 * Assim o PWA sobe por tar, como qualquer outra entrega.
 */
class PwaController extends Controller
{
    /** Bump manual: muda o nome do cache do service worker e força a atualização. */
    public const VERSAO = '1';

    private const ICONES = [
        'icone-192.png'          => 'image/png',
        'icone-512.png'          => 'image/png',
        'icone-maskable-512.png' => 'image/png',
        'icone-apple-180.png'    => 'image/png',
    ];

    public function manifest(): Response
    {
        $manifest = [
            'id'          => '/app/dashboard',
            'name'        => 'ERP Comercial IA365',
            'short_name'  => 'ERP Comercial',
            'description' => 'Vendas, PDV, estoque, financeiro e fiscal da sua loja.',
            'lang'        => 'pt-BR',
            'dir'         => 'ltr',
            'start_url'   => '/app/dashboard',
            'scope'       => '/',
            'display'     => 'standalone',
            // se o navegador não suportar standalone, degrada em ordem
            'display_override'  => ['standalone', 'minimal-ui', 'browser'],
            'orientation'       => 'any',
            'background_color'  => '#f1f5f9',  // --body-bg
            'theme_color'       => '#1e293b',  // --sidebar-bg (moldura da janela no Windows)
            'categories'        => ['business', 'productivity', 'finance'],
            'prefer_related_applications' => false,
            'icons' => [
                [
                    'src'     => url('/pwa/icone-192.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => url('/pwa/icone-512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => url('/pwa/icone-maskable-512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            // Jump list do atalho na barra de tarefas do Windows (botão direito no ícone)
            'shortcuts' => [
                [
                    'name'       => 'PDV',
                    'short_name' => 'PDV',
                    'description'=> 'Abrir o ponto de venda',
                    'url'        => '/app/pdv',
                    'icons'      => [['src' => url('/pwa/icone-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name'       => 'Vendas',
                    'short_name' => 'Vendas',
                    'description'=> 'Lista de vendas',
                    'url'        => '/app/vendas',
                    'icons'      => [['src' => url('/pwa/icone-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name'       => 'Dashboard',
                    'short_name' => 'Dashboard',
                    'description'=> 'Painel do dia',
                    'url'        => '/app/dashboard',
                    'icons'      => [['src' => url('/pwa/icone-192.png'), 'sizes' => '192x192']],
                ],
            ],
        ];

        $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return response($json, 200, [
            'Content-Type'  => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Service worker — servido da raiz para ter escopo `/`.
     *
     * Regra de ouro: NADA de HTML de /app ou /admin entra em cache (base
     * multi-tenant, tela de um usuário não pode reaparecer para outro).
     * O cache guarda só a casca offline e os estáticos versionados.
     */
    public function serviceWorker(): Response
    {
        // Chave desligada: o SW instalado nos navegadores se apaga sozinho
        if (! config('pwa.ativo')) {
            return $this->resposta(<<<'JS'
/* PWA desligado (PWA_ATIVO=false) — este service worker se remove. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((nomes) => Promise.all(nomes.map((n) => caches.delete(n))))
            .then(() => self.registration.unregister())
            .then(() => self.clients.matchAll())
            .then((cs) => cs.forEach((c) => c.navigate(c.url)))
    );
});
JS);
        }

        $cache   = 'erp-pwa-v' . self::VERSAO;
        $offline = url('/offline');

        $precache = json_encode([
            $offline,
            url('/pwa/icone-192.png'),
            url('/pwa/icone-512.png'),
        ], JSON_UNESCAPED_SLASHES);

        $js = <<<JS
/* ERP Comercial IA365 — service worker (gerado por PwaController) */
const CACHE   = '{$cache}';
const OFFLINE = '{$offline}';
const PRECACHE = {$precache};

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((nomes) => Promise.all(nomes.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

// Estáticos que podem ficar em cache (nunca HTML de tela)
function estatico(url) {
    return /^\/(css|js|build|pwa)\//.test(url.pathname) || url.pathname === '/favicon.ico';
}

self.addEventListener('fetch', (e) => {
    const req = e.request;

    // POST/PUT/DELETE e outras origens (CDN do Bootstrap, Focus, etc.) passam direto
    if (req.method !== 'GET') return;

    let url;
    try { url = new URL(req.url); } catch (_) { return; }
    if (url.origin !== self.location.origin) return;

    // Navegação: rede primeiro; sem rede, casca offline
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req).catch(() => caches.match(OFFLINE).then((r) => r || Response.error()))
        );
        return;
    }

    // Estático: cache primeiro, revalidando em segundo plano
    if (estatico(url)) {
        e.respondWith(
            caches.match(req).then((cacheado) => {
                const rede = fetch(req).then((res) => {
                    if (res && res.ok && res.type === 'basic') {
                        const copia = res.clone();
                        caches.open(CACHE).then((c) => c.put(req, copia));
                    }
                    return res;
                }).catch(() => cacheado);
                return cacheado || rede;
            })
        );
        return;
    }

    // Todo o resto (telas, AJAX, relatórios): sem interferência
});

// Permite que a página mande o SW novo assumir na hora
self.addEventListener('message', (e) => {
    if (e.data === 'assumir') self.skipWaiting();
});
JS;

        return $this->resposta($js);
    }

    private function resposta(string $js): Response
    {
        return response($js, 200, [
            'Content-Type'           => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            // sem cache: é assim que o navegador enxerga uma versão nova do SW
            'Cache-Control'          => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function icone(string $arquivo): BinaryFileResponse
    {
        abort_unless(isset(self::ICONES[$arquivo]), 404);

        $caminho = resource_path('pwa/' . $arquivo);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Content-Type'  => self::ICONES[$arquivo],
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function offline()
    {
        return response()->view('pwa.offline')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
