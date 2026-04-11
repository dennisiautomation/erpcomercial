@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    {{-- Progress bar --}}
    <div class="d-flex justify-content-center mb-4">
        <div class="d-flex align-items-center gap-3 flex-wrap justify-content-center">
            @for($i = 1; $i <= 4; $i++)
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center {{ $step >= $i ? 'bg-primary text-white' : 'bg-light text-muted border' }}" style="width:40px;height:40px;font-weight:700">
                        @if($step > $i)
                            <i class="bi bi-check-lg"></i>
                        @else
                            {{ $i }}
                        @endif
                    </div>
                    <span class="ms-2 fw-semibold {{ $step >= $i ? 'text-primary' : 'text-muted' }}">{{ ['Empresa','Unidade','Usuario','Fiscal'][$i-1] }}</span>
                </div>
                @if($i < 4)
                    <div class="border-top mx-2" style="width:40px;border-width:2px!important;{{ $step > $i ? 'border-color:var(--accent)!important' : '' }}"></div>
                @endif
            @endfor
        </div>
    </div>

    {{-- Step content --}}
    @yield('step-content')
</div>
@endsection
