<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null): Response
    {
        if (!$request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
                !$request->user()->hasVerifiedEmail())) {

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your email address is not verified.'], 403);
            }

            // return redirect()->route($redirectToRoute ?: 'verification.notice');
            return response()->json(['message' => 'Your email address is not verified.'], 403);
        }

        return $next($request);
    }
}
