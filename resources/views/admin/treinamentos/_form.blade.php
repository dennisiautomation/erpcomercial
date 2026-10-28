@php $t = $treinamento ?? null; @endphp

<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Informações Basicas</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('titulo') is-invalid @enderror"
                       id="titulo" name="titulo" value="{{ old('titulo', $t?->titulo) }}" required>
                @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label for="ordem" class="form-label">Ordem <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('ordem') is-invalid @enderror"
                       id="ordem" name="ordem" value="{{ old('ordem', $t?->ordem ?? 0) }}" min="0" required>
                @error('ordem') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label for="ativo" class="form-label">Status</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                           {{ old('ativo', $t?->ativo ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Ativo</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label mb-1">Quem vê este video <span class="text-danger">*</span></label>
                <div class="form-text mt-0 mb-2">Dono e Gerente veem todos os videos.</div>
                @php($marcados = old('perfis', $t?->perfis ?? array_map(fn ($p) => $p->value, \App\Models\Treinamento::PERFIS)))
                <div class="d-flex flex-wrap gap-3">
                    @foreach(\App\Models\Treinamento::PERFIS as $perfil)
                        <div class="form-check">
                            <input class="form-check-input @error('perfis') is-invalid @enderror" type="checkbox"
                                   id="perfil_{{ $perfil->value }}" name="perfis[]" value="{{ $perfil->value }}"
                                   {{ in_array($perfil->value, $marcados, true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="perfil_{{ $perfil->value }}">{{ $perfil->label() }}</label>
                        </div>
                    @endforeach
                </div>
                @error('perfis') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control @error('descricao') is-invalid @enderror"
                          id="descricao" name="descricao" rows="2">{{ old('descricao', $t?->descricao) }}</textarea>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Video</h6></div>
    <div class="card-body">
        @if($t?->arquivo)
            <video src="{{ route('admin.treinamentos.video', $t) }}" controls preload="metadata"
                   class="w-100 rounded mb-3 bg-dark" style="max-height: 360px;"></video>
        @endif

        <label for="videoArquivo" class="form-label">
            {{ $t?->arquivo ? 'Trocar o video' : 'Arquivo do video' }}
            @unless($t?->arquivo) <span class="text-danger">*</span> @endunless
        </label>
        <input type="file" class="form-control @error('arquivo') is-invalid @enderror" id="videoArquivo"
               accept=".mp4,.webm,.m4v,.mov,video/mp4,video/webm,video/quicktime">
        @error('arquivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">MP4, WEBM, M4V ou MOV. O video é enviado em partes de {{ (int) ($pedaco / 1048576) }} MB, sem limite de tamanho do arquivo — pode ser um vídeo de 1 GB ou mais.</div>
        <input type="hidden" name="arquivo" id="videoNome" value="{{ old('arquivo') }}">

        <div class="mt-3 d-none" id="videoProgresso">
            <div class="d-flex justify-content-between small mb-1">
                <span id="videoProgressoTexto">Enviando…</span>
                <span id="videoProgressoPct">0%</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="videoProgressoBarra" style="width: 0%"></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('admin.treinamentos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary" id="btnSalvarTreinamento">
        <i class="bi bi-check-lg me-1"></i> {{ $t ? 'Atualizar' : 'Criar' }} Treinamento
    </button>
</div>

@push('scripts')
<script>
/*
 * Envio em pedaços: o vídeo é fatiado em partes e cada parte vai num request
 * próprio para admin.treinamentos.upload. O tamanho da parte vem do servidor
 * (TreinamentoController::tamanhoDoPedaco, lido do php.ini em uso), então
 * nunca passa do que o PHP/nginx aceitam. Cada parte tem até 3 tentativas —
 * um vídeo de 1 GB são dezenas de requests, e uma oscilação de rede não pode
 * jogar tudo fora. Só depois da última parte o formulário é enviado, com o
 * nome do arquivo que o servidor devolveu.
 */
(function () {
    const PEDACO = {{ (int) $pedaco }};
    const TENTATIVAS = 3;
    const mb = (b) => (b / 1048576).toFixed(b >= 100 * 1048576 ? 0 : 1) + ' MB';
    const form = document.getElementById('formTreinamento');
    const input = document.getElementById('videoArquivo');
    const btn = document.getElementById('btnSalvarTreinamento');
    const caixa = document.getElementById('videoProgresso');
    const texto = document.getElementById('videoProgressoTexto');
    const pct = document.getElementById('videoProgressoPct');
    const barra = document.getElementById('videoProgressoBarra');
    let enviando = false;

    form.addEventListener('submit', async (e) => {
        const arquivo = input.files[0];
        if (!arquivo) return; // sem vídeo novo: salva só os dados
        e.preventDefault();
        if (enviando) return;
        enviando = true;
        btn.disabled = true;
        caixa.classList.remove('d-none');

        const uploadId = crypto.randomUUID();
        const total = Math.max(1, Math.ceil(arquivo.size / PEDACO));

        try {
            for (let i = 0; i < total; i++) {
                const dados = new FormData();
                dados.append('upload_id', uploadId);
                dados.append('indice', i);
                dados.append('total', total);
                dados.append('nome', arquivo.name);
                dados.append('pedaco', arquivo.slice(i * PEDACO, (i + 1) * PEDACO), 'pedaco');

                let json = null;
                for (let tentativa = 1; tentativa <= TENTATIVAS; tentativa++) {
                    try {
                        const resp = await fetch(@json(route('admin.treinamentos.upload')), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: dados,
                        });
                        json = await resp.json().catch(() => ({}));
                        // 4xx é erro de validação (formato, sessão): não adianta repetir
                        if (resp.status >= 400 && resp.status < 500) throw new Error(json.message || 'Falha ao enviar o vídeo.');
                        if (!resp.ok) throw Object.assign(new Error(json.message || `Servidor respondeu ${resp.status}.`), { repetir: true });
                        break;
                    } catch (err) {
                        const deRede = err instanceof TypeError; // fetch caiu: rede, proxy, timeout
                        if (!(deRede || err.repetir) || tentativa === TENTATIVAS) throw err;
                        texto.textContent = `Parte ${i + 1} falhou, tentando de novo (${tentativa + 1}/${TENTATIVAS})…`;
                        await new Promise(r => setTimeout(r, 1500 * tentativa));
                    }
                }

                const enviado = Math.min(arquivo.size, (i + 1) * PEDACO);
                const feito = Math.round((enviado / arquivo.size) * 100);
                barra.style.width = feito + '%';
                pct.textContent = feito + '%';
                texto.textContent = `Enviando parte ${i + 1} de ${total} — ${mb(enviado)} de ${mb(arquivo.size)}`;

                if (json.arquivo) document.getElementById('videoNome').value = json.arquivo;
            }

            texto.textContent = 'Vídeo enviado. Salvando…';
            input.value = '';
            form.submit();
        } catch (err) {
            enviando = false;
            btn.disabled = false;
            texto.textContent = err.message;
            barra.classList.add('bg-danger');
            ERP.toast(err.message, 'danger');
        }
    });
})();
</script>
@endpush
