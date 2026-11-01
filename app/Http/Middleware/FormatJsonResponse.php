<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormatJsonResponse
{
    /**
     * Handle an incoming request and format JSON responses with pretty print and unescaped slashes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->header('X-Format-Middleware', 'ran');
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if ($response->getStatusCode() >= 400) {
                $data = $response->getData(true);
                if (is_array($data) && isset($data['type'], $data['title'])) {
                    $response->headers->set('Content-Type', 'application/problem+json');
                }
            }
        }

        return $response;
    }
}
