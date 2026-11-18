<?php

namespace App\Http\Controllers;

use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\TaskResource;
use App\Models\Letter;
use App\Models\Cartable;
use App\Models\Minutes;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\Type;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class NewUiController extends Controller
{
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
        abort_if($activeView === 'calendar' && !$canTasks, 403);
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
        $cartableLetters = $cartableLetters->latest('cartables.id')
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
        $cartableTasks = $cartableTasks->with(['letter', 'minutes', 'task_group', 'project'])
            ->orderByRaw('CASE WHEN ended_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('ended_at')->latest('id')
            ->paginate(10, ['*'], 'cartable_tasks_page')->withQueryString();

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
            'canAssistant' => $canAssistant,
            'canCreateTask' => $user->can('create_task'),
            'canCreateLetter' => $user->can('create_letter'),
            'canCreateMinutes' => $user->can('create_minutes'),
        ]);
    }

    public function calendarEvents(Request $request)
    {
        $user = $request->user();
        $panel = Filament::getPanel('admin');
        abort_unless($panel && $user->canAccessPanel($panel), 403);
        abort_unless($user->can('view_any_task'), 403);

        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'task_status' => ['nullable', 'in:pending,done,all'],
            'group_id' => ['nullable', 'integer', 'exists:task_groups,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $query = Task::query()->with('task_group')
            ->whereNotNull('started_at')
            ->where('started_at', '<', $request->query('end'))
            ->where(function ($builder) use ($request) {
                $builder->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $request->query('start'));
            });
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

        return response()->json($query->get()->map(function (Task $task) {
            $groupIds = $task->task_group->pluck('id');
            $prefix = $groupIds->contains(1) ? '🧰 ' : ($groupIds->contains(33) ? '📝 ' : ($groupIds->contains(2) ? '🕹️ ' : ''));

            return [
                'id' => $task->id,
                'title' => $prefix . $task->name,
                'start' => $task->started_at,
                'end' => $task->ended_at,
                'backgroundColor' => $task->completed ? '#64748b' : '#1d4ed8',
                'borderColor' => $task->completed ? '#475569' : '#1e40af',
                'url' => url('/newui/task/' . $task->id),
            ];
        }));
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

        return view('newui.record', [
            'type' => $type,
            'title' => $title,
            'record' => $record,
            'backUrl' => $backUrl,
            'editUrl' => $editUrl,
            'canEdit' => $user->can('update_' . $type),
        ]);
    }
}
