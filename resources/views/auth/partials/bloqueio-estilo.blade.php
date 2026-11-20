{{-- Visual único das telas de bloqueio (acesso suspenso e teste encerrado), 20/11/2026.
     Mesmo mundo da tela de login: ardósia escura, cartão central, cor do sistema nos botões. --}}
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center;
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
    }
    .wrapper { width: 100%; max-width: 520px; padding: 1rem; }
    .card-suspenso {
        background: #1e293b; border: 1px solid #334155; border-radius: 1rem; padding: 2.25rem 1.75rem 1.75rem;
        box-shadow: 0 24px 48px rgba(0,0,0,0.4); text-align: center;
    }
    @media (max-width: 420px) { .card-suspenso { padding: 1.75rem 1.1rem 1.25rem; } }
    .icone {
        width: 64px; height: 64px; border-radius: 1rem; margin: 0 auto 1.25rem;
        display: flex; align-items: center; justify-content: center;
        background: #dc2626; box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
    .icone.aviso { background: #4f46e5; }
    .icone i { font-size: 1.8rem; color: #fff; }
    h1 { font-size: 1.35rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.5rem; text-wrap: balance; }
    p.sub { color: #cbd5e1; font-size: 0.95rem; margin: 0 0 1.5rem; line-height: 1.6; text-wrap: pretty; }
    p.sub strong { color: #f1f5f9; }
    .faturas {
        background: #0f172a; border: 1px solid #334155;
        border-radius: 0.75rem; padding: 1rem 1.1rem; margin-bottom: 1rem; text-align: left;
    }
    .faturas .rotulo { color: #94a3b8; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem; }
    .faturas .item { padding: 0.5rem 0 0.75rem; color: #cbd5e1; font-size: 0.9rem; }
    .faturas .item + .item { border-top: 1px solid #334155; padding-top: 0.75rem; }
    .faturas .item .valor { display: flex; flex-wrap: wrap; gap: 0.25rem 0.75rem; align-items: baseline; margin-top: 0.15rem; }
    .faturas .item .valor strong { color: #f1f5f9; font-size: 1.05rem; }
    .faturas .item .valor small { color: #fca5a5; }
    .btn-bloqueio, .btn-bloqueio-sec, .btn-sair {
        display: flex; align-items: center; justify-content: center; gap: 0.4rem; width: 100%; min-height: 2.75rem;
        padding: 0.6rem 1rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.95rem; text-decoration: none; cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease;
    }
    .btn-bloqueio { background: #4f46e5; border: 1px solid #4f46e5; color: #fff; }
    .btn-bloqueio:hover { background: #4338ca; border-color: #4338ca; color: #fff; }
    .btn-bloqueio-sec, .btn-sair { background: transparent; border: 1px solid #475569; color: #e2e8f0; }
    .btn-bloqueio-sec:hover, .btn-sair:hover { background: #334155; color: #fff; }
    .btn-bloqueio:focus-visible, .btn-bloqueio-sec:focus-visible, .btn-sair:focus-visible, .contato a:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 2px; }
    .acoes { display: grid; gap: 0.6rem; margin-bottom: 1.25rem; }
    .contato { color: #94a3b8; font-size: 0.875rem; margin: 0 0 1.25rem; line-height: 1.6; }
    .contato a { color: #a5b4fc; display: inline-block; padding: 0.35rem 0; }
    .btn-sair { width: auto; margin: 0 auto; padding: 0.6rem 1.75rem; }
</style>
