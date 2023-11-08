<?php

namespace Tiacx\ApiFox\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tiacx\ApiFox\Utilities\ApiFoxPusher;
use Symfony\Component\HttpFoundation\Response;

class ApiFoxMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (getenv('APP_ENV') == 'testing') {
            (new ApiFoxPusher($request, $response))->handle();
        }
        return $response;
    }
}
