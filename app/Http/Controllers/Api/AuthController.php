<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
            'device_name' => ['nullable','string','max:100'],
        ]);

        $user = User::where('email',$data['email'])->first();

        if (!$user || !Hash::check($data['password'],$user->password)) {
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور صحیح نیست.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'karnama-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'data' => [
                'id'=>$user->id,
                'name'=>$user->name,
                'email'=>$user->email,
                'avatar'=>$user->getFilamentAvatarUrl(),
                'roles'=>$user->getRoleNames()->values(),
                'permissions'=>$user->getAllPermissions()->pluck('name')->values(),
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('roles');
        return response()->json([
            'data'=>[
                'id'=>$user->id,'name'=>$user->name,'email'=>$user->email,
                'avatar'=>$user->getFilamentAvatarUrl(),
                'roles'=>$user->getRoleNames()->values(),
                'permissions'=>$user->getAllPermissions()->pluck('name')->values(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message'=>'با موفقیت خارج شدید.']);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password'=>'required|string',
            'password'=>'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (!Hash::check($data['current_password'],$user->password)) {
            return response()->json(['message'=>'رمز عبور فعلی صحیح نیست.'],422);
        }

        $user->update(['password'=>$data['password']]);
        return response()->json(['message'=>'رمز عبور تغییر کرد.']);
    }
}
