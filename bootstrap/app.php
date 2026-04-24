<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confia em qualquer proxy (estamos sempre atrás do Caddy na VPS).
        // Caddy envia X-Forwarded-Proto=https — o Laravel então gera URLs
        // HTTPS corretas e form actions funcionam.
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'unidade' => \App\Http\Middleware\EnsureUnidadeSelected::class,
            'plano' => \App\Http\Middleware\CheckPlano::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
