<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorController extends Controller
{
    public function setup(Request $request)
    {
        try {
            $user = auth()->user();
            $google2fa = new Google2FA();

            $secret = $google2fa->generateSecretKey();

            $user->two_factor_secret = Crypt::encrypt($secret);
            $user->save();

            return response()->json([
                'secret' => $secret
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function enable(Request $request)
    {
        $request->validate(['code' => 'required']);
        $user = auth('api')->user();

        $google2fa = new Google2FA();
        $secret = Crypt::decrypt($user->two_factor_secret);

        if ($google2fa->verifyKey($secret, $request->code)) {
            $user->two_factor_confirmed_at = now(); 
            $user->save();

            return response()->json(['message' => '2FA enabled successfully']);
        }

        return response()->json(['message' => 'Invalid code'], 422);
    }
}