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
                'avatar' => $user->avatar_url ? url('/api/profile/avatar') : null,
            ],
        ]);
    }

    /**
     * دریافت تصویر پروفایل کاربر لاگین شده
     */

    public function avatar(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->avatar_url) {
            abort(404);
        }

        $disk = Storage::disk('profile-photos');
        $path = ltrim(str_replace('\\', '/', (string) $user->avatar_url), '/');

        // Some Filament versions store the disk-relative path while older
        // installations may have persisted "profile-photos/..." in the DB.
        $candidates = array_values(array_unique([
            $path,
            preg_replace('#^profile-photos/#', '', $path),
            basename($path),
        ]));

        foreach ($candidates as $candidate) {
            if (!$candidate || !$disk->exists($candidate)) {
                continue;
            }

            $mime = $disk->mimeType($candidate) ?: 'application/octet-stream';

            return response($disk->get($candidate), 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . basename($candidate) . '"',
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404);
    }

    public function get_avatar(string $filename)
    {
        $disk = Storage::disk('profile-photos');
        $path = ltrim(str_replace('\\', '/', $filename), '/');

        foreach (array_values(array_unique([
            $path,
            preg_replace('#^profile-photos/#', '', $path),
            basename($path),
        ])) as $candidate) {
            if (!$candidate || !$disk->exists($candidate)) {
                continue;
            }

            return response($disk->get($candidate), 200, [
                'Content-Type' => $disk->mimeType($candidate) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . basename($candidate) . '"',
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404);
    }
}
