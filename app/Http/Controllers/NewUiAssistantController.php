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
        $resultsPage = $request->boolean('results_page');
        $shareContent = $request->boolean('share_content');
        $cancelConsent = $request->boolean('cancel_content');
        $data = $request->validate($resultsPage
            ? ['results_page' => ['required', 'accepted'], 'search_token' => ['required', 'string', 'size:64'], 'page' => ['required', 'integer', 'min:1', 'max:100000']]
            : ($cancelConsent
                ? ['cancel_content' => ['required', 'accepted'], 'consent_token' => ['required', 'string', 'size:64']]
                : ($shareContent
                    ? ['share_content' => ['required', 'accepted'], 'consent_token' => ['required', 'string', 'size:64']]
                    : ['message' => ['required', 'string', 'max:1200']])));

        try {
            if ($resultsPage) {
                return response()->json($assistant->resultsPage($user, $request->session(), $data['search_token'], (int) $data['page']));
            }
            if ($cancelConsent) {
                $key = 'newui_assistant_consent.' . $data['consent_token'];
                $pending = $request->session()->get($key);
                if (is_array($pending) && ($pending['user_id'] ?? null) === $user->id) $request->session()->forget($key);
                return response()->json(['cancelled' => true]);
            }
            if ($shareContent) return response()->json($assistant->confirmConsent($user, $request->session(), $data['consent_token']));
            return response()->json($assistant->answer($user, trim($data['message']), $request->session()));
        } catch (\Throwable $exception) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) throw $exception;
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
