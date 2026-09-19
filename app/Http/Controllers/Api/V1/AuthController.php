<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * @group Authentication
 *
 * Manage authentication for the API.
 *
 * @unauthenticated
 */
class AuthController extends Controller
{
    private const ABILITIES = ['view', 'create', 'update', 'delete'];

    /**
     * Get Token
     *
     * Obtain a bearer token using email and password credentials.
     *
     * @bodyParam email string required The user's email address. Example: demo-user@example.com
     * @bodyParam password string required The user's password. Example: 12345678
     * @bodyParam device_name string required A name for the device requesting the token. Example: Iqmal's Iphone 16
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);
        $email = Str::lower($validated['email']);

        if (! Auth::validate(['email' => $email, 'password' => $validated['password']])) {
            abort(401);
        }

        RateLimiter::clear(Str::transliterate($email.'|'.$request->ip()));
        $user = User::where('email', $email)->firstOrFail();

        return $this->tokenResponse($user, $validated['device_name']);
    }

    /**
     * Revoke Current Access Token
     *
     * Revoke the bearer token used by the current device.
     *
     * @authenticated
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, string $deviceName)
    {
        $token = $user->createToken($deviceName, self::ABILITIES);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => self::ABILITIES,
            'expires_at' => null,
        ]);
    }
}
