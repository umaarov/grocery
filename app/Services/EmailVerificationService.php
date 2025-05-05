<?php

namespace App\Services;

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Exception;

class EmailVerificationService
{
    public function generateToken(): string
    {
        return Str::random(60);
    }

    public function sendVerificationEmail(User $user, bool $useApiRoute = false): void
    {
        if ($user->hasVerifiedEmail() || !$user->email_verification_token) {
            Log::info('Verification email skipped.', ['user_id' => $user->id, 'verified' => $user->hasVerifiedEmail(), 'has_token' => !!$user->email_verification_token]);
            return;
        }

        $routeName = $useApiRoute ? 'api.verification.verify' : 'verification.verify';

        try {
            $verificationUrl = URL::temporarySignedRoute(
                $routeName,
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'token' => $user->email_verification_token,
                ]
            );

            Log::info('Sending verification email', ['user_id' => $user->id, 'url' => $verificationUrl]);

            Mail::to($user->email)->send(new EmailVerification($user, $verificationUrl));

        } catch (Exception $e) {
            Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // throw $e;
        }
    }

    public function verify(User $user, string $token): bool
    {
        if (!$user->hasVerifiedEmail() && hash_equals((string)$user->email_verification_token, $token)) {
            Log::info('Email verification successful.', ['user_id' => $user->id]);
            $user->forceFill([
                'email_verified_at' => $user->freshTimestamp(),
                'email_verification_token' => null,
            ])->save();
            return true;
        }

        Log::warning('Email verification failed or already verified.', [
            'user_id' => $user->id,
            'token_provided' => $token,
            'token_expected' => $user->email_verification_token,
            'already_verified' => $user->hasVerifiedEmail(),
        ]);
        return false;
    }
}
