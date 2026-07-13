<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * دریافت اطلاعات کاربر لاگین شده
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات کاربر دریافت شد.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => method_exists($user, 'getFilamentAvatarUrl')
    ? $user->getFilamentAvatarUrl()
    : null,
            ],
        ]);
    }
}