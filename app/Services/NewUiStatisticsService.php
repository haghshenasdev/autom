<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\Minutes;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Morilog\Jalali\Jalalian;

class NewUiStatisticsService
{
    public function dashboard(User $user, ?Carbon $from = null, ?Carbon $to = null, ?string $place = null, ?array $entities = null): array
    {
        $to ??= now()->endOfDay();
        $from ??= $to->copy()->subMonths(11)->startOfMonth();
        $allowed = $this->allowed($user);
        if ($entities !== null) $allowed = array_values(array_intersect($allowed, $entities));
        $totals = ['tasks' => null, 'letters' => null, 'minutes' => null];
        $trend = [];
        $tasksByStatus = null;
        $lettersByKind = null;

        foreach ($allowed as $entity) {
            $query = $this->query($user, $entity, $from, $to, $place);
            $totals[$entity] = (clone $query)->count();
            $dateColumn = match ($entity) {
                'tasks' => 'COALESCE(started_at, created_at)',
                'letters' => 'created_at',
                default => '`date`',
            };
            $rows = (clone $query)->selectRaw("DATE_FORMAT({$dateColumn}, '%Y-%m') as period, COUNT(*) as total")
                ->groupBy('period')->orderBy('period')->get();
            foreach ($rows as $row) $trend[$row->period][$entity] = (int) $row->total;
        }

        if (in_array('tasks', $allowed, true)) {
            $statusQuery = $this->query($user, 'tasks', $from, $to, $place);
            $tasksByStatus = [
                'انجام‌نشده' => (clone $statusQuery)->where(fn (Builder $q) => $q->whereNull('completed')->orWhere('completed', 0))->count(),
                'انجام‌شده' => (clone $statusQuery)->where('completed', 1)->count(),
            ];
        }
        if (in_array('letters', $allowed, true)) {
            $kindQuery = $this->query($user, 'letters', $from, $to, $place);
            $lettersByKind = [
                'وارده' => (clone $kindQuery)->where('kind', 0)->count(),
                'صادره' => (clone $kindQuery)->where('kind', 1)->count(),
            ];
        }

        $periods = [];
        $cursor = $from->copy()->startOfMonth();
        $last = $to->copy()->startOfMonth();
        while ($cursor <= $last && count($periods) < 36) {
            $key = $cursor->format('Y-m');
            $periods[] = [
                'key' => $key,
                'label' => Jalalian::fromCarbon($cursor)->format('Y/m'),
                'tasks' => (int) ($trend[$key]['tasks'] ?? 0),
                'letters' => (int) ($trend[$key]['letters'] ?? 0),
                'minutes' => (int) ($trend[$key]['minutes'] ?? 0),
            ];
            $cursor->addMonth();
        }

        return [
            'totals' => $totals,
            'periods' => $periods,
            'task_status' => $tasksByStatus,
            'letter_kind' => $lettersByKind,
            'range' => ['from' => Jalalian::fromCarbon($from)->format('Y/m/d'), 'to' => Jalalian::fromCarbon($to)->format('Y/m/d')],
            'allowed' => $allowed,
        ];
    }

    public function allowed(User $user): array
    {
        return array_values(array_filter([
            $user->can('view_any_task') ? 'tasks' : null,
            $user->can('view_any_letter') ? 'letters' : null,
            $user->can('view_any_minutes') ? 'minutes' : null,
        ]));
    }

    public function query(User $user, string $entity, Carbon $from, Carbon $to, ?string $place = null): Builder
    {
        $place = trim((string) $place);
        if ($entity === 'tasks') {
            $query = Task::query();
            if (!$user->can('restore_any_task')) $query->where('Responsible_id', $user->id);
            $query->whereRaw('DATE(COALESCE(started_at, created_at)) >= ?', [$from->toDateString()])
                ->whereRaw('DATE(COALESCE(started_at, created_at)) <= ?', [$to->toDateString()]);
            if ($place !== '') $query->where(fn (Builder $q) => $q->whereHas('city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%"))
                ->orWhereHas('project.city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%")));
            return $query;
        }
        if ($entity === 'letters') {
            $query = Letter::query()->whereBetween('created_at', [$from, $to]);
            if (!$user->can('restore_any_letter')) $query->where(fn (Builder $q) => $q->where('user_id', $user->id)
                ->orWhereHas('referrals', fn (Builder $r) => $r->where('to_user_id', $user->id))
                ->orWhereHas('users', fn (Builder $u) => $u->where('users.id', $user->id)));
            if ($place !== '') $query->where(fn (Builder $q) => $q->whereHas('customers.city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%"))
                ->orWhereHas('organ', fn (Builder $o) => $o->where('name', 'like', "%{$place}%"))
                ->orWhereHas('organs_owner', fn (Builder $o) => $o->where('name', 'like', "%{$place}%")));
            return $query;
        }
        if ($entity === 'minutes') {
            $query = Minutes::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            if (!$user->can('restore_any_minutes')) $query->where('typer_id', $user->id);
            if ($place !== '') $query->where(fn (Builder $q) => $q->whereHas('projects.city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%"))
                ->orWhereHas('organ', fn (Builder $o) => $o->where('name', 'like', "%{$place}%")));
            return $query;
        }
        abort(403);
    }

    public function renderPng(array $chart): ?string
    {
        $response = Http::timeout(20)->accept('image/png')->post('https://quickchart.io/chart', [
            'version' => '4', 'format' => 'png', 'width' => 900, 'height' => 480,
            'backgroundColor' => '#ffffff', 'chart' => $chart,
        ]);
        return $response->successful() && str_starts_with($response->header('Content-Type', ''), 'image/')
            ? $response->body() : null;
    }
}
