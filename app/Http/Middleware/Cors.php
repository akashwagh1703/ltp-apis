<?php

namespace App\Http\Middleware;

use App\Support\CorsOrigins;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowOrigin = CorsOrigins::headerFor($request->headers->get('Origin'));

        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
            $this->apply($response, $allowOrigin);

            return $response;
        }

        $response = $next($request);
        $this->apply($response, $allowOrigin);

        return $response;
    }

    protected function apply(Response $response, ?string $allowOrigin): void
    {
        if ($allowOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Max-Age', '86400');
    }
}
