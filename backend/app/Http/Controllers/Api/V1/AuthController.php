<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\Auth\LoginUserService;
use App\Services\Auth\LogoutUserService;
use App\Services\Auth\RegisterUserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly LoginUserService $loginUserService,
        private readonly LogoutUserService $logoutUserService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->registerUserService->execute($request->validated());

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->loginUserService->execute(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        } catch (ValidationException) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutUserService->execute($request->user());

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $validated = $request->safe()->only(['name', 'email']);

        if (array_key_exists('email', $validated) && $validated['email'] !== $user->email) {
            $validated['email_verified_at'] = null;
        }

        $user->forceFill($validated)->save();

        return new UserResource($user->refresh()->load('roles'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => $request->string('password')->toString(),
        ])->save();

        return response()->json(null, 204);
    }


    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return response()->json(['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Password reset successful.']);
    }
}
