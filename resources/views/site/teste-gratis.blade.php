@extends('site._teste-base')
@section('titulo', 'Teste grátis')
@section('css')
.tg{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:clamp(1.2rem,3vw,2.4rem);align-items:start}
.tg__lado{background:var(--navy);color:var(--onNavy);border-radius:22px;padding:clamp(1.5rem,3vw,2.2rem);position:sticky;top:1.2rem}
.tg__lado h1{font-size:clamp(1.7rem,3vw,2.3rem);font-weight:700;line-height:1.1;margin-bottom:.8rem}
.tg__lado>p{color:var(--onNavy2);font-size:1.02rem;text-wrap:pretty}
.tg__lista{list-style:none;margin-top:1.4rem;display:grid;gap:.7rem;font-size:.98rem}
.tg__lista li{display:flex;gap:.6rem}
.tg__lista svg{flex:none;width:1.1em;height:1.1em;margin-top:.25em;color:#8fa2ff}
.tg__resumo{margin-top:1.6rem;padding-top:1.3rem;border-top:1px solid rgba(238,240,251,.16);font-size:.95rem;color:var(--onNavy2)}
.tg__resumo b{display:block;color:var(--onNavy);font-size:1.25rem;font-weight:700;margin-bottom:.15rem}
.form{background:#fff;border-radius:22px;box-shadow:var(--shadow);padding:clamp(1.3rem,3vw,2rem)}
.form fieldset{border:0;margin-bottom:1.5rem}
.form legend{font-size:1.12rem;font-weight:700;margin-bottom:.9rem;padding:0}
.field{margin-bottom:.85rem}
.row{display:grid;gap:.75rem}
.row--2{grid-template-columns:1fr 1fr}
.row--end{grid-template-columns:2fr 1fr}
.row--cid{grid-template-columns:2fr 2fr 1fr}
.field label{display:block;font-size:.86rem;font-weight:600;margin-bottom:.3rem}
.field small{display:block;font-size:.8rem;color:var(--ink2);margin-top:.25rem}
.field input,.field select{width:100%;font:inherit;font-size:1rem;padding:.7rem .8rem;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);min-height:48px}
.field input:focus,.field select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,113,227,.18)}
.field input.is-err,.field select.is-err{border-color:var(--badD)}
.erro{color:var(--badD);font-size:.84rem;font-weight:500;margin-top:.3rem}
.alerta{background:rgba(255,59,48,.08);color:var(--badD);border-radius:12px;padding:.8rem 1rem;font-size:.94rem;font-weight:500;margin-bottom:1.2rem}
.planos{display:grid;gap:.55rem}
.planos label{display:flex;align-items:center;gap:.8rem;border:1px solid var(--line);border-radius:14px;padding:.8rem 1rem;cursor:pointer;transition:border-color .18s var(--ease),background .18s var(--ease)}
.planos input{accent-color:var(--blue);width:1.15rem;height:1.15rem;flex:none}
.planos label:has(input:checked){border-color:var(--blue);background:rgba(0,113,227,.05);box-shadow:inset 0 0 0 1px var(--blue)}
.planos b{display:block;font-size:1rem}
.planos span span{font-size:.86rem;color:var(--ink2)}
.planos em{margin-left:auto;font-style:normal;font-weight:700;white-space:nowrap;font-variant-numeric:tabular-nums}
.planos em small{font-weight:500;color:var(--ink2);font-size:.8rem}
.aceite{display:flex;gap:.6rem;align-items:flex-start;font-size:.92rem;margin-bottom:1.1rem}
.aceite input{accent-color:var(--blue);width:1.15rem;height:1.15rem;margin-top:.2rem;flex:none}
.fine{font-size:.82rem;color:var(--ink2);margin-top:.8rem;text-align:center}
@media(max-width:860px){.tg{grid-template-columns:1fr}.tg__lado{position:static}}
@media(max-width:520px){.row--2,.row--end,.row--cid{grid-template-columns:1fr}.planos label{flex-wrap:wrap}.planos em{margin-left:1.95rem}}
@endsection

@section('conteudo')
@php
  $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
  $brl = fn ($v) => 'R$ ' . number_format((float) $v, fmod((float) $v, 1) ? 2 : 0, ',', '.');
  $nomePlano = fn ($p) => $p->nome === 'Basico' ? 'Básico' : $p->nome;
  $lojasDe = fn ($p) => (int) $p->max_unidades >= 999 ? 'Lojas sem limite' : ((int) $p->max_unidades <= 1 ? '1 loja' : 'Até ' . (int) $p->max_unidades . ' lojas');
  $marcado = old('plano', $escolhido->slug);
  $campo = function (string $nome) use ($errors) { return $errors->has($nome) ? 'is-err' : ''; };
@endphp
<div class="wrap tg">
  <aside class="tg__lado">
    <h1>Teste o sistema inteiro, de graça.</h1>
    <p>Você cria a conta agora e já entra. Sem cartão e sem compromisso: se não servir, é só não continuar.</p>
    <ul class="tg__lista">
      <li>{!! $check !!} Venda, estoque, caixa, financeiro e nota fiscal liberados</li>
      <li>{!! $check !!} Seus dados continuam com você se decidir ficar</li>
      <li>{!! $check !!} Nossa equipe ajuda pelo WhatsApp durante o teste</li>
    </ul>
    <div class="tg__resumo" aria-live="polite">
      <b id="resDias">{{ (int) ($escolhido->dias_trial ?: 14) }} dias grátis</b>
      <span id="resPlano">Plano {{ $nomePlano($escolhido) }}. Depois, {{ $brl($escolhido->preco_mensal) }} por mês, só se você quiser continuar.</span>
    </div>
  </aside>

  <form class="form" method="POST" action="{{ route('site.teste.store') }}" id="tgForm" novalidate>
    @csrf
    <input type="text" name="site" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

    @if($errors->any())
      <div class="alerta" role="alert">Confira os campos marcados abaixo e envie de novo.</div>
    @endif

    <fieldset>
      <legend>1. Escolha o plano</legend>
      <div class="planos">
        @foreach($planos as $p)
          <label>
            <input type="radio" name="plano" value="{{ $p->slug }}" {{ $marcado === $p->slug ? 'checked' : '' }}
                   data-dias="{{ (int) ($p->dias_trial ?: 14) }}" data-nome="{{ $nomePlano($p) }}" data-preco="{{ $brl($p->preco_mensal) }}">
            <span><b>{{ $nomePlano($p) }}</b><span>{{ $lojasDe($p) }} · {{ (int) ($p->dias_trial ?: 14) }} dias grátis</span></span>
            <em>{{ $brl($p->preco_mensal) }} <small>por mês</small></em>
          </label>
        @endforeach
      </div>
      @error('plano')<p class="erro">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset>
      <legend>2. Seu acesso</legend>
      <div class="field">
        <label for="nome">Seu nome</label>
        <input class="{{ $campo('nome') }}" id="nome" name="nome" type="text" autocomplete="name" value="{{ old('nome') }}" required>
        @error('nome')<p class="erro">{{ $message }}</p>@enderror
      </div>
      <div class="row row--2">
        <div class="field">
          <label for="email">E-mail</label>
          <input class="{{ $campo('email') }}" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>
          <small>É o seu login. Vamos enviar um link de confirmação.</small>
          @error('email')<p class="erro">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="whatsapp">WhatsApp</label>
          <input class="{{ $campo('whatsapp') }}" id="whatsapp" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" placeholder="(86) 90000-0000" value="{{ old('whatsapp') }}" required>
          @error('whatsapp')<p class="erro">{{ $message }}</p>@enderror
        </div>
      </div>
      <div class="row row--2">
        <div class="field">
          <label for="senha">Crie uma senha</label>
          <input class="{{ $campo('senha') }}" id="senha" name="senha" type="password" autocomplete="new-password" minlength="8" required>
          <small>Pelo menos 8 caracteres.</small>
          @error('senha')<p class="erro">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="senha_confirmation">Repita a senha</label>
          <input id="senha_confirmation" name="senha_confirmation" type="password" autocomplete="new-password" minlength="8" required>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>3. Sua empresa</legend>
      <div class="row row--2">
        <div class="field">
          <label for="cnpj">CNPJ</label>
          <input class="{{ $campo('cnpj') }}" id="cnpj" name="cnpj" type="text" inputmode="text" maxlength="18" placeholder="00.000.000/0000-00" value="{{ old('cnpj') }}" required>
          @error('cnpj')<p class="erro">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="regime_tributario">Regime tributário</label>
          <select class="{{ $campo('regime_tributario') }}" id="regime_tributario" name="regime_tributario" required>
            @foreach($regimes as $r)
              <option value="{{ $r->value }}" {{ old('regime_tributario', 'simples_nacional') === $r->value ? 'selected' : '' }}>{{ ['simples_nacional' => 'Simples Nacional (inclui MEI)', 'lucro_presumido' => 'Lucro Presumido', 'lucro_real' => 'Lucro Real'][$r->value] ?? $r->value }}</option>
            @endforeach
          </select>
          <small>Na dúvida, deixe Simples Nacional. Dá para mudar depois.</small>
          @error('regime_tributario')<p class="erro">{{ $message }}</p>@enderror
        </div>
      </div>
      <div class="field">
        <label for="razao_social">Razão social</label>
        <input class="{{ $campo('razao_social') }}" id="razao_social" name="razao_social" type="text" autocomplete="organization" value="{{ old('razao_social') }}" required>
        @error('razao_social')<p class="erro">{{ $message }}</p>@enderror
      </div>
      <div class="field">
        <label for="nome_fantasia">Nome da loja <span style="font-weight:400;color:var(--ink2)">(como os clientes conhecem)</span></label>
        <input class="{{ $campo('nome_fantasia') }}" id="nome_fantasia" name="nome_fantasia" type="text" value="{{ old('nome_fantasia') }}">
        @error('nome_fantasia')<p class="erro">{{ $message }}</p>@enderror
      </div>
      <div class="row row--end">
        <div class="field">
          <label for="cep">CEP</label>
          <input class="{{ $campo('cep') }}" id="cep" name="cep" type="text" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="postal-code" value="{{ old('cep') }}" required>
          <small id="cepMsg">Digite o CEP e o endereço é preenchido sozinho.</small>
          @error('cep')<p class="erro">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="numero">Número</label>
          <input class="{{ $campo('numero') }}" id="numero" name="numero" type="text" maxlength="20" value="{{ old('numero') }}" required>
          @error('numero')<p class="erro">{{ $message }}</p>@enderror
        </div>
      </div>
      <div class="row row--2">
        <div class="field">
          <label for="logradouro">Endereço (rua, avenida)</label>
          <input class="{{ $campo('logradouro') }}" id="logradouro" name="logradouro" type="text" autocomplete="address-line1" value="{{ old('logradouro') }}" required>
          @error('logradouro')<p class="erro">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="complemento">Complemento <span style="font-weight:400;color:var(--ink2)">(se tiver)</span></label>
          <input id="complemento" name="complemento" type="text" maxlength="100" value="{{ old('complemento') }}">
        </div>
      </div>
      <div class="row row--cid">
        <div class="field">
          <label for="bairro">Bairro</label>
          <input class="{{ $campo('bairro') }}" id="bairro" name="bairro" type="text" value="{{ old('bairro') }}" required>
          @error('bairro')<p class="erro">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="cidade">Cidade</label>
          <input class="{{ $campo('cidade') }}" id="cidade" name="cidade" type="text" value="{{ old('cidade') }}" required>
          @error('cidade')<p class="erro">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="uf">Estado</label>
          <input class="{{ $campo('uf') }}" id="uf" name="uf" type="text" maxlength="2" placeholder="PI" style="text-transform:uppercase" value="{{ old('uf') }}" required>
          @error('uf')<p class="erro">{{ $message }}</p>@enderror
        </div>
      </div>
    </fieldset>

    <label class="aceite">
      <input type="checkbox" name="aceite" value="1" {{ old('aceite') ? 'checked' : '' }} required>
      <span>Concordo em receber contato da IA365 sobre o meu teste e sei que, ao fim do período grátis, o acesso é pausado até eu contratar um plano.</span>
    </label>
    @error('aceite')<p class="erro" style="margin:-.6rem 0 1rem">{{ $message }}</p>@enderror

    <button class="pill pill--block" type="submit" id="tgEnviar">Criar minha conta e testar grátis</button>
    <p class="fine">Sem cartão de crédito. A conta é criada quando você confirma o e-mail.</p>
  </form>
</div>
@endsection

@section('js')
<script>
(function () {
  var so = function (v) { return (v || '').replace(/\D/g, ''); };
  var cnpj = document.getElementById('cnpj');
  cnpj.addEventListener('input', function () {
    // aceita o CNPJ novo com letras: só tira o que não é letra nem número e põe a máscara
    var v = cnpj.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 14), o = '';
    for (var i = 0; i < v.length; i++) { o += ({2: '.', 5: '.', 8: '/', 12: '-'}[i] || '') + v[i]; }
    cnpj.value = o;
  });
  var cep = document.getElementById('cep'), msg = document.getElementById('cepMsg');
  cep.addEventListener('input', function () {
    var v = so(cep.value).slice(0, 8);
    cep.value = v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v;
    if (v.length !== 8) return;
    msg.textContent = 'Buscando o endereço...';
    fetch('https://viacep.com.br/ws/' + v + '/json/').then(function (r) { return r.json(); }).then(function (d) {
      if (d.erro) { msg.textContent = 'CEP não encontrado. Preencha o endereço à mão.'; return; }
      var p = {logradouro: d.logradouro, bairro: d.bairro, cidade: d.localidade, uf: d.uf};
      Object.keys(p).forEach(function (k) { var el = document.getElementById(k); if (el && p[k]) el.value = p[k]; });
      msg.textContent = 'Endereço preenchido. Confira e informe o número.';
      document.getElementById('numero').focus();
    }).catch(function () { msg.textContent = 'Não deu para buscar o CEP agora. Preencha o endereço à mão.'; });
  });
  var whats = document.getElementById('whatsapp');
  whats.addEventListener('input', function () {
    var v = so(whats.value).slice(0, 11);
    whats.value = v.length > 6 ? '(' + v.slice(0, 2) + ') ' + v.slice(2, v.length - 4) + '-' + v.slice(-4) : (v.length > 2 ? '(' + v.slice(0, 2) + ') ' + v.slice(2) : v);
  });
  document.querySelectorAll('input[name=plano]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.getElementById('resDias').textContent = r.dataset.dias + ' dias grátis';
      document.getElementById('resPlano').textContent = 'Plano ' + r.dataset.nome + '. Depois, ' + r.dataset.preco + ' por mês, só se você quiser continuar.';
    });
  });
  document.getElementById('tgForm').addEventListener('submit', function () {
    var b = document.getElementById('tgEnviar'); b.disabled = true; b.textContent = 'Criando o seu cadastro...';
  });
})();
</script>
@endsection
