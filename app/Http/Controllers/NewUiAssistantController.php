<?php

namespace App\Http\Controllers;

use App\Services\NewUiAssistantService;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewUiAssistantController extends Controller
{
    public function analyzeFile(Request $request, NewUiAssistantService $assistant): JsonResponse
    {
        $user = $this->authorizeAssistant($request);
        abort_unless($user->can('create_letter') || $user->can('create_minutes'), 403);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,tif,tiff', 'max:20480']]);

        try {
            $file = $data['file'];
            $ocrText = app(\App\Services\BaleLetterOcrService::class)->extract(
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            );
            try {
                $suggestion = $assistant->suggestRegistrationType($ocrText);
            } catch (\Throwable $aiException) {
                Log::warning('New UI assistant file AI analysis unavailable; local draft fallback used', [
                    'user_id' => $user->id,
                    'exception' => get_class($aiException),
                    'message' => $aiException->getMessage(),
                ]);
                $suggestion = [
                    'type' => $user->can('create_letter') ? 'letter' : 'minute',
                    'title' => mb_substr(trim((preg_split('/\\R/u', $ocrText)[0] ?? '')), 0, 180),
                    'reason' => 'دستیار هوشمند در دسترس نیست؛ عنوان و نوع را بازبینی کنید.',
                    'summary' => '',
                ];
            }
            if (!in_array($suggestion['type'], ['letter', 'minute'], true)
                || !$user->can($suggestion['type'] === 'letter' ? 'create_letter' : 'create_minutes')) {
                $suggestion['type'] = $user->can('create_letter') ? 'letter' : 'minute';
            }

            $extension = strtolower((string) $file->extension()) ?: 'pdf';
            if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'], true)) $extension = 'pdf';
            $temporaryName = app(\App\Services\TempFileService::class)->save(
                file_get_contents($file->getRealPath()),
                $extension
            );
            $token = Str::random(48);
            Cache::put('newui_assistant_file.' . $token, [
                'user_id' => (int) $user->id,
                'text' => mb_substr(trim($ocrText), 0, 24000),
                'temporary_name' => $temporaryName,
                'extension' => $extension,
                'suggestion' => $suggestion,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ], now()->addMinutes(5));

            return response()->json([
                'token' => $token,
                'type' => $suggestion['type'],
                'title' => $suggestion['title'],
                'reason' => $suggestion['reason'] ?? '',
                'summary' => $suggestion['summary'] ?? '',
                'preview' => mb_substr($ocrText, 0, 1200),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('New UI assistant file OCR/analysis failed', [
                'user_id' => $user->id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => $this->assistantErrorMessage($exception)], 503);
        }
    }

    public function registerFile(Request $request): JsonResponse
    {
        $user = $this->authorizeAssistant($request);
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'type' => ['required', 'in:letter,minute'],
            'title' => ['required', 'string', 'max:255'],
        ]);
        $key = 'newui_assistant_file.' . $data['token'];
        $state = Cache::get($key);
        abort_unless(is_array($state)
            && (int) ($state['user_id'] ?? 0) === (int) $user->id
            && (int) ($state['expires_at'] ?? 0) >= now()->timestamp, 410);
        abort_unless($user->can($data['type'] === 'letter' ? 'create_letter' : 'create_minutes'), 403);
        $temporaryPath = 'temp_files/' . basename((string) $state['temporary_name']);
        abort_unless(Storage::disk('local')->exists($temporaryPath), 410);
        $contents = Storage::disk('local')->get($temporaryPath);
        $title = trim($data['title']);
        $body = trim((string) $state['text']);
        $aiSummary = trim((string) ($state['suggestion']['summary'] ?? ''));
        $description = ($aiSummary !== '' ? "خلاصه:\n{$aiSummary}\n\n" : '')
            . ($body !== '' ? "متن استخراج‌شده:\n{$body}" : '');
        $extension = preg_replace('/[^a-z0-9]/i', '', (string) $state['extension']) ?: 'pdf';

        if ($data['type'] === 'letter') {
            try {
                $parsed = (new \App\Http\Controllers\ai\LetterParser())->parse("#نامه {$title}\n{$body}");
            } catch (\Throwable $exception) {
                Log::warning('New UI assistant letter parser failed; registering OCR text as description.', [
                    'user_id' => $user->id, 'exception' => get_class($exception), 'message' => $exception->getMessage(),
                ]);
                $parsed = [];
            }
            $record = \App\Models\Letter::create([
                'subject' => $title,
                'created_at' => $parsed['title_date'] ?? now(),
                'description' => $description,
                'summary' => trim((string) ($parsed['summary'] ?? '')) ?: $aiSummary,
                'mokatebe' => $parsed['mokatebe'] ?? null,
                'daftar_id' => $parsed['daftar'] ?? 461,
                'kind' => $parsed['kind'] ?? 1,
                'user_id' => $user->id,
                'peiroow_letter_id' => $parsed['pirow'] ?? null,
                'status' => $parsed['status'] ?? 2,
                'organ_id' => ($parsed['kind'] ?? 1) == 1 ? ($parsed['organ_id'] ?? null) : null,
                'file' => $extension,
            ]);
            $record->users()->syncWithoutDetaching($parsed['user_id'] ?? []);
            $record->organs_owner()->syncWithoutDetaching(array_filter($parsed['organ_owners'] ?? []));
            $record->customers()->syncWithoutDetaching(array_filter($parsed['customer_owners'] ?? []));
            $record->projects()->syncWithoutDetaching(array_filter($parsed['projects'] ?? []));
            Storage::disk('private')->put($record->getFilePath(), $contents);
            $url = url('/newui/letter/' . $record->id);
        } else {
            try {
                $parsed = (new \App\Http\Controllers\ai\MinutesParser(false))->parse("{$title}\n{$body}", $user->id);
            } catch (\Throwable $exception) {
                Log::warning('New UI assistant minute parser failed; registering OCR text as description.', [
                    'user_id' => $user->id, 'exception' => get_class($exception), 'message' => $exception->getMessage(),
                ]);
                $parsed = [];
            }
            $record = \App\Models\Minutes::create([
                'title' => $title,
                'date' => $parsed['title_date'] ?? now(),
                'text' => $description,
                'typer_id' => $user->id,
                'task_id' => $parsed['task_id'] ?? null,
                'file' => $extension,
            ]);
            $record->organ()->sync($parsed['organs'] ?? []);
            if (\App\Models\MinutesGroup::query()->whereKey(1)->exists()) $record->group()->sync([1]);
            Storage::disk('private_appendix_other')->put($record->getFilePath(), $contents);
            $url = url('/newui/minute/' . $record->id);
        }

        Storage::disk('local')->delete($temporaryPath);
        Cache::forget($key);
        return response()->json(['message' => 'رکورد با موفقیت ثبت شد.', 'url' => $url, 'title' => $title]);
    }

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
            Log::warning('New UI assistant request failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json(['message' => $this->assistantErrorMessage($exception)], 503);
        }
    }

    private function assistantErrorMessage(\Throwable $exception): string
    {
        if (trim((string) config('services.gapgpt.api_key')) === '') {
            return 'کلید GapGPT برای دستیار تنظیم نشده است.';
        }
        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'سرور برنامه به api.gapgpt.ir وصل نمی‌شود. DNS و دسترسی خروجی سرور را بررسی کنید.';
        }
        return 'در ارتباط با دستیار خطایی رخ داد. کمی بعد دوباره تلاش کنید.';
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
