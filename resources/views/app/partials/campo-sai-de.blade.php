{{-- "Sai de" (05/11/2026, chamado DK Med): de qual estoque da loja o produto sai. Só aparece
     quando a loja tem mais de um estoque ativo, e é OBRIGATÓRIO: nada vem marcado, o vendedor
     escolhe. Recebe $estoquesSaida e $estoqueSelecionado. --}}
@if(($estoquesSaida ?? collect())->isNotEmpty())
    @php $saiDe = (int) old('estoque_id', $estoqueSelecionado ?? 0); @endphp
    <div class="mb-3">
        <label for="campo-estoque_id" class="form-label small fw-semibold">Sai de <span class="text-danger">*</span></label>
        <select id="campo-estoque_id" name="estoque_id" class="form-select @error('estoque_id') is-invalid @enderror" required>
            <option value="" @selected(! $saiDe)>Escolha de onde sai…</option>
            @foreach($estoquesSaida as $e)
                <option value="{{ $e->id }}" @selected($saiDe === (int) $e->id)>{{ $e->nome }}</option>
            @endforeach
        </select>
        @error('estoque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">De qual estoque o produto sai. A baixa é feita dele quando o pedido for faturado.</div>
    </div>
@endif
