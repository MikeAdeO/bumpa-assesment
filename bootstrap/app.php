<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The purchases endpoint is a JSON API consumed by curl/HTTP
        // clients, not an HTML form with a session-issued CSRF token to
        // send back — the assessment's own example (curl -X POST ...) has
        // no way to obtain one. It stays in routes/web.php (only the
        // achievements GET is required there), so it's excluded from CSRF
        // verification individually rather than moved to a whole separate
        // stateless route file. GET requests (the achievements endpoint)
        // are never CSRF-checked in the first place, so nothing else here
        // needs an exception.
        $middleware->validateCsrfTokens(except: [
            'users/*/purchases',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
