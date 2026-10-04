<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Support\Navigation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if ($request->expectsJson() || ! $request->isMethod('GET') || $request->user() === null) {
                return null;
            }

            return response()->view('access-restricted', [
                'module' => Navigation::moduleForRoute($request->route()?->getName()),
            ], 403);
        });
    })->create();
