<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc,dns', 'unique:users'], 'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()]]);
        $user = User::create($data);

        return $this->authenticated($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 422);
        }

        return $this->authenticated($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    private function authenticated(User $user, int $status = 200): JsonResponse
    {
        $user->tokens()->where('name', 'currency-converter')->delete();

        return response()->json(['user' => $user, 'token' => $user->createToken('currency-converter')->plainTextToken], $status)->header('Cache-Control', 'no-store');
    }
}
