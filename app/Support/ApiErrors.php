<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Every API error has the same shape: {"message": "...", "code": "...", "errors": {...}}.
 * "errors" only appears for validation problems. Details of unexpected errors stay
 * in the log in production.
 */
final class ApiErrors
{
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message] = match (true) {
            $exception instanceof ValidationException => [422, 'validation_failed', 'The given data was invalid.'],
            $exception instanceof AuthenticationException => [401, 'unauthenticated', $exception->getMessage() === 'Unauthenticated.' ? 'Send a valid API token.' : $exception->getMessage()],
            $exception instanceof AuthorizationException, $exception instanceof AccessDeniedHttpException => [403, 'forbidden', 'This token or account isn\'t allowed to do that.'],
            $exception instanceof ModelNotFoundException, $exception instanceof NotFoundHttpException => [404, 'not_found', 'Not found.'],
            $exception instanceof MethodNotAllowedHttpException => [405, 'method_not_allowed', 'That method isn\'t allowed here.'],
            $exception instanceof TooManyRequestsHttpException => [429, 'too_many_requests', 'Too many requests. Slow down and try again shortly.'],
            $exception instanceof InvalidQuery => [400, 'invalid_query', $exception->getMessage()],
            $exception instanceof HttpExceptionInterface => [$exception->getStatusCode(), 'http_error', $exception->getMessage() !== '' ? $exception->getMessage() : 'Request failed.'],
            default => [500, 'server_error', config('app.debug') ? $exception->getMessage() : 'Something went wrong on our side.'],
        };

        $body = ['message' => $message, 'code' => $code];

        if ($exception instanceof ValidationException) {
            $body['errors'] = $exception->errors();
        }

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        return new JsonResponse($body, $status, $headers);
    }
}
