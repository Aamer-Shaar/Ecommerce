<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\JWT;

class AuthController extends Controller
{
    use ApiResponseTrait;
    
    // تسجيل مستخدم جديد
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = auth('api')->login($user);

        $data = [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            // 'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
        return $this->successResponse($data, 'User registered successfully', 201);
    }

    // تسجيل الدخول
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
           return $this->errorResponse('Invalid credentials', null, 401);
        }

        return $this->respondWithToken($token);
    }

    // بيانات المستخدم الحالي
    public function me()
    {
        return $this->successResponse(auth()->user(), 'User profile retrieved');
    }

    // تسجيل الخروج
    public function logout()
    {
        auth()->logout();
        return $this->successResponse(null, 'Logged out successfully');
    }

    // تحديث التوكن (Refresh)
    // public function refresh()
    // {
    //    return $this->respondWithToken(auth('api')->refresh());
    // }

    // تنسيق الرد مع التوكن
    protected function respondWithToken($token)
    {
        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            // 'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
        return $this->successResponse($data, 'Token refreshed successfully');
    }
}