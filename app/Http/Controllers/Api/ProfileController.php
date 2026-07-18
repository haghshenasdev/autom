<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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
                'avatar' => $user->avatar_url,
            ],
        ]);
    }

    /**
     * دریافت تصویر پروفایل کاربر لاگین شده
     */

    public function avatar(Request $request)
    {
        $user = auth()->user();

        if (!$user || empty($user->avatar_url)) {
            abort(404);
        }

        $disk = Storage::disk('profile-photos');

        if (!$disk->exists($user->avatar_url)) {
            abort(404);
        }

        return response(
            $disk->get($user->avatar_url),
            200,
            [
                'Content-Type' => $disk->mimeType($user->avatar_url),
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    public function get_avatar(string $filename)
    {
        $disk = Storage::disk('profile-photos');

        if (! $disk->exists($filename)) {
            abort(404);
        }

        return response(
            $disk->get($filename),
            200,
            [
                'Content-Type' => $disk->mimeType($filename),
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}
