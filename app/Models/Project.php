<?php

namespace App\Models;

use App\Models\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Rupadana\ApiService\Contracts\HasAllowedFilters;
use Rupadana\ApiService\Contracts\HasAllowedSorts;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Support\RawJs;
use CodeWithDennis\FilamentSelectTree\SelectTree;

class Project extends Model implements HasAllowedSorts,HasAllowedFilters
{
    use HasFactory,HasStatus,LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'organ_id',
        'city_id',
        'group_id',
        'required_amount',
        'status',
        'amount', // کل اعتبار اخذ شده
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organ(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organ::class);
    }

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function approvs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Approve::class);
    }

    public function letters()
    {
        return $this->belongsToMany(Letter::class, 'letter_project')->withPivot('summary');
    }

    public function minutes()
    {
        return $this->belongsToMany(Minutes::class, 'minute_project', 'project_id', 'minute_id');
    }

    public function group()
    {
        return $this->belongsToMany(ProjectGroup::class);
    }

    public function ai_words_data()
    {
        return $this->morphMany(AiWordsData::class, 'ai_words_data');
    }

    protected static function boot()
    {
        parent::boot();

//        static::creating(function ($model) {
//            $model->user_id = Auth::id();
//        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['updated_at','created_at'])->logAll()->logOnlyDirty() // فقط وقتی مقدار تغییر کرد ذخیره بشه
        ->dontSubmitEmptyLogs(); // لاگ خالی ثبت نشه
    }


    /**
     * Shared form schema used by ProjectResource and relationship quick-create
     * forms. Keep this definition in one place so Minutes can create a Project
     * without calling a method on a Filament Resource.
     */
    public static function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label("عنوان")
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')->label('توضیحات'),
            SelectTree::make('group_id')->label('دسته بندی')
                ->relationship('group', 'name', 'parent_id')
                ->enableBranchNode()
                ->createOptionForm(auth()->user()?->can('create_project::group') ? ProjectGroup::formSchema() : null),
            Forms\Components\TextInput::make('required_amount')->numeric()->nullable()
                ->label('چشم انداز کار یا جلسه مورد نیاز')->minValue(0),
            Select::make('status')
                ->options(self::getStatusListDefine())->label('وضعیت')
                ->default(null),
            Forms\Components\Select::make('user_id')->label('مسئول')
                ->relationship('user', 'name')
                ->allowHtml()
                ->getOptionLabelFromRecordUsing(function ($record): string {
                    return view('filament.components.select-user-result')
                        ->with('name', $record->name)
                        ->with('user', $record)
                        ->with('image', $record->getFilamentAvatarUrl())
                        ->render();
                })
                ->searchable()->preload()->default(auth()->id())
                ->visible(auth()->user()?->can('restore_any_project') ?? false),
            Forms\Components\Select::make('city_id')->label('شهر/روستا')
                ->relationship('city', 'name')
                ->searchable()->preload(),
            Forms\Components\Select::make('organ')
                ->prefixActions([
                    Forms\Components\Actions\Action::make('updateAuthor')
                        ->icon('heroicon-o-arrows-pointing-out')
                        ->label('انتخاب بر اساس نوع')
                        ->action(function (array $data, Forms\Set $set): void {
                            $set('organ', $data['organ_selected']);
                        })
                        ->form([
                            Select::make('organ_type_id')
                                ->label('نوع')
                                ->options(OrganType::query()->pluck('name', 'id'))
                                ->live()
                                ->searchable()
                                ->required(),
                            Forms\Components\Select::make('organ_selected')
                                ->label('ارگان')
                                ->options(fn (Get $get) => $get('organ_type_id')
                                    ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                    : [])
                                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                ->searchable()
                                ->preload()
                        ])
                ])
                ->label('دستگاه مربوطه')
                ->relationship('organ', 'name')
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                ->searchable(['id', 'name'])
                ->preload(),
            TextInput::make('amount')
                ->label('اعتبار اخذ شده')->numeric()->nullable()->suffix('ریال')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(','),
        ];
    }

    public static function getAllowedSorts(): array
    {
        return [
            'id',
            'name',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    public static function getAllowedFilters(): array
    {
        return [
            'id',
            'name',
            'status',
            'created_at',
            'updated_at',
            AllowedFilter::callback(
                'search',
                function (Builder $query, $value) {

                    $query->where(function ($q) use ($value) {

                        if (is_numeric($value)) {
                            $q->orWhere('id', $value);
                        }

                        $q->orWhere('name', 'LIKE', "%{$value}%")
                            ->orWhere('description', 'LIKE', "%{$value}%");

                    });

                }
            ),
        ];
    }

}
