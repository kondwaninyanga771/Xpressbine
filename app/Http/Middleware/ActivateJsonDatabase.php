<?php

namespace App\Http\Middleware;

use App\Data\JsonFileDriver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ActivateJsonDatabase
{
    public function __construct(private JsonFileDriver $driver) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('xpressbine.data_driver') !== 'json') {
            return $next($request);
        }

        $this->driver->begin();

        try {
            $response = $next($request);
            $this->driver->commit();

            return $response;
        } catch (Throwable $exception) {
            $this->driver->abort();

            throw $exception;
        }
    }
}
