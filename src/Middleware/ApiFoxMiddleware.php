<?php

namespace Tiacx\ApiFox\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Tiacx\ApiFox\Utilities\ApiFoxPusher;
use Symfony\Component\HttpFoundation\Response;

class ApiFoxMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response) $next
     * @return Response
     * @throws Exception
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
