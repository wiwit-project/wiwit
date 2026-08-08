<?php

use App\Http\Middleware\RequireJsonBody;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'json.body' => RequireJsonBody::class,
        ]);

        // Enable trusted proxy config is app running behing a reverse proxy so that the assets
        // URLs are generated correctly
        // NOTE: Can't use config() helpher here because the config is not loaded yet. Use environment instead.
        if (env('ENABLE_TRUSTED_PROXY_CONFIG', false)) {
            $middleware->trustProxies(env('TRUSTED_PROXIES', '*'));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof ValidationException => 422,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            [$type, $title, $detail] = match ($status) {
                400 => ['/problems/malformed-json', 'Malformed JSON', 'The request body contains invalid JSON.'],
                401 => ['/problems/unauthenticated', 'Unauthenticated', 'Authentication credentials are missing or invalid.'],
                403 => ['/problems/insufficient-ability', 'Insufficient ability', 'The token lacks the ability required for this operation.'],
                404 => ['/problems/not-found', 'Resource not found', match (true) {
                    $request->is('api/v1/transactions/*') => 'The requested transaction was not found.',
                    $request->is('api/v1/categories/*') => 'The requested category was not found.',
                    default => 'The requested resource was not found.',
                }],
                405 => ['/problems/method-not-allowed', 'Method not allowed', 'The HTTP method is not supported for this endpoint.'],
                409 => ['/problems/conflict', 'Conflict', 'The category name conflicts with an existing category.'],
                415 => ['/problems/unsupported-media-type', 'Unsupported media type', 'Request bodies must use application/json.'],
                422 => ['/problems/validation-failed', 'Validation failed', 'One or more fields are invalid.'],
                429 => ['/problems/too-many-requests', 'Too many requests', 'Too many requests were made. Try again later.'],
                default => ['/problems/internal-server-error', 'Internal server error', 'An unexpected error occurred.'],
            };

            $problem = compact('type', 'title', 'status', 'detail') + ['instance' => $request->getRequestUri()];

            if ($exception instanceof ValidationException) {
                $problem['errors'] = $exception->errors();
            }

            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

            return response()->json($problem, $status, $headers)
                ->header('Content-Type', 'application/problem+json');
        });
    })->create();
