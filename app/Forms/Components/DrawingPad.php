<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;

class DrawingPad extends Field
{
    protected string $view = 'components.forms.drawing-pad';

    protected string $disk = 'public';
    protected $directory = null;

    public function disk(string $disk): static
    {
        $this->disk = $disk;
        return $this;
    }

    public function directory($directory): static
    {
        $this->directory = $directory;
        return $this;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getDirectory($record = null): ?string
    {
        if (is_callable($this->directory)) {
            return call_user_func($this->directory, $record);
        }
        return $this->directory;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // هنگام hydrate، اگر مسیر فایل موجود است برای نمایش اولیه لازم داریم
        $this->afterStateHydrated(function ($component, $state) {
            // اینجا فقط state را دست‌نخورده نگه می‌داریم؛ نمایش در Blade انجام می‌شود
        });

        // هنگام submit: اگر state به‌شکل base64 بود، ذخیره کن و مسیر را برگردان
        $this->dehydrateStateUsing(function ($state, $record) {
            if (is_string($state) && str_starts_with($state, 'data:image')) {
                $image = preg_replace('#^data:image/\w+;base64,#', '', $state);
                $dir = $this->getDirectory($record) ?? 'temp';
                $filename = uniqid('drawing_', true) . '.png';
                $path = trim($dir, '/').'/'.$filename;

                Storage::disk($this->getDisk())->put($path, base64_decode($image));

                return $path; // مقدار نهایی که در آرایه‌ی body ذخیره می‌شود
            }

            // اگر از قبل مسیر فایل بوده، همان را نگه دار
            return $state;
        });

        // اجازه بده مقدار فیلد در فرم ذخیره شود (پیش‌فرض همین است)
        $this->dehydrated(true);
    }

    public function processState($record, $state): ?string
    {
        // اگر مقدار به صورت base64 تصویر باشد
        if ($state && str_starts_with($state, 'data:image')) {
            $image = str_replace('data:image/png;base64,', '', $state);
            $image = str_replace(' ', '+', $image);

            $dir = $this->getDirectory($record) ?? 'temp';
            $filename = uniqid('drawing_') . '.png';
            $path = $dir . '/' . $filename;

            // ذخیره فایل در دیسک مشخص شده
            Storage::disk($this->getDisk())->put($path, base64_decode($image));

            return $path; // مسیر فایل ذخیره شده
        }

        // اگر قبلاً مسیر فایل بود، همان را برگردان
        return $state;
    }
}
