<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Minutes;
use App\Models\Task;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class NewUiController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $panel = Filament::getPanel('admin');
        abort_unless($panel && $user->canAccessPanel($panel), 403);

        $activeView = $request->query('view', 'home');
        abort_unless(in_array($activeView, ['home', 'tasks', 'letters', 'minutes'], true), 404);
        $taskStatus = $request->query('task_status', 'pending');
        abort_unless(in_array($taskStatus, ['pending', 'done', 'all'], true), 404);

        $canTasks = $user->can('view_any_task');
        $canLetters = $user->can('view_any_letter');
        $canMinutes = $user->can('view_any_minutes');
        abort_if($activeView === 'tasks' && !$canTasks, 403);
        abort_if($activeView === 'letters' && !$canLetters, 403);
        abort_if($activeView === 'minutes' && !$canMinutes, 403);

        $tasks = Task::query()->with(['project', 'task_group', 'minutes']);
        if (!$canTasks) $tasks->whereRaw('1 = 0');
        elseif (!$user->can('restore_any_task')) $tasks->where('Responsible_id', $user->id);

        $letters = Letter::query()->with(['type', 'user', 'organ']);
        if (!$canLetters) $letters->whereRaw('1 = 0');
        elseif (!$user->can('restore_any_letter')) {
            $letters->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('referrals', fn ($referrals) => $referrals->where('to_user_id', $user->id))
                    ->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id));
            });
        }

        $minutes = Minutes::query()->with(['typer', 'task_creator'])->withCount(['tasks', 'approves']);
        if (!$canMinutes) $minutes->whereRaw('1 = 0');
        elseif (!$user->can('restore_any_minutes')) $minutes->where('typer_id', $user->id);

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
            'search' => $search,
            'items' => $items,
            'taskCount' => $taskCount,
            'pendingTaskCount' => $pendingTaskCount,
            'letterCount' => $letterCount,
            'minuteCount' => $minuteCount,
            'canTasks' => $canTasks,
            'canLetters' => $canLetters,
            'canMinutes' => $canMinutes,
            'canCreateTask' => $user->can('create_task'),
            'canCreateLetter' => $user->can('create_letter'),
            'canCreateMinutes' => $user->can('create_minutes'),
        ]);
    }
}
