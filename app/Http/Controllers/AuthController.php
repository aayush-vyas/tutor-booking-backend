<?php

namespace App\Http\Controllers;

use App\Dto\Auth\RegisterDto;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        $dto = new RegisterDto(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            role: $request->input('role')
        );

        $user = User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'role' => $dto->role,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
            'User registered successfully',
            201
        );
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse(
                'Invalid credentials',
                401
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            data: [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Login successful'
        );
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return $this->successResponse(
            $request->user(),
            'User retrieved successfully'
        );
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            null,
            'Logged out successfully'
        );
    }
}
