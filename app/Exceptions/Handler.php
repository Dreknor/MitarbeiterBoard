<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($request->is('api/v1/*')) {
            $apiResponse = $this->renderApiV1Exception($exception);
            if ($apiResponse) {
                return $apiResponse;
            }
        }

        return parent::render($request, $exception);
    }

    /**
     * API v1: Einheitliche, deutschsprachige Fehlermeldungen ohne interne Klassennamen.
     */
    protected function renderApiV1Exception(Throwable $exception): ?\Illuminate\Http\JsonResponse
    {
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Die angeforderte Ressource wurde nicht gefunden.'], 404);
        }

        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException
            || $exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
            return response()->json(['message' => 'Keine Berechtigung für diese Aktion.'], 403);
        }

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
            return response()->json(['message' => $exception->getMessage() ?: 'Keine Berechtigung für diese Aktion.'], 403);
        }

        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json(['message' => 'Nicht authentifiziert.'], 401);
        }

        return null;
    }
}
