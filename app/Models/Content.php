<?php

namespace App\Models;

use App\Forms\Components\DrawingPad;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'user_id',
    ];

    protected $casts = [
        'body' => 'array', // یا 'json'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
                    DrawingPad::make('drawing')
                        ->label('برگه A4 برای نوشتن')
                        ->disk('private2')
                        ->directory(fn ($record) => $record?->id ? "{$record->id}" : 'temp')
                        ->visible(fn ($get) => $get('type') === 'signature')
//                        ->required(fn ($get) => $get('type') === 'signature'),
//                    SignaturePad::make('signature-pad')
//                        ->label('دست نویس')
//                        ->columnSpanFull()->downloadable()
//                        ->visible(fn ($get) => $get('type') === 'signature')
//                        ->required(fn ($get) => $get('type') === 'signature'),
                ])
                ->columnSpanFull()->default([
                    ['type' => 'file']
                ])
        ];
    }

    protected static function booted(): void
    {
        static::saved(function ($record) {
            $attachments = $record->body ?? [];
            $oldAttachments = $record->getOriginal('body') ?? [];

            foreach ($attachments as $index => &$item) {
                // انتقال file از temp به پوشه رکورد
                if (isset($item['file']) && is_string($item['file']) && str_starts_with($item['file'], 'temp/')) {
                    $filename = basename($item['file']);
                    $newPath = "{$record->id}/{$filename}";

                    if (Storage::disk('private2')->exists($item['file'])) {
                        Storage::disk('private2')->move($item['file'], $newPath);
                        $item['file'] = $newPath;
                    }
                }

                // انتقال drawing از temp به پوشه رکورد
                if (isset($item['drawing']) && is_string($item['drawing']) && str_starts_with($item['drawing'], 'temp/')) {
                    $filename = basename($item['drawing']);
                    $newPath = "{$record->id}/{$filename}";

                    if (Storage::disk('private2')->exists($item['drawing'])) {
                        Storage::disk('private2')->move($item['drawing'], $newPath);
                        $item['drawing'] = $newPath;
                    }
                }

                // اگر drawing جدید به صورت base64 است، فقط فایل قبلی همان آیتم را پاک کن
                if (isset($item['drawing']) && is_string($item['drawing']) && str_starts_with($item['drawing'], 'data:image')) {
                    $oldItem = $oldAttachments[$index] ?? null;

                    // اگر قبلاً مسیر فایل داشت (و base64 نبود)، آن فایل را حذف کن
                    if ($oldItem && isset($oldItem['drawing']) && is_string($oldItem['drawing']) && !str_starts_with($oldItem['drawing'], 'data:image')) {
                        if (Storage::disk('private2')->exists($oldItem['drawing'])) {
                            Storage::disk('private2')->delete($oldItem['drawing']);
                        }
                    }

                    // ذخیره‌ی فایل جدید از base64
                    $component = \App\Forms\Components\DrawingPad::make('drawing')
                        ->disk('private2')
                        ->directory(fn ($r) => $r?->id ? "{$r->id}" : 'temp');

                    $item['drawing'] = $component->processState($record, $item['drawing']);
                }
            }

            $record->updateQuietly(['body' => $attachments]);
        });

        static::updated(function ($record) {
            $attachments = $record->body ?? [];
            $oldAttachments = $record->getOriginal('body') ?? [];

            // لیست فایل‌های فعلی (برای باقی‌ماندن)
            $currentFiles = collect($attachments)->pluck('file')->filter(fn($p) => is_string($p))->all();
            $currentDrawings = collect($attachments)->pluck('drawing')->filter(fn($p) => is_string($p))->all();

            foreach ($oldAttachments as $oldItem) {
                // پاک کردن فایل‌های file که دیگر وجود ندارند
                if (isset($oldItem['file']) && is_string($oldItem['file']) && !in_array($oldItem['file'], $currentFiles, true)) {
                    if (Storage::disk('private2')->exists($oldItem['file'])) {
                        Storage::disk('private2')->delete($oldItem['file']);
                    }
                }

                // پاک کردن فایل‌های drawing که دیگر وجود ندارند (فقط اگر مسیر فایل است، نه base64)
                if (isset($oldItem['drawing']) && is_string($oldItem['drawing']) && !str_starts_with($oldItem['drawing'], 'data:image')) {
                    if (!in_array($oldItem['drawing'], $currentDrawings, true) && Storage::disk('private2')->exists($oldItem['drawing'])) {
                        Storage::disk('private2')->delete($oldItem['drawing']);
                    }
                }
            }

            // در این مرحله فقط پاک‌سازی انجام شده؛ attachments همین مقدار فعلی است.
            // اگر در همین هندلر تغییری روی attachments نداری، نیازی به updateQuietly نیست.
            // اما برای همسانی با منطق قبلی اگر تغییر کرده باشد، می‌توانی بگذاری:
            $record->updateQuietly(['body' => $attachments]);
        });

        static::deleting(function ($record) {
            Storage::disk('private2')->deleteDirectory($record->id);
        });
    }
}
