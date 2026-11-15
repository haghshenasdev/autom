<?php

namespace App\Http\Controllers;

use App\Services\NewUiAssistantService;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NewUiAssistantController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAssistant($request);

        return view('newui.assistant');
    }

    public function message(Request $request, NewUiAssistantService $assistant): JsonResponse
    {
        $user = $this->authorizeAssistant($request);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1200'],
        ]);

        try {
            return response()->json($assistant->answer($user, trim($data['message'])));
        } catch (\Throwable $exception) {
            Log::warning('New UI assistant request failed', ['exception' => get_class($exception)]);

            return response()->json([
                'message' => trim((string) config('services.gapgpt.api_key')) === ''
                    ? 'کلید GapGPT برای دستیار تنظیم نشده است.'
                    : 'در ارتباط با دستیار خطایی رخ داد. کمی بعد دوباره تلاش کنید.',
            ], 503);
        }
    }

    private function authorizeAssistant(Request $request)
    {
        $user = $request->user();
        $panel = Filament::getPanel('admin');
        abort_unless($panel && $user && $user->canAccessPanel($panel), 403);
        abort_unless(
            $user->can('view_any_letter') || $user->can('view_any_task') || $user->can('view_any_minutes'),
            403
        );

        return $user;
    }
}
