<tr class="{{ $level > 0 ? 'child-of-' . $conta->parent_id : '' }}" data-id="{{ $conta->id }}">
    <td>
        <span style="padding-left: {{ $level * 24 }}px;">
            @if($conta->children->isNotEmpty())
                <a href="javascript:void(0)" onclick="toggleChildren({{ $conta->id }})" class="text-decoration-none text-dark me-1">
                    <i id="toggle-icon-{{ $conta->id }}" class="bi bi-chevron-down small"></i>
                </a>
            @else
                <span style="width: 16px; display: inline-block;"></span>
            @endif
            <code>{{ $conta->codigo }}</code>
        </span>
    </td>
    <td>
        <span style="padding-left: {{ $level * 12 }}px;">
            {{ $conta->nome }}
        </span>
    </td>
    <td>
        @if($conta->tipo === 'receita')
            <span class="badge-status ativo">Receita</span>
        @elseif($conta->tipo === 'despesa')
            <span class="badge-status cancelado">Despesa</span>
        @else
            <span class="badge-status pendente">Custo</span>
        @endif
    </td>
    <td>
        @if($conta->natureza === 'sintetica')
            <span class="badge-status inativo">Sintetica</span>
        @else
            <span class="badge-status confirmado">Analitica</span>
        @endif
    </td>
    <td>
        @if($conta->ativo)
            <span class="badge-status ativa">Ativa</span>
        @else
            <span class="badge-status inativa">Inativa</span>
        @endif
    </td>
    <td class="text-end">
        <div class="action-btns justify-content-end">
            @if($conta->natureza === 'sintetica')
                <a href="{{ route('app.plano-contas.create', ['parent_id' => $conta->id]) }}" class="btn btn-sm btn-outline-success" title="Adicionar Subconta">
                    <i class="bi bi-plus-lg"></i>
                </a>
            @endif
            <a href="{{ route('app.plano-contas.edit', $conta) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                <i class="bi bi-pencil"></i>
            </a>
            <form method="POST" action="{{ route('app.plano-contas.destroy', $conta) }}" class="d-inline" onsubmit="return confirm('Confirma a exclusao?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </td>
</tr>
@if($conta->children->isNotEmpty())
    @foreach($conta->children as $child)
        @include('app.plano-contas._tree-row', ['conta' => $child, 'level' => $level + 1])
    @endforeach
@endif
