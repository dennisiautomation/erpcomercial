{{-- Botões das redes sociais cadastradas (Configurações). $classe opcional. --}}
@php
    $redes = array_filter([
        'instagram' => filled($loja->opcao('instagram')) ? 'https://instagram.com/' . ltrim($loja->opcao('instagram'), '@') : null,
        'facebook' => filled($loja->opcao('facebook')) ? 'https://facebook.com/' . ltrim($loja->opcao('facebook'), '@/') : null,
        'tiktok' => filled($loja->opcao('tiktok')) ? 'https://tiktok.com/@' . ltrim($loja->opcao('tiktok'), '@') : null,
        'youtube' => filled($loja->opcao('youtube')) ? 'https://youtube.com/@' . ltrim($loja->opcao('youtube'), '@') : null,
        'whatsapp' => filled($loja->opcao('whatsapp')) ? 'https://wa.me/55' . preg_replace('/\D/', '', $loja->opcao('whatsapp')) : null,
    ]);
    $nomes = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp'];
@endphp
@if($redes)
<ul class="lj-redes {{ $classe ?? '' }}">
    @foreach($redes as $rede => $url)
        <li><a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $nomes[$rede] }} da loja"><i class="bi bi-{{ $rede }}" aria-hidden="true"></i>@if(!empty($comNome))<span>{{ $nomes[$rede] }}</span>@endif</a></li>
    @endforeach
</ul>
@endif
