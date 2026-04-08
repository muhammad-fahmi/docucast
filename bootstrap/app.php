<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $redirectForbidden = function (Request $request) {
            if (!$request->headers->has('referer')) {
                return redirect('/');
            }

            if (url()->previous() === $request->fullUrl()) {
                return redirect('/');
            }

            return back()->with('error', 'You are not authorized to access that page.');
        };

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($redirectForbidden) {
            if ($request->expectsJson()) {
                return null;
            }

            return $redirectForbidden($request);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($redirectForbidden) {
            if ($request->expectsJson() || $exception->getStatusCode() !== 403) {
                return null;
            }

            return $redirectForbidden($request);
        });
    })->create();
