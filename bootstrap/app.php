<?php

use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\EnsureSessionUnlocked;
use App\Http\Middleware\EnsureSingleSessionActive;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Railway's reverse proxy so Laravel correctly detects HTTPS
        $middleware->trustProxies(
            at: '*',
            headers: SymfonyRequest::HEADER_X_FORWARDED_FOR
                | SymfonyRequest::HEADER_X_FORWARDED_HOST
                | SymfonyRequest::HEADER_X_FORWARDED_PORT
                | SymfonyRequest::HEADER_X_FORWARDED_PROTO
        );

        // Enforce single active session, session inactivity timeout & session lock checks on web requests
        $middleware->web(append: [
            EnsureSingleSessionActive::class,
            \App\Http\Middleware\EnsureSessionInactivity::class,
            EnsureSessionUnlocked::class,
            \App\Http\Middleware\EnsureMaintenanceMode::class,
        ]);

        // Register route middleware aliases
        $middleware->alias([
            'role'         => RoleMiddleware::class,
            'permission'   => \App\Http\Middleware\EnsurePermission::class,
            'otp.verified' => EnsureOtpVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();