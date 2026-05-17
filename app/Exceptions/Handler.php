<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof TooManyRequestsHttpException && ($request->expectsJson() || $request->is('api/*'))) {
            return response()->json([
                'message' => 'Túl sok kérés érkezett rövid időn belül. Várj egy kicsit, majd próbáld újra.'
            ], 429);
        }

        if ($request->is('api/*')) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json([
                'message' => $status >= 500 ? 'Backend hiba történt.' : $e->getMessage(),
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ], $status);
        }

        return parent::render($request, $e);
    }
}
