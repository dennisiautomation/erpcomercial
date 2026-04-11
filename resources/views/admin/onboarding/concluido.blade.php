@extends('layouts.app')

@section('title', 'Onboarding Concluido')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body p-5">
                    {{-- Success icon --}}
                    <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-4" style="width:80px;height:80px">
                        <i class="bi bi-check-lg text-success" style="font-size:2.5rem"></i>
                    </div>

                    <h3 class="fw-bold mb-2">Empresa configurada com sucesso!</h3>
                    <p class="text-muted mb-4">O onboarding foi concluido. Veja o resumo abaixo.</p>

                    {{-- Summary --}}
                    <div class="text-start bg-light rounded p-4 mb-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Empresa</small>
                                <strong>{{ $empresa->razao_social }}</strong>
                                @if($empresa->nome_fantasia)
                                    <br><small class="text-muted">{{ $empresa->nome_fantasia }}</small>
                                @endif
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">CNPJ</small>
                                <strong>{{ $empresa->cnpj }}</strong>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Plano</small>
                                <strong>{{ ucfirst($empresa->plano) }}</strong>
                                @if($empresa->em_trial)
                                    <span class="badge bg-warning text-dark ms-1">Trial</span>
                                @endif
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Regime</small>
                                <strong>{{ $empresa->regime_tributario?->label() ?? '-' }}</strong>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Unidades</small>
                                <strong>{{ $empresa->unidades->count() }}</strong>
                                @foreach($empresa->unidades as $unidade)
                                    <br><small>{{ $unidade->nome }}</small>
                                @endforeach
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Usuarios</small>
                                <strong>{{ $empresa->users->count() }}</strong>
                                @foreach($empresa->users as $user)
                                    <br><small>{{ $user->name }} ({{ $user->perfil?->value ?? '-' }})</small>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="d-flex justify-content-center gap-3">
                        <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i> Ir para a empresa
                        </a>
                        <a href="{{ route('admin.onboarding.step1') }}" class="btn btn-outline-primary">
                            <i class="bi bi-plus-lg me-1"></i> Criar outra empresa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
