<?php

namespace App\Http\Controllers;

use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\TaskResource;
use App\Models\Letter;
use App\Models\Cartable;
use App\Models\City;
use App\Models\Minutes;
use App\Models\MinutesGroup;
use App\Models\Organ;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\Type;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class NewUiController extends Controller
{
    public function updateRecord(Request $request, string $type, int $recordId)
    {
        $user = $request->user();
        abort_unless(in_array($type, ['task', 'letter', 'minute'], true), 404);
        abort_unless($user->can('update_' . $type), 403);

        $record = match ($type) {
            'task' => Task::query()->when(!$user->can('restore_any_task'), fn ($q) => $q->where('Responsible_id', $user->id))->findOrFail($recordId),
            'letter' => Letter::query()->when(!$user->can('restore_any_letter'), fn ($q) => $q->where(fn ($scope) => $scope->where('user_id', $user->id)->orWhereHas('referrals', fn ($referrals) => $referrals->where('to_user_id', $user->id))->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id))))->findOrFail($recordId),
            'minute' => Minutes::query()->when(!$user->can('restore_any_minutes'), fn ($q) => $q->where('typer_id', $user->id))->findOrFail($recordId),
        };

        $commonIds = ['nullable', 'array'];
        if ($type === 'task') {
            $this->normalizeRecordDates($request, ['started_at', 'ended_at', 'completed_at']);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
                'completed' => ['nullable', 'boolean'], 'repeat' => ['nullable', 'boolean'],
                'status' => ['nullable', 'integer'],
                'started_at' => ['nullable', 'date'], 'ended_at' => ['nullable', 'date'], 'completed_at' => ['nullable', 'date'],
                'progress' => ['nullable', 'integer', 'min:0', 'max:100'], 'amount' => ['nullable', 'numeric', 'min:0'],
                'city_id' => ['nullable', 'exists:cities,id'], 'organ_id' => ['nullable', 'exists:organs,id'],
                'Responsible_id' => [$user->can('restore_any_task') ? 'nullable' : 'prohibited', 'exists:users,id'],
                'task_group_ids' => $commonIds, 'task_group_ids.*' => ['integer', 'exists:task_groups,id'],
                'project_ids' => $commonIds, 'project_ids.*' => ['integer', 'exists:projects,id'],
                'minutes_id' => ['nullable', 'exists:minutes,id'], 'letter_id' => ['nullable', 'exists:letters,id'],
            ]);
            $record->fill(collect($data)->except(['task_group_ids', 'project_ids'])->all());
            $record->save();
            if (array_key_exists('task_group_ids', $data)) $record->task_group()->sync($data['task_group_ids'] ?? []);
            if (array_key_exists('project_ids', $data)) $record->project()->sync($data['project_ids'] ?? []);
        } elseif ($type === 'letter') {
            $this->normalizeRecordDates($request, ['created_at']);
            $data = $request->validate([
                'subject' => ['required', 'string', 'max:1000'], 'mokatebe' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'], 'summary' => ['nullable', 'string'],
                'created_at' => ['nullable', 'date'], 'status' => ['nullable', 'integer'],
                'type_id' => ['nullable', 'exists:types,id'], 'kind' => ['required', 'in:0,1'],
                'daftar_id' => ['nullable', 'exists:organs,id'], 'organ_id' => ['nullable', 'exists:organs,id'],
                'peiroow_letter_id' => ['nullable', 'exists:letters,id'],
                'project_ids' => $commonIds, 'project_ids.*' => ['integer', 'exists:projects,id'],
            ]);
            $record->fill(collect($data)->except('project_ids')->all())->save();
            if (array_key_exists('project_ids', $data)) $record->projects()->sync($data['project_ids'] ?? []);
        } else {
            $this->normalizeRecordDates($request, ['date']);
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'], 'text' => ['nullable', 'string'],
                'date' => ['required', 'date'], 'task_id' => ['required', 'exists:tasks,id'],
                'organ_ids' => $commonIds, 'organ_ids.*' => ['integer', 'exists:organs,id'],
                'project_ids' => $commonIds, 'project_ids.*' => ['integer', 'exists:projects,id'],
                'group_ids' => $commonIds, 'group_ids.*' => ['integer', 'exists:minutes_groups,id'],
            ]);
            $record->fill(collect($data)->only(['title', 'text', 'date', 'task_id'])->all())->save();
            if (array_key_exists('organ_ids', $data)) $record->organ()->sync($data['organ_ids'] ?? []);
            if (array_key_exists('project_ids', $data)) $record->projects()->sync($data['project_ids'] ?? []);
            if (array_key_exists('group_ids', $data)) $record->group()->sync($data['group_ids'] ?? []);
        }

        return redirect()->route('newui.record', ['type' => $type, 'recordId' => $recordId])->with('success', 'تغییرات ذخیره شد.');
    }

    private function normalizeRecordDates(Request $request, array $fields): void
    {
        $parser = app(\App\Services\PersianJalaliDateParser::class);
        $normalized = [];
        foreach ($fields as $field) {
            $value = trim((string) $request->input($field, ''));
            if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value)) continue;
            $date = $parser->parse($value);
            if (!$date) continue;
            $digits = strtr($value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
            if (preg_match('/(?:[ T])(\d{1,2}:\d{2}(?::\d{2})?)$/', $digits, $time)) {
                $parts = array_map('intval', explode(':', $time[1]));
                $date->setTime($parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0);
            }
            $normalized[$field] = $date->format('Y-m-d H:i:s');
        }
        if ($normalized) $request->merge($normalized);
    }

    public function updateCartableChecked(Request $request, Cartable $cartable)
    {
        abort_unless($request->user()->can('update_cartable'), 403);
        abort_unless((int) $cartable->user_id === (int) $request->user()->id, 403);
        $data = $request->validate(['checked' => ['required', 'boolean']]);
        $cartable->update(['checked' => $data['checked']]);

        return redirect()->route('newui', ['view' => 'cartable'])->with('success', 'وضعیت نامه به‌روزرسانی شد.');
    }

    public function updateCartableTask(Request $request, Task $task)
    {
        $user = $request->user();
        $this->authorize('update', $task);
        abort_unless((int) $task->Responsible_id === (int) $user->id || $user->can('restore_any_task'), 403);

        $data = $request->validate([
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'completed' => ['required', 'boolean'],
        ]);
        $task->update($data);

        return redirect()->route('newui', ['view' => 'cartable'])->with('success', 'فعالیت به‌روزرسانی شد.');
    }

    public function letterTimeline(Request $request, int $letterId)
    {
        $user = $request->user();
        abort_unless($user->can('view_any_letter'), 403);
        $letter = $this->letterQueryForUser($user)
            ->with(['Answer', 'referrals.by_users', 'referrals.users', 'referrals.activities.causer', 'activities.causer', 'user'])
            ->findOrFail($letterId);

        return response()->json(['data' => $letter->timeline()->values()]);
    }

    public function openNotification(Request $request, string $notificationId)
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $data = is_array($notification->data) ? $notification->data : [];
        $type = strtolower((string) ($data['resource_type'] ?? ''));
        $id = filter_var($data['resource_id'] ?? null, FILTER_VALIDATE_INT);

        if ($id && in_array($type, ['letters', 'letter'], true)) {
            abort_unless($request->user()->can('view_any_letter'), 403);
            $this->letterQueryForUser($request->user())->findOrFail($id);
            $notification->markAsRead();
            return redirect()->route('newui.record', ['type' => 'letter', 'recordId' => $id]);
        }
        if ($id && in_array($type, ['minutes', 'minute'], true)) {
            $user = $request->user();
            abort_unless($user->can('view_any_minutes'), 403);
            $query = Minutes::query()->whereKey($id);
            if (!$user->can('restore_any_minutes')) $query->where('typer_id', $user->id);
            $query->firstOrFail();
            $notification->markAsRead();
            return redirect()->route('newui.record', ['type' => 'minute', 'recordId' => $id]);
        }
        if ($id && in_array($type, ['tasks', 'task'], true)) {
            $user = $request->user();
            abort_unless($user->can('view_any_task'), 403);
            $query = Task::query()->whereKey($id);
            if (!$user->can('restore_any_task')) $query->where('Responsible_id', $user->id);
            $query->firstOrFail();
            $notification->markAsRead();
            return redirect()->route('newui.record', ['type' => 'task', 'recordId' => $id]);
        }

        $notification->markAsRead();
        return redirect()->route('newui')->with('status', 'اعلان خوانده شد.');
    }

    public function setNotificationRead(Request $request, string $notificationId)
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $data = $request->validate(['read' => ['required', 'boolean']]);
        $notification->forceFill(['read_at' => $data['read'] ? now() : null])->save();

        return redirect()->route('newui')->with('status', $data['read'] ? 'اعلان خوانده شد.' : 'اعلان به‌عنوان خوانده‌نشده علامت خورد.');
    }

    public function deleteNotification(Request $request, string $notificationId)
    {
        $request->user()->notifications()->findOrFail($notificationId)->delete();

        return redirect()->route('newui')->with('status', 'اعلان حذف شد.');
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $panel = Filament::getPanel('admin');
        abort_unless($panel && $user->canAccessPanel($panel), 403);

        $activeView = $request->query('view', 'home');
        abort_unless(in_array($activeView, ['home', 'tasks', 'letters', 'minutes', 'calendar', 'cartable'], true), 404);
        $taskStatus = $request->query('task_status', $activeView === 'calendar' ? 'all' : 'pending');
        abort_unless(in_array($taskStatus, ['pending', 'done', 'all'], true), 404);
        $jalaliDateParser = app(\App\Services\PersianJalaliDateParser::class);
        $parseFilterDate = function ($value) use ($jalaliDateParser) {
            if (!is_string($value) || trim($value) === '') return null;
            $parsed = $jalaliDateParser->parse($value);
            if ($parsed) return $parsed->startOfDay();
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                try { return \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $value); }
                catch (\Throwable) { return null; }
            }
            return null;
        };
        $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'group_id' => ['nullable', 'integer', 'exists:task_groups,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'letter_kind' => ['nullable', 'in:all,incoming,outgoing'],
            'letter_status' => ['nullable', 'integer'],
            'letter_type_id' => ['nullable', 'integer', 'exists:types,id'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', 'in:all,tasks,letters,minutes'],
            'cartable_checked' => ['nullable', 'in:all,unread,checked'],
            'task_due' => ['nullable', 'in:all,open,overdue,today,upcoming,completed,without_deadline'],
            'date_from' => ['nullable', 'string', 'max:30', function ($attribute, $value, $fail) use ($parseFilterDate) {
                if ($value !== null && !$parseFilterDate($value)) $fail('تاریخ شروع را به‌صورت شمسی مانند ۱۴۰۵/۰۱/۰۱ وارد کنید.');
            }],
            'date_to' => ['nullable', 'string', 'max:30', function ($attribute, $value, $fail) use ($parseFilterDate) {
                if ($value !== null && !$parseFilterDate($value)) $fail('تاریخ پایان را به‌صورت شمسی مانند ۱۴۰۵/۰۱/۰۱ وارد کنید.');
            }],
        ]);
        $dateFrom = $parseFilterDate($request->query('date_from'));
        $dateTo = $parseFilterDate($request->query('date_to'));
        if ($dateFrom && $dateTo && $dateTo->lt($dateFrom)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',
            ]);
        }

        $canTasks = $user->can('view_any_task');
        $canLetters = $user->can('view_any_letter');
        $canMinutes = $user->can('view_any_minutes');
        $canCartable = $user->can('view_any_cartable');
        $canAssistant = $canTasks || $canLetters || $canMinutes;
        abort_if($activeView === 'tasks' && !$canTasks, 403);
        abort_if($activeView === 'calendar' && !$canTasks && !$canLetters && !$canMinutes, 403);
        abort_if($activeView === 'letters' && !$canLetters, 403);
        abort_if($activeView === 'minutes' && !$canMinutes, 403);
        abort_if($activeView === 'cartable' && !$canCartable && !$canTasks, 403);

        $tasks = Task::query()->with(['project', 'task_group', 'minutes', 'responsible']);
        if (!$canTasks) $tasks->whereRaw('1 = 0');
        elseif (!$user->can('restore_any_task')) $tasks->where('Responsible_id', $user->id);
        if ($request->filled('group_id')) $tasks->whereHas('task_group', fn ($query) => $query->whereKey($request->integer('group_id')));
        if ($request->filled('project_id')) $tasks->whereHas('project', fn ($query) => $query->whereKey($request->integer('project_id')));
        if ($dateFrom) $tasks->whereDate('ended_at', '>=', $dateFrom->toDateString());
        if ($dateTo) $tasks->whereDate('ended_at', '<=', $dateTo->toDateString());

        $letters = Letter::query()->with(['type', 'user', 'organ']);
        if (!$canLetters) $letters->whereRaw('1 = 0');
        elseif (!$user->can('restore_any_letter')) {
            $letters->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('referrals', fn ($referrals) => $referrals->where('to_user_id', $user->id))
                    ->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id));
            });
        }
        if ($request->query('letter_kind') === 'incoming') $letters->where('kind', 0);
        elseif ($request->query('letter_kind') === 'outgoing') $letters->where('kind', 1);
        if ($request->filled('letter_status')) $letters->where('status', $request->integer('letter_status'));
        if ($request->filled('letter_type_id')) $letters->where('type_id', $request->integer('letter_type_id'));
        if ($activeView === 'letters' || $request->filled('letter_kind') || $request->filled('letter_type_id') || $request->filled('letter_status')) {
            if ($dateFrom) $letters->whereDate('created_at', '>=', $dateFrom->toDateString());
            if ($dateTo) $letters->whereDate('created_at', '<=', $dateTo->toDateString());
        }

        $minutes = Minutes::query()->with(['typer', 'task_creator'])->withCount(['tasks', 'approves']);
        if (!$canMinutes) $minutes->whereRaw('1 = 0');
        elseif (!$user->can('restore_any_minutes')) $minutes->where('typer_id', $user->id);
        if ($request->filled('author_id')) $minutes->where('typer_id', $request->integer('author_id'));
        if ($activeView === 'minutes' || $activeView === 'calendar') {
            if ($dateFrom) $minutes->whereDate('date', '>=', $dateFrom->toDateString());
            if ($dateTo) $minutes->whereDate('date', '<=', $dateTo->toDateString());
        }

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $tasks->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
                if (is_numeric($search)) $query->orWhere('id', (int) $search);
            });
            $letters->where(function ($query) use ($search) {
                $query->where('subject', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
                if (is_numeric($search)) $query->orWhere('id', (int) $search);
            });
            $minutes->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('text', 'like', "%{$search}%");
                if (is_numeric($search)) $query->orWhere('id', (int) $search);
            });
        }

        $taskCount = (clone $tasks)->count();
        $pendingTaskCount = (clone $tasks)->where(function ($query) {
            $query->whereNull('completed')->orWhere('completed', 0);
        })->count();
        $letterCount = (clone $letters)->count();
        $minuteCount = (clone $minutes)->count();

        $cartableChecked = $request->query('cartable_checked', 'unread');
        $taskDue = $request->query('task_due', 'open');
        $cartableLetters = Cartable::query()->where('user_id', $user->id)
            ->with(['letter.organ', 'letter.daftar', 'letter.projects']);
        if (!$canCartable) $cartableLetters->whereRaw('1 = 0');
        if ($cartableChecked === 'unread') $cartableLetters->where('checked', false);
        elseif ($cartableChecked === 'checked') $cartableLetters->where('checked', true);
        if ($search !== '') {
            $cartableLetters->whereHas('letter', fn ($query) => $query
                ->where('subject', 'like', "%{$search}%")
                ->orWhere('id', is_numeric($search) ? (int) $search : -1));
        }
        $cartableLetters = $cartableLetters->latest('cartables.created_at')->latest('cartables.id')
            ->paginate(10, ['*'], 'cartable_letters_page')->withQueryString();

        $cartableTasks = clone $tasks;
        if (!$canTasks) $cartableTasks->whereRaw('1 = 0');
        if ($taskDue === 'open') {
            $cartableTasks->where(fn ($query) => $query->whereNull('completed')->orWhere('completed', 0));
        } elseif ($taskDue === 'overdue') {
            $cartableTasks->whereNotNull('ended_at')->where('ended_at', '<', now())
                ->where(fn ($query) => $query->whereNull('completed')->orWhere('completed', '!=', 1));
        } elseif ($taskDue === 'today') {
            $cartableTasks->whereDate('ended_at', today())
                ->where(fn ($query) => $query->whereNull('completed')->orWhere('completed', '!=', 1));
        } elseif ($taskDue === 'upcoming') {
            $cartableTasks->whereNotNull('ended_at')->where('ended_at', '>', now())
                ->where(fn ($query) => $query->whereNull('completed')->orWhere('completed', '!=', 1));
        } elseif ($taskDue === 'completed') {
            $cartableTasks->where('completed', 1);
        } elseif ($taskDue === 'without_deadline') {
            $cartableTasks->whereNull('ended_at')
                ->where(fn ($query) => $query->whereNull('completed')->orWhere('completed', '!=', 1));
        }
        $cartableTasks->where('Responsible_id', $user->id);
        $cartableTasks = $cartableTasks->with(['letter', 'minutes', 'task_group', 'project'])
            ->latest('created_at')->latest('id')
            ->paginate(10, ['*'], 'cartable_tasks_page')->withQueryString();

        $notifications = $user->notifications()->latest()->limit(8)->get();
        $unreadNotificationCount = $user->unreadNotifications()->count();

        $items = collect();
        if ($activeView === 'tasks') {
            $taskList = clone $tasks;
            if ($taskStatus === 'pending') {
                $taskList->where(function ($query) {
                    $query->whereNull('completed')->orWhere('completed', 0);
                });
            } elseif ($taskStatus === 'done') {
                $taskList->where('completed', 1);
            }
            $items = $taskList->orderByRaw('CASE WHEN ended_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('ended_at')->orderByDesc('id')->paginate(12)->withQueryString();
        } elseif ($activeView === 'letters') {
            $items = $letters->latest('created_at')->paginate(12)->withQueryString();
        } elseif ($activeView === 'minutes') {
            $items = $minutes->latest('date')->paginate(12)->withQueryString();
        } elseif ($activeView === 'calendar') {
            $items = collect();
        } else {
            $items = collect([
                'tasks' => (clone $tasks)->where(function ($query) {
                    $query->whereNull('completed')->orWhere('completed', 0);
                })->orderByRaw('CASE WHEN ended_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('ended_at')->latest('id')->limit(5)->get(),
                'letters' => (clone $letters)->latest('created_at')->limit(4)->get(),
                'minutes' => (clone $minutes)->latest('date')->limit(4)->get(),
            ]);
        }

        return view('newui.index', [
            'activeView' => $activeView,
            'taskStatus' => $taskStatus,
            'filters' => $request->only(['group_id', 'project_id', 'letter_kind', 'letter_status', 'letter_type_id', 'author_id', 'date_from', 'date_to']),
            'filterDateValues' => [
                'date_from' => $dateFrom ? \Morilog\Jalali\Jalalian::fromCarbon($dateFrom)->format('Y/m/d') : '',
                'date_to' => $dateTo ? \Morilog\Jalali\Jalalian::fromCarbon($dateTo)->format('Y/m/d') : '',
            ],
            'filterOptions' => [
                'groups' => $canTasks ? TaskGroup::query()->orderBy('name')->pluck('name', 'id') : collect(),
                'projects' => $canTasks ? Project::query()->orderBy('name')->pluck('name', 'id') : collect(),
                'letterTypes' => $canLetters ? Type::query()->orderBy('name')->pluck('name', 'id') : collect(),
                'letterStatuses' => Letter::getStatusListDefine(),
                'authors' => $canMinutes && $user->can('restore_any_minutes')
                    ? \App\Models\User::query()->orderBy('name')->pluck('name', 'id') : collect(),
            ],
            'search' => $search,
            'items' => $items,
            'taskCount' => $taskCount,
            'pendingTaskCount' => $pendingTaskCount,
            'letterCount' => $letterCount,
            'minuteCount' => $minuteCount,
            'canTasks' => $canTasks,
            'canUpdateTasks' => $user->can('update_task'),
            'canLetters' => $canLetters,
            'canMinutes' => $canMinutes,
            'canCartable' => $canCartable,
            'canUpdateCartable' => $user->can('update_cartable'),
            'cartableLetters' => $cartableLetters,
            'cartableTasks' => $cartableTasks,
            'cartableChecked' => $cartableChecked,
            'taskDue' => $taskDue,
            'notifications' => $notifications,
            'unreadNotificationCount' => $unreadNotificationCount,
            'canAssistant' => $canAssistant,
            'canCreateTask' => $user->can('create_task'),
            'canCreateLetter' => $user->can('create_letter'),
            'canCreateMinutes' => $user->can('create_minutes'),
        ]);
    }

    private function letterQueryForUser($user)
    {
        $query = Letter::query();
        if (!$user->can('restore_any_letter')) {
            $query->where(function ($scope) use ($user) {
                $scope->where('user_id', $user->id)
                    ->orWhereHas('referrals', fn ($referrals) => $referrals->where('to_user_id', $user->id))
                    ->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id));
            });
        }

        return $query;
    }

    public function calendarEvents(Request $request)
    {
        $user = $request->user();
        $panel = Filament::getPanel('admin');
        abort_unless($panel && $user->canAccessPanel($panel), 403);
        abort_unless($user->can('view_any_task') || $user->can('view_any_letter') || $user->can('view_any_minutes'), 403);

        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'task_status' => ['nullable', 'in:pending,done,all'],
            'group_id' => ['nullable', 'integer', 'exists:task_groups,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'q' => ['nullable', 'string', 'max:200'],
            'letter_kind' => ['nullable', 'in:all,incoming,outgoing'],
            'letter_status' => ['nullable', 'integer'],
            'letter_type_id' => ['nullable', 'integer', 'exists:types,id'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', 'in:all,tasks,letters,minutes'],
        ]);

        $query = Task::query()->with('task_group')
            ->whereNotNull('started_at')
            ->where('started_at', '<', $request->query('end'))
            ->where(function ($builder) use ($request) {
                $builder->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $request->query('start'));
            });
        if (!$user->can('view_any_task')) $query->whereRaw('1 = 0');
        if (!in_array($request->query('type', 'all'), ['all', 'tasks'], true)) $query->whereRaw('1 = 0');
        if (!$user->can('restore_any_task')) $query->where('Responsible_id', $user->id);
        if ($request->query('task_status', 'pending') === 'pending') {
            $query->where(fn ($builder) => $builder->whereNull('completed')->orWhere('completed', 0));
        } elseif ($request->query('task_status') === 'done') {
            $query->where('completed', 1);
        }
        if ($request->filled('group_id')) $query->whereHas('task_group', fn ($builder) => $builder->whereKey($request->integer('group_id')));
        if ($request->filled('project_id')) $query->whereHas('project', fn ($builder) => $builder->whereKey($request->integer('project_id')));
        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
                if (is_numeric($search)) $builder->orWhere('id', (int) $search);
            });
        }

        $events = $query->get()->map(function (Task $task) {
            return [
                'id' => 'task-' . $task->id,
                'record_id' => $task->id,
                'type' => 'task',
                'type_title' => 'فعالیت',
                'title' => $task->name,
                'start' => $task->started_at,
                'end' => $task->ended_at ? \Illuminate\Support\Carbon::parse($task->ended_at)->addDay()->toDateString() : null,
                'backgroundColor' => $task->completed ? '#64748b' : '#1d4ed8',
                'borderColor' => $task->completed ? '#475569' : '#1e40af',
                'textColor' => '#ffffff',
                'url' => url('/newui/task/' . $task->id),
            ];
        });

        if ($user->can('view_any_letter') && in_array($request->query('type', 'all'), ['all', 'letters'], true)) {
            $letters = Letter::query()->where('created_at', '>=', $request->query('start'))
                ->where('created_at', '<', $request->query('end'));
            if (!$user->can('restore_any_letter')) $letters->where(fn ($scope) => $scope->where('user_id', $user->id)->orWhereHas('referrals', fn ($referrals) => $referrals->where('to_user_id', $user->id))->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id)));
            if ($request->query('letter_kind') === 'incoming') $letters->where('kind', 0);
            elseif ($request->query('letter_kind') === 'outgoing') $letters->where('kind', 1);
            if ($request->filled('letter_status')) $letters->where('status', $request->integer('letter_status'));
            if ($request->filled('letter_type_id')) $letters->where('type_id', $request->integer('letter_type_id'));
            if ($request->filled('q')) {
                $search = trim((string) $request->query('q'));
                $letters->where(fn ($q) => $q->where('subject', 'like', "%{$search}%")->orWhere('summary', 'like', "%{$search}%"));
            }
            $events = $events->concat($letters->get()->map(fn (Letter $letter) => [
                'id' => 'letter-' . $letter->id, 'record_id' => $letter->id, 'type' => 'letter', 'type_title' => 'نامه',
                'title' => $letter->subject, 'start' => $letter->created_at?->toDateString(), 'allDay' => true,
                'backgroundColor' => '#16a34a', 'borderColor' => '#15803d', 'textColor' => '#ffffff',
                'url' => url('/newui/letter/' . $letter->id),
            ]));
        }

        if ($user->can('view_any_minutes') && in_array($request->query('type', 'all'), ['all', 'minutes'], true)) {
            $minutes = Minutes::query()->where('date', '>=', $request->query('start'))
                ->where('date', '<', $request->query('end'));
            if (!$user->can('restore_any_minutes')) $minutes->where('typer_id', $user->id);
            if ($request->filled('author_id')) $minutes->where('typer_id', $request->integer('author_id'));
            if ($request->filled('q')) {
                $search = trim((string) $request->query('q'));
                $minutes->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('text', 'like', "%{$search}%"));
            }
            $events = $events->concat($minutes->get()->map(fn (Minutes $minute) => [
                'id' => 'minute-' . $minute->id, 'record_id' => $minute->id, 'type' => 'minute', 'type_title' => 'صورت‌جلسه',
                'title' => $minute->title, 'start' => \Illuminate\Support\Carbon::parse($minute->getRawOriginal('date'))->toDateString(), 'allDay' => true,
                'backgroundColor' => '#ea580c', 'borderColor' => '#c2410c', 'textColor' => '#ffffff',
                'url' => url('/newui/minute/' . $minute->id),
            ]));
        }

        return response()->json($events->values());
    }

    public function showRecord(Request $request, string $type, int $recordId)
    {
        $user = $request->user();
        $panel = Filament::getPanel('admin');
        abort_unless($panel && $user->canAccessPanel($panel), 403);

        if ($type === 'task') {
            abort_unless($user->can('view_any_task'), 403);
            $query = Task::query()->with(['project', 'task_group', 'minutes', 'letter', 'responsible', 'appendix_others']);
            if (!$user->can('restore_any_task')) $query->where('Responsible_id', $user->id);
            $record = $query->findOrFail($recordId);
            $editUrl = TaskResource::getUrl('edit', [$record->id]);
            $backUrl = url('/newui?view=tasks');
            $title = 'فعالیت';
        } elseif ($type === 'letter') {
            abort_unless($user->can('view_any_letter'), 403);
            $query = Letter::query()->with(['type', 'user', 'organ', 'daftar', 'letter', 'projects', 'customers', 'organs_owner', 'users', 'Answer', 'Appendix', 'tasks.responsible']);
            if (!$user->can('restore_any_letter')) {
                $query->where(function ($scope) use ($user) {
                    $scope->where('user_id', $user->id)
                        ->orWhereHas('referrals', fn ($referrals) => $referrals->where('to_user_id', $user->id))
                        ->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id));
                });
            }
            $record = $query->findOrFail($recordId);
            $editUrl = LetterResource::getUrl('edit', [$record->id]);
            $backUrl = url('/newui?view=letters');
            $title = 'نامه';
        } elseif ($type === 'minute') {
            abort_unless($user->can('view_any_minutes'), 403);
            $query = Minutes::query()->with(['typer', 'task_creator', 'organ', 'projects', 'tasks.responsible', 'approves', 'appendix_others']);
            if (!$user->can('restore_any_minutes')) $query->where('typer_id', $user->id);
            $record = $query->findOrFail($recordId);
            $editUrl = MinutesResource::getUrl('edit', [$record->id]);
            $backUrl = url('/newui?view=minutes');
            $title = 'صورت‌جلسه';
        } else {
            abort(404);
        }

        $editOptions = $request->boolean('edit') ? [
            'types' => Type::query()->orderBy('name')->pluck('name', 'id'),
            'organs' => Organ::query()->orderBy('name')->pluck('name', 'id'),
            'projects' => Project::query()->orderBy('name')->pluck('name', 'id'),
            'cities' => City::query()->orderBy('name')->pluck('name', 'id'),
            'taskGroups' => TaskGroup::query()->orderBy('name')->pluck('name', 'id'),
            'minuteGroups' => MinutesGroup::query()->orderBy('name')->pluck('name', 'id'),
            'users' => $user->can('restore_any_task') ? User::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'tasks' => Task::query()->orderByDesc('id')->limit(500)->pluck('name', 'id'),
            'letters' => Letter::query()->orderByDesc('id')->limit(500)->pluck('subject', 'id'),
            'minutes' => Minutes::query()->orderByDesc('id')->limit(500)->pluck('title', 'id'),
        ] : [];
        if ($editOptions) {
            if ($type === 'task') {
                if ($record->minutes && !$editOptions['minutes']->has($record->minutes->id)) $editOptions['minutes']->put($record->minutes->id, $record->minutes->title);
                if ($record->letter && !$editOptions['letters']->has($record->letter->id)) $editOptions['letters']->put($record->letter->id, $record->letter->subject);
            } elseif ($type === 'letter' && $record->letter && !$editOptions['letters']->has($record->letter->id)) {
                $editOptions['letters']->put($record->letter->id, $record->letter->subject);
            } elseif ($type === 'minute' && $record->task_creator && !$editOptions['tasks']->has($record->task_creator->id)) {
                $editOptions['tasks']->put($record->task_creator->id, $record->task_creator->name);
            }
        }

        return view('newui.record', [
            'type' => $type,
            'title' => $title,
            'record' => $record,
            'backUrl' => $backUrl,
            'editUrl' => $editUrl,
            'canEdit' => $user->can('update_' . $type),
            'editing' => $request->boolean('edit') && $user->can('update_' . $type),
            'editOptions' => $editOptions,
        ]);
    }
}
