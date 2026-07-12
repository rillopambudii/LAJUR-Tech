<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the active tenant on every web request (runs after the session
        // is started, so the authenticated user is available).
        $middleware->web(append: [
            \App\Http\Middleware\IdentifyTenant::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        // Payment gateway posts server-to-server without a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'payment/midtrans/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // A stale/expired session makes CSRF fail with "Page Expired" (419). For a
        // public no-login form like Lacak Pesanan that dead-ends the customer, so
        // instead bounce them back with their input and a friendly retry message.
        // NOTE: the framework converts TokenMismatchException into HttpException(419)
        // before render callbacks run, so we match on the 419 and its previous.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! ($e->getPrevious() instanceof TokenMismatchException)) {
                return null; // not a CSRF failure — let the default handler render it
            }

            $message = 'Sesi kamu kedaluwarsa karena halaman terlalu lama dibuka. Silakan coba lagi.';

            $redirect = $request->is('lacak')
                ? redirect()->route('tracking.search')
                : redirect()->back();

            return $redirect
                ->withInput($request->except('_token'))
                ->with('tracking_error', $message)
                ->with('error', $message);
        });
    })->create();
