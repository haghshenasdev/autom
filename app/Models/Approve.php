<?php

namespace App\Models;

use App\Models\Traits\HasStatus;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approve extends Model
{
    use HasFactory,HasStatus;

    // مصوبه ها

    protected $fillable = [
        'minute_id',
        'amount',
        'city_id',
        'status',
        'description',
        'title',
    ];


    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function organ(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Organ::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minutes::class);
    }

    public static function formSchema() : array
    {
        return [
            TextInput::make('title')
                ->label('عنوان')->required()
                ->maxLength(255)->required(),
            Textarea::make('description')
                ->label('متن')->maxLength(500)
            ,
            TextInput::make('amount')->numeric()->nullable()->suffix('ریال')
                ->label('اعتبار اخذ شده')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
            ,
            Select::make('city_id')
                ->label('شهر')
                ->relationship('city', 'name')
                ->searchable()
                ->preload(),
            Select::make('organ_id')
                ->prefixActions([
                    Action::make('updateAuthor')
                        ->icon('heroicon-o-arrows-pointing-out')
                        ->label('انتخاب بر اساس نوع')
                        ->action(function (array $data,Set $set,Get $get): void {
                            $organ_owners = $get('organ_id');
                            $set('organ_id', array_merge($organ_owners,$data['organ_selected']));
                        })
                        ->form([
                            Select::make('organ_type_id')
                                ->label('نوع')
                                ->options(OrganType::query()->pluck('name', 'id'))
                                ->live()
                                ->searchable()
                                ->required(),
                            Select::make('organ_selected')
                                ->label('ارگان')
                                ->options(fn (Get $get) => $get('organ_type_id')
                                    ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                    : [])
                                ->multiple()
                                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                ->searchable()
                                ->preload()
                        ])
                ])
                ->relationship('organ','name')
                ->multiple()
                ->searchable(['name','id'])
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                ->label('دستگاه اجرایی'),

            Select::make('project_id')->label('پروژه')
                ->label('پروژه')->multiple()
                ->relationship('project', 'name')
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                ->searchable(['projects.id', 'projects.name'])
                ->preload(),
            Select::make('status')
                ->options(self::getStatusListDefine())->label('وضعیت')
                ->hiddenOn('create')
                ->default(null)
        ];
    }
}
