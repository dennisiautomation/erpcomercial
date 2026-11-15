{{-- Fileira de cartões (carrossel horizontal). Usada na vitrine em carrossel e nos "vistos recentemente". --}}
@foreach($produtos as $p) @include('loja.partials.cartao', ['p' => $p]) @endforeach
