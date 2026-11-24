<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\Minutes;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\MinutesGroup;
use App\Models\City;
use App\Models\Type;
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
        $dimensions = [];

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
            $dimensions['task_groups'] = $this->groupBreakdown($statusQuery, TaskGroup::query(), 'group', 'tasks');
            $dimensions['task_cities'] = $this->foreignBreakdown($statusQuery, City::query(), 'city_id');
            $dimensions['task_progress'] = [
                'بدون پیشرفت' => (clone $statusQuery)->where(fn (Builder $q) => $q->whereNull('progress')->orWhere('progress', '<=', 0))->count(),
                '۱ تا ۴۹٪' => (clone $statusQuery)->whereBetween('progress', [1, 49])->count(),
                '۵۰ تا ۹۹٪' => (clone $statusQuery)->whereBetween('progress', [50, 99])->count(),
                '۱۰۰٪' => (clone $statusQuery)->where('progress', '>=', 100)->count(),
            ];
            $timed = (clone $statusQuery)->whereNotNull('started_at')->whereNotNull('ended_at');
            $dimensions['task_duration'] = [
                'تا ۵ روز' => (clone $timed)->whereRaw('DATEDIFF(ended_at, started_at) <= 5')->count(),
                'بیش از ۵ روز' => (clone $timed)->whereRaw('DATEDIFF(ended_at, started_at) > 5')->count(),
            ];
            $dimensions['task_amount'] = (float) (clone $statusQuery)->sum('amount');
        }
        if (in_array('letters', $allowed, true)) {
            $kindQuery = $this->query($user, 'letters', $from, $to, $place);
            $lettersByKind = [
                'وارده' => (clone $kindQuery)->where('kind', 0)->count(),
                'صادره' => (clone $kindQuery)->where('kind', 1)->count(),
            ];
            $dimensions['letter_types'] = $this->foreignBreakdown($kindQuery, Type::query(), 'type_id');
            $dimensions['letter_status'] = $this->scalarBreakdown($kindQuery, 'status');
        }
        if (in_array('minutes', $allowed, true)) {
            $minutesQuery = $this->query($user, 'minutes', $from, $to, $place);
            $dimensions['minute_groups'] = $this->groupBreakdown($minutesQuery, MinutesGroup::query(), 'group', 'minutes');
            $authorRows = (clone $minutesQuery)->selectRaw('typer_id, COUNT(*) as total')->groupBy('typer_id')->orderByDesc('total')->limit(8)->get();
            $authorNames = User::query()->whereIn('id', $authorRows->pluck('typer_id')->filter())->pluck('name', 'id');
            $dimensions['minute_authors'] = $authorRows->mapWithKeys(fn ($row) => [($authorNames[$row->typer_id] ?? 'بدون نویسنده') => (int) $row->total])->all();
            $dimensions['minutes_with_file'] = (clone $minutesQuery)->whereNotNull('file')->where('file', '<>', '')->count();
            $dimensions['minutes_without_file'] = max(0, ($totals['minutes'] ?? 0) - $dimensions['minutes_with_file']);
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
            'dimensions' => $dimensions,
            'range' => ['from' => Jalalian::fromCarbon($from)->format('Y/m/d'), 'to' => Jalalian::fromCarbon($to)->format('Y/m/d')],
            'allowed' => $allowed,
        ];
    }

    private function groupBreakdown(Builder $base, Builder $groups, string $baseRelation, string $categoryRelation): array
    {
        $groupIds = (clone $base)->select('id');
        $rows = $groups->whereHas($categoryRelation, fn (Builder $q) => $q->whereIn($q->getModel()->getTable().'.id', $groupIds))
            ->orderBy('name')->limit(30)->get(['id', 'name']);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->name] = (clone $base)->whereHas($baseRelation, fn (Builder $q) => $q->whereKey($row->id))->count();
        }
        return $this->topBreakdown($result);
    }

    private function foreignBreakdown(Builder $base, Builder $categories, string $foreignKey): array
    {
        $rows = $categories->whereIn('id', (clone $base)->whereNotNull($foreignKey)->select($foreignKey))
            ->orderBy('name')->limit(30)->get(['id', 'name']);
        $result = [];
        foreach ($rows as $row) $result[$row->name] = (clone $base)->where($foreignKey, $row->id)->count();
        return $this->topBreakdown($result);
    }

    private function scalarBreakdown(Builder $query, string $column): array
    {
        return (clone $query)->selectRaw("COALESCE(CAST({$column} AS CHAR), 'بدون وضعیت') as label, COUNT(*) as total")
            ->groupBy('label')->orderByDesc('total')->limit(8)->get()
            ->mapWithKeys(function ($row) use ($column) {
                $label = (string) $row->label;
                if ($column === 'status' && is_numeric($label)) $label = Letter::getStatusLabel((int) $label);
                return [$label => (int) $row->total];
            })->all();
    }

    private function topBreakdown(array $values, int $limit = 8): array
    {
        arsort($values);
        $top = array_slice($values, 0, $limit, true);
        $other = array_sum(array_slice($values, $limit, null, true));
        if ($other > 0) $top['سایر'] = $other;
        return $top;
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
            if ($place !== '') $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$place}%")
                ->orWhere('description', 'like', "%{$place}%")
                ->orWhereHas('city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%"))
                ->orWhereHas('project.city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%"))
                ->orWhereHas('organ', fn (Builder $o) => $o->where('name', 'like', "%{$place}%")->orWhere('address', 'like', "%{$place}%")));
            return $query;
        }
        if ($entity === 'letters') {
            $query = Letter::query()->whereBetween('created_at', [$from, $to]);
            if (!$user->can('restore_any_letter')) $query->where(fn (Builder $q) => $q->where('user_id', $user->id)
                ->orWhereHas('referrals', fn (Builder $r) => $r->where('to_user_id', $user->id))
                ->orWhereHas('users', fn (Builder $u) => $u->where('users.id', $user->id)));
            if ($place !== '') $query->where(fn (Builder $q) => $q->where('subject', 'like', "%{$place}%")
                ->orWhere('summary', 'like', "%{$place}%")
                ->orWhere('description', 'like', "%{$place}%")
                ->orWhereHas('customers.city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%"))
                ->orWhereHas('organ', fn (Builder $o) => $o->where('name', 'like', "%{$place}%")->orWhere('address', 'like', "%{$place}%"))
                ->orWhereHas('organs_owner', fn (Builder $o) => $o->where('name', 'like', "%{$place}%")->orWhere('address', 'like', "%{$place}%")));
            return $query;
        }
        if ($entity === 'minutes') {
            $query = Minutes::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            if (!$user->can('restore_any_minutes')) $query->where('typer_id', $user->id);
            if ($place !== '') $query->where(fn (Builder $q) => $q->where('title', 'like', "%{$place}%")
                ->orWhere('text', 'like', "%{$place}%")
                ->orWhereHas('projects.city', fn (Builder $c) => $c->where('name', 'like', "%{$place}%"))
                ->orWhereHas('organ', fn (Builder $o) => $o->where('name', 'like', "%{$place}%")->orWhere('address', 'like', "%{$place}%")));
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
