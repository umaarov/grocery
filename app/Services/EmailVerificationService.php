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
        if ($user->hasVerifiedEmail()) {
            Log::info('Verification email skipped for verified user.', ['user_id' => $user->id]);
            return;
        }

        if (!$user->email_verification_token) {
            $user->email_verification_token = $this->generateToken();
            $user->save();
            Log::info('Generated new verification token for user', ['user_id' => $user->id]);
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

            Log::info('Sending verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'url' => $verificationUrl
            ]);

            Mail::to($user->email)->send(new EmailVerification($user, $verificationUrl));

            Log::info('Verification email sent successfully', ['user_id' => $user->id]);

        } catch (Exception $e) {
            Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function verify(User $user, string $token): bool
    {
        if ($user->hasVerifiedEmail()) {
            Log::warning('Email already verified.', ['user_id' => $user->id]);
            return false;
        }

        if (!$user->email_verification_token) {
            Log::warning('User has no verification token.', ['user_id' => $user->id]);
            return false;
        }

        if (hash_equals((string)$user->email_verification_token, (string)$token)) {
            $user->forceFill([
                'email_verified_at' => $user->freshTimestamp(),
                'email_verification_token' => null,
            ])->save();

            Log::info('Email verification successful.', ['user_id' => $user->id]);
            return true;
        }

        Log::warning('Email verification failed - token mismatch.', [
            'user_id' => $user->id,
            'token_provided' => $token,
            'token_expected' => $user->email_verification_token,
        ]);
        return false;
    }
}
