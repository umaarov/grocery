<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\EmailVerificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private AvatarService $avatarService;
    private EmailVerificationService $emailVerificationService;

    public function __construct(AvatarService $avatarService, EmailVerificationService $emailVerificationService)
    {
        $this->avatarService = $avatarService;
        $this->emailVerificationService = $emailVerificationService;
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), $this->getRegistrationRules());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = $this->createUser($request);

            $this->setProfilePicture($user, $request);

            $this->emailVerificationService->sendVerificationEmail($user, true);

            $user->makeHidden(['email_verification_token', 'password']);

            return response()->json([
                'message' => 'Registration successful! Please check your email to verify your account.',
                'user' => $user
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('API Registration failed: ' . $e->getMessage(), [
                'user_email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Registration failed.', 'error' => $e->getMessage()], 500);
        }
    }

    public function verifyEmail(Request $request, $id, $token): JsonResponse
    {
        if (!$request->hasValidSignature()) {
            Log::warning('Invalid signature for email verification', [
                'id' => $id,
                'token' => $token,
                'url' => $request->fullUrl()
            ]);
            return response()->json(['message' => 'Invalid or expired verification link.'], 401);
        }

        $user = User::find($id);

        if (!$user) {
            Log::warning('User not found during email verification', ['id' => $id]);
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        if ($this->emailVerificationService->verify($user, $token)) {
            return response()->json(['message' => 'Email verified successfully! You can now log in.'], 200);
        }

        Log::warning('Token mismatch during email verification', [
            'user_id' => $user->id,
            'provided_token' => $token
        ]);
        return response()->json(['message' => 'Invalid verification token.'], 400);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        try {
            $this->emailVerificationService->sendVerificationEmail($user, true); // Pass true for API URL
            return response()->json(['message' => 'Verification link sent! Please check your email.'], 200);
        } catch (Exception $e) {
            Log::error('API Resend Verification failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to send verification email. Please try again later.'], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string',
                'device_name' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $credentials = $request->only('email', 'password');

            if (Auth::attempt($credentials)) {
                $user = Auth::user();

                if (!$user->hasVerifiedEmail()) {
                    // Auth::logout();
                    return response()->json(['message' => 'Email not verified. Please check your email.'], 403);
                }

                $user->tokens()->where('name', $request->device_name)->delete();

                try {
                    $token = $user->createToken($request->device_name)->plainTextToken;

                    Log::debug('Generated Token: ', ['token' => $token]);

                    $user->makeHidden(['email_verification_token']);

                    return response()->json([
                        'message' => 'Logged in successfully!',
                        'user' => $user,
                        'token_type' => 'Bearer',
                        'access_token' => $token,
                    ], 200);
                } catch (Exception $tokenEx) {
                    Log::error('Failed to create token: ' . $tokenEx->getMessage(), [
                        'user_id' => $user->id,
                        'trace' => $tokenEx->getTraceAsString()
                    ]);
                    return response()->json(['message' => 'Authentication error. Please try again.'], 500);
                }
            } else {
                Log::warning('API Failed login attempt', ['email' => $request->email, 'ip' => $request->ip()]);
                return response()->json(['message' => 'Invalid login credentials.'], 401);
            }
        } catch (Exception $e) {
            Log::error('API Login failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => 'Login failed: ', $e->getMessage()], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $user->currentAccessToken()->delete();
            return response()->json(['message' => 'Logged out successfully!'], 200);
            // return response('', 204);
        } catch (Exception $e) {
            Log::error('API Logout failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'token_id' => optional($user->currentAccessToken())->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Logout failed. Please try again.'], 500);
        }
    }

    public function googleRedirect(): JsonResponse
    {
        try {
            $redirectUrl = Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl();
            return response()->json(['redirect_url' => $redirectUrl]);

        } catch (Exception $e) {
            Log::error('API Google redirect failed: ' . $e->getMessage());
            return response()->json(['message' => 'Google authentication failed. Please try again.'], 500);
        }
    }

    public function googleCallback(Request $request): RedirectResponse // Redirects back to FRONTEND
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                $existingUser = User::where('email', $googleUser->getEmail())->first();
                if ($existingUser) {
                    $user = $this->updateExistingUserWithGoogle($existingUser, $googleUser);
                } else {
                    $user = $this->createUserFromGoogle($googleUser);
                }
            }

            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $token = $user->createToken('google-login')->plainTextToken;

            $redirectUrl = $frontendUrl . '/auth/callback#token=' . urlencode($token) . '&user=' . urlencode(json_encode($user->only(['id', 'first_name', 'email']))); // Pass minimal user info if needed

            return redirect()->away($redirectUrl);


        } catch (Exception $e) {
            Log::error('API Google Auth Callback Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $errorRedirectUrl = $frontendUrl . '/auth/error?message=' . urlencode('Unable to login using Google. Please try again.');
            return redirect()->away($errorRedirectUrl);
        }
    }

    private function getRegistrationRules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];
    }

    private function createUser(Request $request): User
    {
        return User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_picture' => null,
            'email_verification_token' => $this->emailVerificationService->generateToken(),
            'email_verified_at' => null,
        ]);
    }

    private function setProfilePicture(User $user, Request $request): void
    {
        $profilePicturePath = null;
        if ($request->hasFile('profile_picture')) {
            $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');
        } else {
            $profilePicturePath = $this->avatarService->generateInitialsAvatar(
                $user->first_name,
                $user->last_name ?? '',
                $user->id
            );
        }

        if ($profilePicturePath) {
            $user->profile_picture = $profilePicturePath;
            $user->save();
        }
    }

    private function updateExistingUserWithGoogle(User $user, $googleUser): User
    {
        $updateData = ['google_id' => $googleUser->getId()];

        if (!$user->profile_picture && $googleUser->getAvatar()) {
            $updateData['profile_picture'] = $googleUser->getAvatar();
        }
        if (!$user->email_verified_at) {
            $updateData['email_verified_at'] = now();
        }

        $user->update($updateData);
        return $user;
    }

    private function createUserFromGoogle($googleUser): User
    {

        $firstName = $googleUser->user['given_name'] ?? explode(' ', $googleUser->getName() ?? '')[0] ?? '';
        $lastName = $googleUser->user['family_name'] ?? (count(explode(' ', $googleUser->getName() ?? '')) > 1 ? implode(' ', array_slice(explode(' ', $googleUser->getName() ?? ''), 1)) : null);

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(24)),
            'email_verification_token' => null,
        ]);

        $profilePicturePath = $googleUser->getAvatar();
        if (!$profilePicturePath) {
            $profilePicturePath = $this->avatarService->generateInitialsAvatar(
                $user->first_name,
                $user->last_name ?? '',
                $user->id
            );
        }
        $user->profile_picture = $profilePicturePath;
        $user->save();

        return $user;
    }
}
