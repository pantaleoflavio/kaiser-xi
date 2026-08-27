<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivacyAcknowledged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->privacy_acknowledged_at === null) {
            return new JsonResponse([
                'message' => 'Privacy Policy acknowledgement is required.',
                'code' => 'privacy_acknowledgement_required',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
