<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\AnswerResource;
use App\Filament\Resources\AppendixResource;
use App\Filament\Resources\ApproveResource;
use App\Filament\Resources\CartableResource;
use App\Filament\Resources\CityResource;
use App\Filament\Resources\ContentGroupResource;
use App\Filament\Resources\ContentResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesGroupResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\OrganResource;
use App\Filament\Resources\OrganTypeResource;
use App\Filament\Resources\ProjectGroupResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ReferralResource;
use App\Filament\Resources\ReplicationResource;
use App\Filament\Resources\TaskGroupResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\TitleholderResource;
use App\Filament\Resources\TypeResource;
use App\Filament\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\AppendixOther;
use App\Models\Approve;
use App\Models\Cartable;
use App\Models\City;
use App\Models\Content;
use App\Models\ContentGroup;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Minutes;
use App\Models\MinutesGroup;
use App\Models\Organ;
use App\Models\OrganType;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\Referral;
use App\Models\Replication;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\Titleholder;
use App\Models\Type;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class MobileApiController extends Controller
{
    /**
     * The mobile API deliberately uses the same Filament Resource query and
     * the same permission names as the admin panel. This keeps the mobile
     * client from accidentally seeing records that Filament hides.
     */
    private const RESOURCES = [
        'letters' => [Letter::class, LetterResource::class, 'letter', ['user','type','organ','daftar','customers','organs_owner','users','projects','referrals','Answer']],
        'minutes' => [Minutes::class, MinutesResource::class, 'minutes', ['typer','task_creator','organ','group','tasks','approves','appendix_others']],
        'tasks' => [Task::class, TaskResource::class, 'task', ['creator','responsible','organ','city','minutes','project','task_group','appendix_others']],
        'projects' => [Project::class, ProjectResource::class, 'project', ['user','organ','city','group','tasks','letters']],
        'task-groups' => [TaskGroup::class, TaskGroupResource::class, 'task_group', ['parent','tasks']],
        'project-groups' => [ProjectGroup::class, ProjectGroupResource::class, 'project_group', ['parent','projects']],
        'minutes-groups' => [MinutesGroup::class, MinutesGroupResource::class, 'minutes_group', ['parent','minutes']],
        'organs' => [Organ::class, OrganResource::class, 'organ', ['organ_type','letters','projects','tasks']],
        'organ-types' => [OrganType::class, OrganTypeResource::class, 'organ_type', ['organs']],
        'cities' => [City::class, CityResource::class, 'city', ['projects','tasks','customers']],
        'customers' => [Customer::class, CustomerResource::class, 'customer', ['letters','city']],
        'types' => [Type::class, TypeResource::class, 'type', ['letters']],
        'titleholders' => [Titleholder::class, TitleholderResource::class, 'titleholder', ['organ','letters']],
        'referrals' => [Referral::class, ReferralResource::class, 'referral', ['letter','users','by_users']],
        'answers' => [Answer::class, AnswerResource::class, 'answer', ['letter']],
        'approves' => [Approve::class, ApproveResource::class, 'approve', ['minute','project']],
        'appendixes' => [AppendixOther::class, AppendixResource::class, 'appendix', ['appendix_other']],
        'replications' => [Replication::class, ReplicationResource::class, 'replication', ['letter']],
        'content-groups' => [ContentGroup::class, ContentGroupResource::class, 'content_group', ['contents']],
        'contents' => [Content::class, ContentResource::class, 'content', ['group']],
        'users' => [User::class, UserResource::class, 'user', ['roles','permissions']],
    ];

    public function permissions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'can' => collect([
                    'view_any_letter','create_letter','update_letter','delete_letter',
                    'view_any_minutes','create_minutes','update_minutes','delete_minutes',
                    'view_any_task','create_task','update_task','delete_task',
                    'view_any_project','create_project','update_project','delete_project',
                    'view_any_cartable','update_cartable','delete_cartable',
                    'view_any_referral','create_referral','update_referral','delete_referral',
                    'view_any_user','create_user','update_user','delete_user',
                ])->mapWithKeys(fn ($permission) => [$permission => $user->can($permission)]),
            ],
        ]);
    }

    public function index(Request $request, string $resource)
    {
        [$model, $filamentResource, $permission, $includes] = $this->definition($resource);
        $this->ensurePermission($request->user(), "view_any_{$permission}");

        // Re-use the exact query scope used by Filament.
        $query = $filamentResource::getEloquentQuery();

        $requestedIncludes = collect(explode(',', (string) $request->query('include', '')))
            ->filter()
            ->intersect($includes)
            ->values()
            ->all();

        $query->with($this->safeIncludes($model, $requestedIncludes) ?: $this->safeIncludes($model, $this->defaultIncludes($resource)));

        $query = $this->applyQuery($query, $model, $request);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($item) => $this->transform($item, $resource))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, string $resource, int $id)
    {
        [$model, $filamentResource, $permission] = $this->definition($resource);
        $item = $filamentResource::getEloquentQuery()->findOrFail($id);
        $this->ensurePermission($request->user(), "view_{$permission}", $item);

        $item->load($this->safeIncludes($model, $this->defaultIncludes($resource)));

        return response()->json(['data' => $this->transform($item, $resource)]);
    }

    public function store(Request $request, string $resource)
    {
        [$model, $filamentResource, $permission] = $this->definition($resource);
        $this->ensurePermission($request->user(), "create_{$permission}");

        $data = $this->validatedData($request, $resource, false);
        $item = DB::transaction(function () use ($model, $data, $request, $resource) {
            $modelObject = new $model;
            $this->fillModel($modelObject, $data, $request->user());
            $modelObject->save();
            $this->storeUploadedFile($modelObject, $request, $resource);
            $this->syncRelations($modelObject, $data, $resource);
            return $modelObject->fresh($this->safeIncludes($model, $this->defaultIncludes($resource)));
        });

        return response()->json(['data' => $this->transform($item, $resource)], Response::HTTP_CREATED);
    }

    public function update(Request $request, string $resource, int $id)
    {
        [$model, $filamentResource, $permission] = $this->definition($resource);
        $item = $filamentResource::getEloquentQuery()->findOrFail($id);
        $this->ensurePermission($request->user(), "update_{$permission}", $item);

        $data = $this->validatedData($request, $resource, true);
        DB::transaction(function () use ($item, $data, $request, $resource) {
            $this->fillModel($item, $data, $request->user());
            $item->save();
            $this->storeUploadedFile($item, $request, $resource);
            $this->syncRelations($item, $data, $resource);
        });

        $item = $item->fresh($this->safeIncludes($model, $this->defaultIncludes($resource)));
        return response()->json(['data' => $this->transform($item, $resource)]);
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        [$model, $filamentResource, $permission] = $this->definition($resource);
        $item = $filamentResource::getEloquentQuery()->findOrFail($id);
        $this->ensurePermission($request->user(), "delete_{$permission}", $item);
        $item->delete();

        return response()->json(['message' => 'رکورد حذف شد.']);
    }

    public function timeline(Request $request, int $id)
    {
        $letter = LetterResource::getEloquentQuery()
            ->with(['Answer', 'referrals.by_users', 'referrals.users', 'referrals.activities.causer', 'activities.causer', 'user'])
            ->findOrFail($id);

        $this->ensurePermission($request->user(), 'view_letter', $letter);

        return response()->json([
            'data' => collect($letter->timeline())->values(),
        ]);
    }

    public function files(Request $request, string $resource, int $id)
    {
        $allowed = ['letters', 'minutes', 'tasks'];
        abort_unless(in_array($resource, $allowed, true), 404);

        if ($resource === 'letters') {
            $item = LetterResource::getEloquentQuery()->findOrFail($id);
            $this->ensurePermission($request->user(), 'view_letter', $item);
            $files = [];

            if ($item->file) {
                $files[] = [
                    'id' => 'main',
                    'title' => 'فایل اصلی نامه',
                    'extension' => strtolower((string)$item->file),
                    'url' => url("/api/mobile/v1/files/letters/{$id}/main"),
                ];
            }

            foreach ($item->Appendix as $appendix) {
                if (!$appendix->file) continue;
                $files[] = [
                    'id' => (string)$appendix->id,
                    'title' => $appendix->title ?: 'پیوست',
                    'extension' => strtolower((string)$appendix->file),
                    'url' => url("/api/mobile/v1/files/letters/{$id}/appendix-{$appendix->id}"),
                ];
            }

            return response()->json(['data' => $files]);
        }

        if ($resource === 'minutes') {
            $item = MinutesResource::getEloquentQuery()->with('appendix_others')->findOrFail($id);
            $this->ensurePermission($request->user(), 'view_minutes', $item);
            return response()->json(['data' => $this->appendixFiles($item, 'minutes', $id)]);
        }

        $item = TaskResource::getEloquentQuery()->with('appendix_others')->findOrFail($id);
        $this->ensurePermission($request->user(), 'view_task', $item);
        return response()->json(['data' => $this->appendixFiles($item, 'tasks', $id)]);
    }

    public function file(Request $request, string $resource, int $id, string $fileKey)
    {
        abort_unless(in_array($resource, ['letters', 'minutes', 'tasks'], true), 404);

        if ($resource === 'letters') {
            $item = LetterResource::getEloquentQuery()->findOrFail($id);
            $this->ensurePermission($request->user(), 'view_letter', $item);

            if ($fileKey === 'main' && $item->file) {
                return $this->binaryFromDisk('private', $item->getFilePath());
            }

            if (str_starts_with($fileKey, 'appendix-')) {
                $appendixId = (int) str_replace('appendix-', '', $fileKey);
                $appendix = $item->Appendix()->findOrFail($appendixId);
                $this->ensurePermission($request->user(), 'view_letter', $item);
                return $this->binaryFromDisk('private_appendix_other', $appendix->getFilePath());
            }

            abort(404);
        }

        $model = $resource === 'minutes'
            ? MinutesResource::getEloquentQuery()->with('appendix_others')->findOrFail($id)
            : TaskResource::getEloquentQuery()->with('appendix_others')->findOrFail($id);

        $this->ensurePermission($request->user(), "view_" . ($resource === 'minutes' ? 'minutes' : 'task'), $model);

        if ($fileKey === 'main' && $model->file) {
            $disk = $resource === 'minutes' ? 'private_appendix_other' : 'private';
            return $this->binaryFromDisk($disk, $model->getFilePath());
        }

        if (str_starts_with($fileKey, 'appendix-')) {
            $appendixId = (int) str_replace('appendix-', '', $fileKey);
            $appendix = $model->appendix_others()->findOrFail($appendixId);
            return $this->binaryFromDisk('private_appendix_other', $appendix->getFilePath());
        }

        abort(404);
    }

    private function appendixFiles(Model $item, string $resource, int $id): array
    {
        $files = [];

        if ($item->file && method_exists($item, 'getFilePath')) {
            $files[] = [
                'id' => 'main',
                'title' => 'فایل اصلی',
                'extension' => strtolower((string)$item->file),
                'url' => url("/api/mobile/v1/files/{$resource}/{$id}/main"),
            ];
        }

        foreach (($item->appendix_others ?? collect()) as $appendix) {
            if (!$appendix->file) continue;
            $files[] = [
                'id' => (string)$appendix->id,
                'title' => $appendix->title ?: 'پیوست',
                'extension' => strtolower((string)$appendix->file),
                'mime' => $this->mimeFromExtension((string)$appendix->file),
                'url' => url("/api/mobile/v1/files/{$resource}/{$id}/appendix-{$appendix->id}"),
            ];
        }

        return $files;
    }

    private function mimeFromExtension(string $extension): string
    {
        return match (strtolower(ltrim($extension, '.'))) {
            'jpg','jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    private function binaryFromDisk(string $disk, ?string $path)
    {
        abort_if(!$path || !Storage::disk($disk)->exists($path), 404);
        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Type' => Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function cartable(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'view_any_cartable');

        $query = Cartable::query()
            ->where('user_id', $user->id)
            ->with(['letter.organ','letter.daftar','letter.customers','letter.organs_owner','letter.projects']);

        if ($request->filled('filter.checked')) {
            $query->where('checked', filter_var($request->input('filter.checked'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('filter.search')) {
            $term = trim($request->input('filter.search'));
            $query->whereHas('letter', fn ($q) => $q->where('subject','like',"%{$term}%")
                ->orWhere('id', is_numeric($term) ? $term : -1));
        }

        if ($request->filled('filter.from')) {
            $query->whereDate('updated_at', '>=', $request->input('filter.from'));
        }
        if ($request->filled('filter.to')) {
            $query->whereDate('updated_at', '<=', $request->input('filter.to'));
        }

        $perPage = min(max((int)$request->query('per_page', 20), 1), 100);
        $page = $query->latest('id')->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($item) => [
                'id' => $item->id,
                'letter_id' => $item->letter_id,
                'checked' => (bool)$item->checked,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'letter' => $item->letter ? [
                    'id' => $item->letter->id,
                    'subject' => $item->letter->subject,
                    'created_at' => $item->letter->created_at,
                    'organ' => $item->letter->organ ? ['id'=>$item->letter->organ->id,'name'=>$item->letter->organ->name] : null,
                    'daftar' => $item->letter->daftar ? ['id'=>$item->letter->daftar->id,'name'=>$item->letter->daftar->name] : null,
                    'customers' => $item->letter->customers->map(fn($x)=>['id'=>$x->id,'name'=>$x->name])->values(),
                    'organs_owner' => $item->letter->organs_owner->map(fn($x)=>['id'=>$x->id,'name'=>$x->name])->values(),
                    'projects' => $item->letter->projects->map(fn($x)=>['id'=>$x->id,'name'=>$x->name])->values(),
                ] : null,
            ])->values(),
            'meta' => [
                'current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),
                'per_page'=>$page->perPage(),'total'=>$page->total(),
            ],
        ]);
    }

    public function cartableUpdate(Request $request, int $id)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'update_cartable');

        $item = Cartable::where('user_id',$user->id)->findOrFail($id);
        $data = $request->validate(['checked'=>'required|boolean']);
        $item->update($data);

        return response()->json(['data'=>[
            'id'=>$item->id,'letter_id'=>$item->letter_id,'checked'=>(bool)$item->checked,
            'created_at'=>$item->created_at,'updated_at'=>$item->updated_at,
        ]]);
    }

    public function referralIndex(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'view_any_referral');

        $query = Referral::query()->with(['letter','users','by_users']);
        if (!$user->can('restore_any_referral')) {
            $query->where('to_user_id',$user->id);
        }

        if ($request->has('filter.checked')) {
            $query->where('checked', filter_var($request->input('filter.checked'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('filter.search')) {
            $term = trim($request->input('filter.search'));
            $query->where(function($q) use ($term) {
                $q->where('rule','like',"%{$term}%")->orWhere('result','like',"%{$term}%");
            });
        }

        $page = $query->latest('id')->paginate(min(max((int)$request->query('per_page',20),1),100));
        return response()->json([
            'data'=>collect($page->items())->map(fn($r)=>$this->referralTransform($r))->values(),
            'meta'=>['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'per_page'=>$page->perPage(),'total'=>$page->total()],
        ]);
    }

    public function referralStore(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'create_referral');
        $data = $request->validate([
            'letter_id'=>'required|integer|exists:letters,id',
            'rule'=>'nullable|string|max:255',
            'to_user_id'=>'required|integer|exists:users,id',
            'checked'=>'sometimes|boolean',
            'result'=>'nullable|string|max:500',
        ]);
        $data['by_user_id'] = $user->id;
        $r = Referral::create($data);
        return response()->json(['data'=>$this->referralTransform($r->load(['letter','users','by_users']))],201);
    }

    public function referralUpdate(Request $request, int $id)
    {
        $user = $request->user();
        $r = Referral::with(['letter','users','by_users'])->findOrFail($id);
        $this->ensurePermission($user, 'update_referral', $r);
        if (!$user->can('restore_any_referral') && $r->by_user_id !== $user->id && $r->to_user_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'checked'=>'sometimes|boolean',
            'result'=>'nullable|string|max:500',
            'rule'=>'nullable|string|max:255',
            'to_user_id'=>'sometimes|integer|exists:users,id',
        ]);
        if (!$user->can('restore_any_referral')) {
            unset($data['to_user_id']);
        }
        $r->update($data);
        return response()->json(['data'=>$this->referralTransform($r->fresh(['letter','users','by_users']))]);
    }

    public function reference(Request $request, string $resource)
    {
        [$model, $filamentResource, $permission] = $this->definition($resource);
        if ($resource === 'users' && (
            $request->user()->can('create_letter') ||
            $request->user()->can('view_any_task') ||
            $request->user()->can('view_any_minutes') ||
            $request->user()->can('view_any_referral')
        )) {
            // Reference lists are limited to id/name and are used by mobile filters.
        } else {
            $this->ensurePermission($request->user(), "view_any_{$permission}");
        }

        $q = $filamentResource::getEloquentQuery();
        $search = trim((string)$request->query('search', $request->input('filter.search', '')));
        $table = (new $model)->getTable();
        $labelField = Schema::hasColumn($table,'name') ? 'name' : (Schema::hasColumn($table,'subject') ? 'subject' : (Schema::hasColumn($table,'title') ? 'title' : 'id'));
        if ($search !== '') {
            $q->where(function($x) use ($search, $labelField) {
                $x->where($labelField,'like',"%{$search}%");
                if (is_numeric($search)) $x->orWhere('id',(int)$search);
            });
        }
        return response()->json([
            'data'=>$q->orderBy($labelField)->limit(min(max((int)$request->query('limit',30),1),100))
                ->get()->map(fn($x)=>['id'=>$x->id,'name'=>$x->name ?? $x->subject ?? $x->title ?? ('#'.$x->id)])->values(),
        ]);
    }


    /**
     * Paginated children of a project/order.
     *
     * type: letters | tasks | minutes | approves
     */
    public function projectChildren(Request $request, int $id)
    {
        $project = ProjectResource::getEloquentQuery()->findOrFail($id);
        $this->ensurePermission($request->user(), 'view_project', $project);

        $type = (string) $request->query('type', 'letters');
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        if ($type === 'letters') {
            $page = $project->letters()
                ->with(['organ', 'projects'])
                ->latest('letters.id')
                ->paginate($perPage);

            return $this->childrenResponse($page, fn ($letter) => [
                'id' => $letter->id,
                'title' => $letter->subject,
                'description' => $letter->description,
                'created_at' => $letter->created_at,
                'file' => $letter->file,
                'kind' => $letter->kind,
                'organ' => $letter->organ ? ['id' => $letter->organ->id, 'name' => $letter->organ->name] : null,
                'files' => $this->appendixFiles($letter, 'letters', $letter->id),
            ]);
        }

        if ($type === 'tasks') {
            $page = $project->tasks()
                ->with(['responsible', 'city', 'minutes', 'appendix_others'])
                ->latest('tasks.id')
                ->paginate($perPage);

            return $this->childrenResponse($page, fn ($task) => [
                'id' => $task->id,
                'title' => $task->name,
                'description' => $task->description,
                'created_at' => $task->created_at,
                'started_at' => $task->started_at,
                'ended_at' => $task->ended_at,
                'completed_at' => $task->completed_at,
                'completed' => (bool) $task->completed,
                'progress' => $task->progress,
                'status' => $task->status,
                'status_title' => Task::getStatusLabel($task->status),
                'responsible' => $task->responsible ? ['id' => $task->responsible->id, 'name' => $task->responsible->name] : null,
                'city' => $task->city ? ['id' => $task->city->id, 'name' => $task->city->name] : null,
                'minutes' => $task->minutes ? [
                    'id' => $task->minutes->id,
                    'title' => $task->minutes->title,
                    'date' => $task->minutes->date,
                ] : null,
                'files' => $this->appendixFiles($task, 'tasks', $task->id),
            ]);
        }

        if ($type === 'minutes') {
            $page = Minutes::query()
                ->whereHas('tasks', fn ($q) => $q->whereIn(
                    'tasks.id',
                    $project->tasks()->select('tasks.id')
                ))
                ->with(['typer', 'group', 'appendix_others'])
                ->select('minutes.*')
                ->distinct()
                ->latest('minutes.id')
                ->paginate($perPage);

            return $this->childrenResponse($page, fn ($minute) => [
                'id' => $minute->id,
                'title' => $minute->title,
                'text' => $minute->text,
                'date' => $minute->date,
                'typer' => $minute->typer ? ['id' => $minute->typer->id, 'name' => $minute->typer->name] : null,
                'files' => $this->appendixFiles($minute, 'minutes', $minute->id),
            ]);
        }

        if ($type === 'approves') {
            $page = Approve::query()
                ->whereHas('project', fn ($q) => $q->where('projects.id', $project->id))
                ->latest('approves.id')
                ->paginate($perPage);

            return $this->childrenResponse($page, fn ($approve) => $approve->toArray());
        }

        abort(422, 'نوع زیرمجموعه دستورکار نامعتبر است.');
    }

    private function childrenResponse($page, callable $mapper)
    {
        return response()->json([
            'data' => collect($page->items())->map($mapper)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Dashboard-style statistics for one project/order.
     * The calculations mirror the important Filament widgets.
     */
    public function projectReport(Request $request, int $id)
    {
        $project = ProjectResource::getEloquentQuery()->findOrFail($id);
        $this->ensurePermission($request->user(), 'view_project', $project);

        $tasks = $project->tasks();
        $letters = $project->letters();

        $totalTasks = (clone $tasks)->count();
        $completedTasks = (clone $tasks)->where('completed', 1)->count();
        $openTasks = max(0, $totalTasks - $completedTasks);

        $onTime = 0;
        $delayed = 0;
        foreach ((clone $tasks)->whereNotNull('started_at')->whereNotNull('ended_at')->get(['started_at', 'ended_at']) as $task) {
            $days = Carbon::parse($task->started_at)->diffInDays(Carbon::parse($task->ended_at));
            $days <= 5 ? $onTime++ : $delayed++;
        }

        $byCity = (clone $tasks)->with('city')->get()->groupBy(fn ($t) => optional($t->city)->name ?: 'بدون شهر')
            ->map(fn ($items) => [
                'total' => $items->count(),
                'completed' => $items->where('completed', 1)->count(),
            ])->sortByDesc('total')->values();

        $monthly = collect();
        for ($i = 1; $i <= 12; $i++) {
            $monthly->push([
                'month' => $i,
                'name' => ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'][$i - 1],
                'count' => 0,
                'completed' => 0,
            ]);
        }
        foreach ((clone $tasks)->get(['created_at', 'completed']) as $task) {
            if (!$task->created_at) continue;
            $m = \Morilog\Jalali\Jalalian::fromDateTime($task->created_at)->getMonth();
            $monthly[$m - 1]['count']++;
            if ((bool) $task->completed) $monthly[$m - 1]['completed']++;
        }

        return response()->json([
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'status_title' => is_numeric($project->status) ? Project::getStatusLabel((int)$project->status) : 'بدون وضعیت',
                ],
                'stats' => [
                    'tasks_total' => $totalTasks,
                    'tasks_completed' => $completedTasks,
                    'tasks_open' => $openTasks,
                    'letters_total' => (clone $letters)->count(),
                    'minutes_total' => Minutes::whereHas('tasks', fn ($q) => $q->whereIn('tasks.id', $project->tasks()->select('tasks.id')))->count(),
                    'on_time' => $onTime,
                    'delayed' => $delayed,
                ],
                'monthly_tasks' => $monthly->values(),
                'cities' => $byCity,
            ],
        ]);
    }

    /**
     * Material-friendly report API for the Flutter Reports page.
     * resource: letters | minutes | tasks | projects
     */
    public function reports(Request $request, string $resource)
    {
        abort_unless(in_array($resource, ['letters','minutes','tasks','projects'], true), 404);

        [$model, $filamentResource, $permission] = $this->definition($resource);
        $this->ensurePermission($request->user(), "view_any_{$permission}");

        $query = $filamentResource::getEloquentQuery();
        $year = $request->query('year');
        if ($year) {
            try {
                $start = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', "{$year}-01-01")->toCarbon()->startOfDay();
                $end = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', "{$year}-12-29")->toCarbon()->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            } catch (\Throwable $e) {
                abort(422, 'سال شمسی نامعتبر است.');
            }
        }

        $items = $query->get();

        $status = $items->groupBy(fn ($x) => (string) ($x->status ?? 'none'))
            ->map(fn ($v, $key) => [
                'key' => $key,
                'label' => $model === Task::class
                    ? (is_numeric($key) ? Task::getStatusLabel((int)$key) : 'بدون وضعیت')
                    : ($model === Letter::class
                        ? (is_numeric($key) ? Letter::getStatusLabel((int)$key) : 'بدون وضعیت')
                        : $key),
                'count' => $v->count(),
            ])->values();

        $monthly = collect();
        for ($i = 1; $i <= 12; $i++) {
            $monthly->push([
                'month' => $i,
                'name' => ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'][$i - 1],
                'count' => 0,
            ]);
        }
        foreach ($items as $item) {
            if (!$item->created_at) continue;
            $m = \Morilog\Jalali\Jalalian::fromDateTime($item->created_at)->getMonth();
            $monthly[$m - 1]['count']++;
        }

        $groups = collect();
        $delay = ['on_time' => 0, 'delayed' => 0];
        $cities = collect();
        $gantt = collect();

        if ($model === Task::class) {
            foreach ($items as $task) {
                if ($task->started_at && $task->ended_at) {
                    $days = Carbon::parse($task->started_at)->diffInDays(Carbon::parse($task->ended_at));
                    if ($days <= 5) $delay['on_time']++; else $delay['delayed']++;
                }
            }

            $cityIds = $items->pluck('city_id')->filter()->unique()->values();
            $cityNames = City::whereIn('id', $cityIds)->pluck('name', 'id');
            $cities = $items->groupBy('city_id')->map(function ($rows, $cityId) use ($cityNames) {
                if (!$cityId) return ['name'=>'بدون شهر','total'=>$rows->count(),'completed'=>$rows->where('completed',1)->count()];
                return ['name'=>$cityNames[$cityId] ?? 'بدون شهر','total'=>$rows->count(),'completed'=>$rows->where('completed',1)->count()];
            })->values();

            $gantt = $items->filter(fn($t) => $t->started_at && $t->ended_at)
                ->map(fn($t) => [
                    'id'=>$t->id,'name'=>$t->name,
                    'started_at'=>$t->started_at,'ended_at'=>$t->ended_at,
                    'days'=>Carbon::parse($t->started_at)->diffInDays(Carbon::parse($t->ended_at)),
                ])->values();

            $groups = TaskGroup::query()->withCount(['tasks' => function ($q) use ($year) {
                if ($year) {
                    $start = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', "{$year}-01-01")->toCarbon()->startOfDay();
                    $end = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', "{$year}-12-29")->toCarbon()->endOfDay();
                    $q->whereBetween('tasks.created_at', [$start, $end]);
                }
            }])->get(['id','name'])->filter(fn ($g) => $g->tasks_count > 0)
              ->map(fn ($g) => ['id'=>$g->id,'name'=>$g->name,'count'=>$g->tasks_count])->values();
        }

        return response()->json([
            'data' => [
                'resource' => $resource,
                'year' => $year ? (int) $year : null,
                'total' => $items->count(),
                'completed' => $model === Task::class ? $items->where('completed', 1)->count() : null,
                'status' => $status,
                'monthly' => $monthly,
                'groups' => $groups,
                'delay' => $delay,
                'cities' => $cities,
                'gantt' => $gantt,
            ],
        ]);
    }

    /**
     * Jalali activity calendar.
     */
    public function calendar(Request $request)
    {
        $this->ensurePermission($request->user(), 'view_any_task');

        $year = (int) ($request->query('year') ?: \Morilog\Jalali\Jalalian::now()->getYear());
        $month = (int) ($request->query('month') ?: \Morilog\Jalali\Jalalian::now()->getMonth());

        $start = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month))->toCarbon()->startOfDay();
        $endDay = $month <= 6 ? 31 : ($month <= 11 ? 30 : 29);
        try {
            $end = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $endDay))->toCarbon()->endOfDay();
        } catch (\Throwable $e) {
            $end = $start->copy()->addMonth()->subSecond();
        }

        $query = Task::query()->with(['responsible','city','project']);
        if (!$request->user()->can('restore_any_task')) {
            $query->where('Responsible_id', $request->user()->id);
        }
        $tasks = $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('started_at', [$start, $end])
              ->orWhereBetween('ended_at', [$start, $end])
              ->orWhereBetween('completed_at', [$start, $end]);
        })->get();

        $events = $tasks->map(function ($task) {
            $date = $task->ended_at ?: $task->started_at ?: $task->completed_at;
            $j = \Morilog\Jalali\Jalalian::fromDateTime($date);
            return [
                'id' => $task->id,
                'title' => $task->name,
                'date' => $date,
                'jalali' => ['year'=>$j->getYear(),'month'=>$j->getMonth(),'day'=>$j->getDay()],
                'completed' => (bool)$task->completed,
                'progress' => $task->progress,
                'responsible' => $task->responsible ? ['id'=>$task->responsible->id,'name'=>$task->responsible->name] : null,
                'city' => $task->city ? ['id'=>$task->city->id,'name'=>$task->city->name] : null,
            ];
        })->values();

        return response()->json([
            'data' => [
                'year' => $year,
                'month' => $month,
                'month_name' => ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'][$month - 1],
                'days' => $endDay,
                'events' => $events,
            ],
        ]);
    }

    /**
     * User notifications for the mobile announcements page.
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(min(max((int)$request->query('per_page',20),1),100));

        return response()->json([
            'data' => collect($notifications->items())->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? $n->data['message'] ?? 'اعلان',
                'message' => $n->data['message'] ?? $n->data['body'] ?? '',
                'type' => $n->data['type'] ?? null,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ])->values(),
            'meta' => [
                'current_page'=>$notifications->currentPage(),
                'last_page'=>$notifications->lastPage(),
                'per_page'=>$notifications->perPage(),
                'total'=>$notifications->total(),
            ],
        ]);
    }

    public function notificationRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['message'=>'اعلان خوانده شد.']);
    }

    private function definition(string $resource): array
    {
        if (!isset(self::RESOURCES[$resource])) abort(404,'منبع API یافت نشد.');
        return self::RESOURCES[$resource];
    }

    private function ensurePermission($user, string $permission, ?Model $model = null): void
    {
        if (!$user || !$user->can($permission)) {
            abort(403, 'شما مجوز انجام این عملیات را ندارید.');
        }
    }

    private function safeIncludes(string $model, array $includes): array
    {
        return array_values(array_filter($includes, fn ($relation) => method_exists($model, $relation)));
    }

    private function defaultIncludes(string $resource): array
    {
        return match($resource) {
            'letters' => ['user','type','organ','daftar','customers','organs_owner','users','projects'],
            'minutes' => ['typer','task_creator','organ','group'],
            'tasks' => ['creator','responsible','organ','city','minutes','project','task_group','appendix_others'],
            'projects' => ['user','organ','city','group'],
            'referrals' => ['letter','users','by_users'],
            default => [],
        };
    }

    private function applyQuery($query, string $model, Request $request)
    {
        // فیلترهای مخصوص کارپوشه موبایل برای فعالیت‌های مسئول کاربر.
        // شناسه کاربر از سرور گرفته می‌شود و Flutter لازم نیست ID کاربر را ارسال کند.
        if ($model === Task::class && $request->boolean('mine')) {
            $query->where('Responsible_id', $request->user()->id);
        }

        if ($model === Task::class && $request->filled('due')) {
            $due = (string) $request->query('due');

            if ($due === 'overdue') {
                $query->whereNotNull('ended_at')
                    ->where('ended_at', '<', Carbon::now())
                    ->where(function ($q) {
                        $q->whereNull('completed')->orWhere('completed', '!=', 1);
                    });
            } elseif ($due === 'today') {
                $query->whereNotNull('ended_at')
                    ->whereDate('ended_at', Carbon::today())
                    ->where(function ($q) {
                        $q->whereNull('completed')->orWhere('completed', '!=', 1);
                    });
            } elseif ($due === 'upcoming') {
                $query->whereNotNull('ended_at')
                    ->where('ended_at', '>', Carbon::now())
                    ->where(function ($q) {
                        $q->whereNull('completed')->orWhere('completed', '!=', 1);
                    });
            } elseif ($due === 'completed') {
                $query->where('completed', 1);
            } elseif ($due === 'without_deadline') {
                $query->whereNull('ended_at')
                    ->where(function ($q) {
                        $q->whereNull('completed')->orWhere('completed', '!=', 1);
                    });
            }
        }

        $search = trim((string)$request->query('search', $request->input('filter.search', '')));
        if ($search !== '') {
            $query->where(function($q) use ($search, $model) {
                $table = (new $model)->getTable();
                foreach (['name','title','subject','description','text','result','rule'] as $field) {
                    if (Schema::hasColumn($table,$field)) {
                        $q->orWhere($field,'like',"%{$search}%");
                    }
                }
                if (is_numeric($search) && Schema::hasColumn($table,'id')) {
                    $q->orWhere('id',(int)$search);
                }
            });
        }

        $sort = (string)$request->query('sort','-id');
        $allowed = method_exists($model,'getAllowedSorts') ? $model::getAllowedSorts() : ['id','created_at','updated_at'];
        $sort = ltrim($sort,'-');
        if (!in_array($sort,$allowed,true)) $sort='id';
        $direction = str_starts_with((string)$request->query('sort','-id'),'-') ? 'desc':'asc';
        $query->orderBy($sort,$direction);

        foreach ((array)$request->query('filter',[]) as $field=>$value) {
            if ($value === null || $value === '') continue;
            if ($field === 'search') continue;

            $valueList = is_array($value) ? array_values(array_filter(array_map('intval', $value))) : array_values(array_filter(array_map('intval', explode(',', (string)$value))));

            if ($model === Letter::class && $field === 'project_id') {
                $query->whereHas('projects', fn($q) => $q->whereIn('projects.id', $valueList));
                continue;
            }
            if ($model === Letter::class && $field === 'organ_id') {
                $query->where('organ_id', $valueList[0] ?? 0);
                continue;
            }
            if ($model === Letter::class && $field === 'type_id') {
                $query->where('type_id', $valueList[0] ?? 0);
                continue;
            }
            if ($model === Minutes::class && $field === 'task_id') {
                $query->where('task_id', $valueList[0] ?? 0);
                continue;
            }
            if ($model === Minutes::class && $field === 'project_id') {
                $query->whereHas('tasks.project', fn($q) => $q->whereIn('projects.id', $valueList));
                continue;
            }
            if ($model === Minutes::class && $field === 'organ_id') {
                $query->whereHas('organ', fn($q) => $q->whereIn('organs.id', $valueList));
                continue;
            }
            if ($model === Task::class && $field === 'project_id') {
                $query->whereHas('project', fn($q) => $q->whereIn('projects.id', $valueList));
                continue;
            }
            if ($model === Task::class && $field === 'city_id') {
                $query->where('city_id', $valueList[0] ?? 0);
                continue;
            }
            if ($model === Task::class && $field === 'Responsible_id') {
                $query->where('Responsible_id', $valueList[0] ?? 0);
                continue;
            }
            if ($model === Task::class && $field === 'organ_id') {
                $query->where('organ_id', $valueList[0] ?? 0);
                continue;
            }
            $allowedFilters = method_exists($model,'getAllowedFilters') ? $model::getAllowedFilters() : [];
            // Direct scalar filters are safe only when they are declared by the model.
            if (in_array($field,$allowedFilters,true)) {
                if (is_string($value) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)
                    && in_array($field, ['created_at','updated_at','date','started_at','ended_at','completed_at'], true)) {
                    $query->whereDate($field, $value);
                } else {
                    $query->where($field,$value);
                }
            }
        }
        return $query;
    }

    private function validatedData(Request $request, string $resource, bool $update): array
    {
        [$model] = $this->definition($resource);
        $fillable = (new $model)->getFillable();
        $data = $request->all();

        $data = collect($data)->only($fillable)->toArray();

        if ($resource === 'letters' && !$update) {
            validator($data,['subject'=>'required|string|max:1000','kind'=>'required|integer'])->validate();
            $data['user_id'] = $request->user()->id;
        }
        if ($resource === 'minutes' && !$update) {
            validator($data,['title'=>'required|string|max:255'])->validate();
        }
        if ($resource === 'tasks' && !$update) {
            validator($data,['name'=>'required|string|max:255'])->validate();
        }
        if ($resource === 'projects' && !$update) {
            validator($data,['name'=>'required|string|max:255'])->validate();
        }

        if ($resource === 'tasks' && !$request->user()->can('restore_any_task')) {
            unset($data['Responsible_id'], $data['created_by']);
        }
        if ($resource === 'minutes' && !$request->user()->can('restore_any_minutes')) {
            unset($data['typer_id']);
        }
        if ($resource === 'projects' && !$request->user()->can('restore_any_project')) {
            unset($data['user_id']);
        }

        return $data + collect($request->all())->only([
            'customer_ids','organ_owner_ids','cartable_user_ids','project_ids','group_ids','organ_ids','role_names'
        ])->toArray();
    }

    private function fillModel(Model $item, array $data, $user): void
    {
        $item->fill(collect($data)->except([
            'customer_ids','organ_owner_ids','cartable_user_ids','project_ids','group_ids','organ_ids','role_names'
        ])->toArray());

        if ($item instanceof Task && !$item->created_by) $item->created_by = $user->id;
        if ($item instanceof Project && !$item->user_id) $item->user_id = $user->id;
        if ($item instanceof Minutes && !$item->typer_id) $item->typer_id = $user->id;
        if ($item instanceof Letter && !$item->user_id) $item->user_id = $user->id;
    }

    private function syncRelations(Model $item, array $data, string $resource): void
    {
        $map = [
            'letters' => ['customers'=>'customer_ids','organs_owner'=>'organ_owner_ids','users'=>'cartable_user_ids','projects'=>'project_ids'],
            'minutes' => ['organ'=>'organ_ids','group'=>'group_ids'],
            'tasks' => ['project'=>'project_ids','task_group'=>'group_ids'],
            'projects' => ['group'=>'group_ids'],
        ];
        if ($item instanceof User && array_key_exists('role_names', $data)) {
            $item->syncRoles(array_values(array_filter((array)$data['role_names'])));
        }

        foreach (($map[$resource] ?? []) as $relation=>$key) {
            if (array_key_exists($key,$data) && method_exists($item,$relation)) {
                $ids = array_values(array_filter(array_map('intval',(array)$data[$key])));
                $item->{$relation}()->sync($ids);
            }
        }
    }

    private function transform(Model $item, string $resource): array
    {
        $data = $item->toArray();
        unset($data['password'],$data['remember_token']);

        if ($item instanceof Letter) {
            $data['kind_title'] = Letter::getKindLabel($item->kind);
            $data['files'] = $this->appendixFiles($item, 'letters', $item->id);
            $data['status_title'] = Letter::getStatusLabel($item->status);
            $data['cartables'] = $item->relationLoaded('users') ? $item->users->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'avatar_url'=>$u->avatar_url])->values() : [];
        } elseif ($item instanceof Task) {
            $data['status_title'] = Task::getStatusLabel($item->status);
            $data['files'] = $this->appendixFiles($item, 'tasks', $item->id);
            $data['status_color'] = Task::getStatusColor(Task::getStatusLabel($item->status));
        } elseif ($item instanceof Project) {
            $data['status_title'] = Project::getStatusLabel($item->status);
            $data['status_color'] = Project::getStatusColor(Project::getStatusLabel($item->status));

            if ($item->relationLoaded('letters')) {
                $data['related_letters'] = $item->letters->map(fn($letter) => [
                    'id' => $letter->id,
                    'subject' => $letter->subject,
                    'description' => $letter->description,
                    'file' => $letter->file,
                    'created_at' => $letter->created_at,
                ])->values();
            } else {
                $data['related_letters'] = [];
            }

            if ($item->relationLoaded('tasks')) {
                $data['related_tasks'] = $item->tasks->map(fn($task) => [
                    'id' => $task->id,
                    'name' => $task->name,
                    'description' => $task->description,
                    'completed' => (bool)$task->completed,
                    'progress' => $task->progress,
                    'minutes' => $task->relationLoaded('minutes') && $task->minutes ? [
                        'id' => $task->minutes->id,
                        'title' => $task->minutes->title,
                        'date' => $task->minutes->date,
                    ] : null,
                ])->values();
            } else {
                $data['related_tasks'] = [];
            }
        } elseif ($item instanceof Minutes) {
            $data['files'] = $this->appendixFiles($item, 'minutes', $item->id);
        } elseif ($item instanceof User) {
            $data['avatar_url'] = $item->getFilamentAvatarUrl();
            $data['roles'] = $item->getRoleNames()->values();
            $data['permissions'] = $item->getAllPermissions()->pluck('name')->values();
        }
        return $data;
    }

    private function storeUploadedFile(Model $item, Request $request, string $resource): void
    {
        /** @var UploadedFile|null $file */
        $file = $request->file('upload_file');
        if (!$file) return;

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');

        if ($resource === 'letters' && $item instanceof Letter) {
            $filename = $item->id . '.' . $extension;
            Storage::disk('private')->putFileAs((string)$item->id, $file, $filename);
            $item->forceFill(['file'=>$extension])->saveQuietly();
            return;
        }

        if ($resource === 'minutes' && $item instanceof Minutes) {
            $filename = $item->id . '.' . $extension;
            Storage::disk('private_appendix_other')->putFileAs(
                'minutes/' . $item->id,
                $file,
                $filename
            );
            $item->forceFill(['file'=>$extension])->saveQuietly();
        }
    }

    private function referralTransform(Referral $r): array
    {
        return [
            'id'=>$r->id,'letter_id'=>$r->letter_id,'rule'=>$r->rule,'result'=>$r->result,
            'checked'=>(bool)$r->checked,'created_at'=>$r->created_at,'updated_at'=>$r->updated_at,
            'letter'=>$r->letter ? ['id'=>$r->letter->id,'subject'=>$r->letter->subject] : null,
            'user'=>$r->users ? ['id'=>$r->users->id,'name'=>$r->users->name,'avatar_url'=>$r->users->avatar_url] : null,
            'by_user'=>$r->by_users ? ['id'=>$r->by_users->id,'name'=>$r->by_users->name,'avatar_url'=>$r->by_users->avatar_url] : null,
        ];
    }
}
