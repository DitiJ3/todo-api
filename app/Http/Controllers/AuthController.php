<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use PragmaRX\Google2FAQRCode\Google2FA;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        return response()->json(['message' => 'User created successfully'], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth('api')->user();

        if ($user->two_factor_secret) {
            return response()->json([
                'requires_2fa' => true,
                'temp_token' => $token,
            ]);
        }

        return $this->respondWithToken($token);
    }

    
    public function me()
    {
        return response()->json(auth()->user());
    }

    public function verify2fa(Request $request)
    {
        $request->validate(['code' => 'required']);
        $user = $user = auth('api')->user();        ;

        $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
        $secret = Crypt::decrypt($user->two_factor_secret);

        if ($google2fa->verifyKey($secret, $request->code)) {
            $token = auth('api')->login($user);
            return $this->respondWithToken($token);
        }

        return response()->json(['message' => 'Invalid 2FA code'], 403);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

}
