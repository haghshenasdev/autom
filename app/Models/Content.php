<?php

namespace App\Models;

use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
    ];

    protected $casts = [
        'body' => 'array', // یا 'json'
    ];


    public function group()
    {
        return $this->belongsToMany(ContentGroup::class);
    }

    public static function formSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('عنوان')->required(),
            SelectTree::make('group_id')->label('دسته بندی')
                ->relationship('group', 'name', 'parent_id')->searchable()
                ->enableBranchNode()->createOptionForm(ContentGroup::formSchema()),

            Repeater::make('body')
                ->label('ضمیمه')
                ->schema([
                    Select::make('type')
                        ->label('نوع')
                        ->options([
                            'file' => 'فایل',
                            'text' => 'متن',
                            'signature' => 'دست‌نویس',
                        ])
                        ->required()
                        ->reactive(),

                    FileUpload::make('file')
                        ->label('آپلود فایل')
                        ->disk('private2')
                        ->directory(fn ($record) => $record?->id ? "{$record->id}" : 'temp')
                        ->imageEditor()
                        ->downloadable()
                        ->hintActions([
                            Action::make('باز کردن لینک')
                                ->label('نمایش فایل')
                                ->url(fn($record,Get $get) => $get('file') ? env('APP_URL').'/private-show2/'. $get('file')[array_key_first($get('file'))] : '', shouldOpenInNewTab: true)
                                ->color('primary')
                                ->icon('heroicon-o-arrow-top-right-on-square'),
                        ])
                        ->visible(fn ($get) => $get('type') === 'file')
                        ->required(fn ($get) => $get('type') === 'file'),

                    RichEditor::make('text')
                        ->label('محتوا')
                        ->fileAttachmentsDisk('private2')
                        ->fileAttachmentsVisibility('private')
                        ->visible(fn ($get) => $get('type') === 'text')
                        ->required(fn ($get) => $get('type') === 'text'),

                    SignaturePad::make('signature-pad')
                        ->label('دست نویس')
                        ->columnSpanFull()->downloadable()
                        ->visible(fn ($get) => $get('type') === 'signature')
                        ->required(fn ($get) => $get('type') === 'signature'),
                ])
                ->columnSpanFull()->default([
                    ['type' => 'file']
                ])
        ];
    }

    protected static function booted(): void
    {
        static::created(function ($record) {
            $attachments = $record->body;

            foreach ($attachments as &$item) {
                if (isset($item['file']) && str_starts_with($item['file'], 'temp/')) {
                    $filename = basename($item['file']);
                    $newPath = "{$record->id}/{$filename}";

                    if (Storage::disk('private2')->exists($item['file'])) {
                        Storage::disk('private2')->move($item['file'], $newPath);
                        $item['file'] = $newPath;
                    }
                }
            }

            $record->updateQuietly(['body' => $attachments]);
        });
    }
}
