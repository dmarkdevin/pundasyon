<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\IssueApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\V1\TokenResource;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @tags Auth
 */
final class AuthController extends Controller
{
    /**
     * Sign in and get a token.
     *
     * Uses the same rules as the web sign-in: account lockout, sign-in history and
     * two-factor codes. The token is shown once; store it safely.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request, IssueApiToken $issue): JsonResponse
    {
        /** @var list<string>|null $abilities */
        $abilities = $request->validated('abilities');
        $days = $request->validated('expires_in_days');

        $token = $issue(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            (string) $request->validated('device_name'),
            $request->validated('code'),
            $abilities,
            $days === null ? null : (int) $days,
        );

        return (new TokenResource($token))->response()->setStatusCode(201);
    }

    /**
     * The signed-in user.
     */
    public function me(Request $request): UserResource
    {
        $user = $request->user();

        return new UserResource($user instanceof User ? $user->load('roles') : abort(401));
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            activity('auth')->event('token-revoked')->withProperties(['token' => $token->name])->log('Revoked an API token');
            $token->delete();
        }

        return response()->noContent();
    }
}
