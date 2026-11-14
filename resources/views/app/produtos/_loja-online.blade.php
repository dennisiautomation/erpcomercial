{{-- Mais fotos do produto + marcas da loja online (14/11/2026).
     $produto = null no cadastro novo. As fotos adicionais valem para qualquer
     empresa; o interruptor "aparece na loja" só existe com a loja online liberada. --}}
@php
    $lojaOnlineAtiva = \App\Models\LojaOnline::ativaPara(auth()->user()->empresa_id ?? session('empresa_id'));
    $fotosExtras = $produto ? $produto->fotos : collect();
    $vagasFoto = \App\Models\ProdutoFoto::MAXIMO - $fotosExtras->count();
@endphp
<div class="col-12">
    <label for="fotos_extras" class="form-label">
        <i class="bi bi-images me-1"></i> Mais fotos <span class="text-muted fw-normal">(opcional)</span>
    </label>
    @if($fotosExtras->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 mb-2" id="fotosExtrasAtuais">
            @foreach($fotosExtras as $extra)
                <label class="position-relative border rounded p-1 text-center" style="width:92px;cursor:pointer;">
                    <img src="{{ Storage::url($extra->caminho) }}" class="rounded" style="width:80px;height:80px;object-fit:cover;" alt="Foto adicional {{ $loop->iteration }}">
                    <span class="form-check d-flex justify-content-center gap-1 mt-1 mb-0 small">
                        <input class="form-check-input" type="checkbox" name="remover_fotos[]" value="{{ $extra->id }}" id="remFoto{{ $extra->id }}">
                        <span class="text-danger">Tirar</span>
                    </span>
                </label>
            @endforeach
        </div>
    @endif
    @if($vagasFoto > 0)
        <input type="file" name="fotos_extras[]" id="fotos_extras" multiple accept="image/png,image/jpeg,image/webp"
               class="form-control @error('fotos_extras') is-invalid @enderror @error('fotos_extras.*') is-invalid @enderror" data-maximo="{{ $vagasFoto }}">
        @error('fotos_extras') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @error('fotos_extras.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">
            Selecione várias de uma vez. A "Foto do produto" acima é a capa; estas aparecem depois dela.
            {{ $vagasFoto === 1 ? 'Cabe mais 1 foto' : 'Cabem mais ' . $vagasFoto . ' fotos' }} (até 10 MB cada).
        </div>
        <div id="fotosExtrasPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
    @else
        <div class="form-text">Este produto já tem {{ \App\Models\ProdutoFoto::MAXIMO }} fotos adicionais. Marque "Tirar" em alguma para enviar outra.</div>
    @endif
</div>

@if($lojaOnlineAtiva)
<div class="col-12">
    <input type="hidden" name="loja_online_bloco" value="1">
    <div class="border rounded p-3" style="background:var(--primary-light, #eef2ff);">
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" name="loja_online" id="loja_online" value="1"
                   {{ old('loja_online', $produto?->loja_online) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="loja_online"><i class="bi bi-globe2 me-1"></i> Aparece na loja online</label>
        </div>
        <div class="form-text mt-0 mb-2">Desligado, o produto continua no PDV e no estoque normalmente — só não entra na vitrine da internet.</div>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" name="loja_online_destaque" id="loja_online_destaque" value="1"
                   {{ old('loja_online_destaque', $produto?->loja_online_destaque) ? 'checked' : '' }}>
            <label class="form-check-label" for="loja_online_destaque">Destaque <span class="text-muted">(aparece nas fileiras de destaque e no topo da lista)</span></label>
        </div>
    </div>
</div>
@endif

@once
@push('scripts')
<script>
(function () {
    var campo = document.getElementById('fotos_extras');
    var previa = document.getElementById('fotosExtrasPreview');
    if (campo && previa) {
        campo.addEventListener('change', function () {
            previa.innerHTML = '';
            var maximo = Number(campo.dataset.maximo) || 8;
            if (campo.files.length > maximo) {
                ERP.toast('Cabem só ' + maximo + ' foto(s) a mais neste produto. Selecione menos arquivos.', 'warning');
                campo.value = '';
                return;
            }
            Array.prototype.forEach.call(campo.files, function (arquivo) {
                var img = document.createElement('img');
                img.className = 'rounded border'; img.style.cssText = 'width:80px;height:80px;object-fit:cover;'; img.alt = 'Prévia';
                img.src = URL.createObjectURL(arquivo); previa.appendChild(img);
            });
        });
    }
    var naLoja = document.getElementById('loja_online'), destaque = document.getElementById('loja_online_destaque');
    if (naLoja && destaque) {
        var sinc = function () { destaque.disabled = !naLoja.checked; if (!naLoja.checked) destaque.checked = false; };
        naLoja.addEventListener('change', sinc); sinc();
    }
})();
</script>
@endpush
@endonce
