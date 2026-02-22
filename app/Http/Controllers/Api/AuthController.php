<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
   
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

    
        $deviceName = $request->device_name ?? $request->userAgent() ?? 'unknown_device';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'verified_at' => $user->verified_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('registration_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }


    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Удаляем текущий токен
        $request->user()->currentAccessToken()->delete();
        
        // Создаем новый токен
        $newToken = $user->createToken('refresh_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $newToken,
                'token_type' => 'Bearer',
            ]
        ]);
    }


    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['cryptoAccounts' => function ($query) {
            $query->select('id', 'user_id', 'currency', 'network', 'balance', 'locked_balance');
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ]
        ]);
    }
}