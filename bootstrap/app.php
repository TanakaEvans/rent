<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // In-app chat (Module 05). Self-contained route file so the feature
            // ships without editing the shared routes/web.php.
            require __DIR__.'/../routes/chat.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'access.pass' => \App\Http\Middleware\EnsureHasAccess::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => 'This action is unauthorized.'], 403)
                : back()->with('error', 'You do not have permission to perform this action.');
        });

        // A broken business rule on an in-app action (e.g. "this slot is already
        // taken", "listing no longer available") should read as a message on the
        // page the user was on, not a bare error page. Only browser (Inertia)
        // form submissions are converted; page loads and API calls keep their status.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($request->isMethod('GET') || ! $request->header('X-Inertia')) {
                return null;
            }

            if (! in_array($e->getStatusCode(), [403, 404, 409, 410, 422], true)) {
                return null;
            }

            $message = $e->getMessage();
            if ($message === '' || str_starts_with($message, 'No query results')) {
                $message = $e->getStatusCode() === 403
                    ? 'You do not have permission to perform this action.'
                    : 'That item is no longer available. Please refresh the page and try again.';
            }

            return back()->with('error', $message);
        });
    })->create();
