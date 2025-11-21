<?php

namespace App\Filament\Resources\LetterResource\Pages;

use App\Filament\Resources\LetterResource;
use App\Http\Controllers\ai\LetterParser;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Morilog\Jalali\Jalalian;

class CreateLetter extends CreateRecord
{
    protected static string $resource = LetterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('ocrAiParse')
                ->label('استخراج از فایل (OCR + AI)')
                ->modalHeading('بارگذاری فایل و پردازش با OCR و هوش مصنوعی')
                ->modalButton('پردازش و بارگذاری در فرم')
                ->form([
                    FileUpload::make('file')
                        ->label('فایل نامه (PDF یا تصویر)')
                        ->required(),
                ])
                ->action(function (array $data, $livewire) {
                    // مسیر فایل آپلود شده
                    $filePath = $data['file'];

                    // 1. ارسال فایل به سرویس OCR
                    $ocrResponse = Http::asForm()->post('https://www.eboo.ir/api/ocr/getway', [
                        'token' => env('EBOO_OCR_TOKEN'),
                        'command' => 'addfile',
                        'filelink' => url('storage/' . $filePath), // لینک فایل روی سرور شما
                    ]);

                    $ocrText = $ocrResponse->body();
                    dd($ocrText);
                    // 2. ارسال متن OCR به GapGPT برای اصلاح
                    $aiResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
                    ])->post('https://api.gapgpt.app/v1/chat/completions', [
                        'model' => 'gpt-4o',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => <<<EOT
متن زیر با OCR از یک نامه استخراج شده است. لطفاً اشکالاتش را برطرف کن.
هرجا تاریخ واضح نبود، تاریخ شمسی امروز در نظر بگیر.
اسامی شهرها معمولاً در شهرستان‌های شاهین‌شهر و میمه و برخوار هستند، بررسی کن درست باشند.
خروجی را فقط متن اصلاح‌شده بده، بدون هیچ متن اضافی.

{$ocrText}
EOT
                            ],
                        ],
                    ]);

                    $fixedText = $aiResponse->json('choices.0.message.content');

                    // 3. قرار دادن متن اصلاح‌شده در فیلد فرم اصلی
                    $livewire->form->fill([
                        'description' => $fixedText,
                    ]);

                    Notification::make()
                        ->title('پردازش در فرم بارگزاری شد')
                        ->success()
                        ->send();
                }),
            Action::make('parseText')
                ->label('پردازش متن')
                ->modalHeading('تکمیل فرم از طریق پردازش متن نامه')
                ->modalButton('بارگذاری در فرم')
                ->form([
                    Textarea::make('caption')
                        ->label('متن نامه')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data, $livewire) {
                    $caption = $data['caption'];
                    $ltp = new LetterParser();
                    $dataLetter = $ltp->parse($caption);

                    // پر کردن فرم زیرین
                    $livewire->form->fill([
                        'subject' => $dataLetter['title'],
                        'created_at' => $dataLetter['title_date'] ?? Carbon::now(),
                        'description' => $caption,
                        'summary' => $dataLetter['summary'],
                        'mokatebe' => $dataLetter['mokatebe'],
                        'daftar' => $dataLetter['daftar'],
                        'kind' => $dataLetter['kind'],
                        'peiroow_letter_id' => $dataLetter['pirow'],
                    ]);

                    Notification::make()
                        ->title('پردازش در فرم بارگزاری شد')
                        ->success()
                        ->send();
                }),
            Action::make('aiParse')
                ->label('استخراج با هوش مصنوعی')
                ->modalHeading('پردازش متن با هوش مصنوعی')
                ->modalButton('بارگذاری در فرم')
                ->form([
                    Textarea::make('caption')
                        ->label('متن نامه')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data, $livewire) {
                    $caption = $data['caption'];

                    // فراخوانی API هوش مصنوعی
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
                    ])->post('https://api.gapgpt.app/v1/chat/completions', [
                        'model' => 'gpt-4o',
                        'messages' => [
                            ['role' => 'user', 'content' => <<<EOT
لطفاً متن زیر را پردازش کن و اطلاعات نامه را استخراج کن.
خروجی را فقط در قالب JSON بده، بدون هیچ متن اضافی.

ساختار مورد انتظار:
/*
{
  "title": "موضوع نامه",
  "date": "YYYY/MM/DD",
  "description": "متن اصلی نامه",
  "mokatebe": "شماره مکاتبه عددی ، ممعمولا بالای متن هست و / یا - بینش دارد",
  "kind": "اگر نامه از طرف حسینعلی حاجی دلیگانی بود، 1 بزار در غیر این صورت 0",
  "pirow": "ببین اگر این نامه kind آن 0 بود ، شماره نامه ای که این نامه پیرو آن است را مقدار بده در غیر این صورت نال",
  "organ": "سمت گیرنده اصلی نامه",
  "refrals": "سمت و نام رونوشت ها در صورت وجود",
  "owners": "اسامی صاحب یا صاحب های نامه در صورت وجود",
}
*/

متن:
{$caption}
EOT],
                        ],
                    ]);

                    $content = $response->json('choices.0.message.content');
                    $content = str_replace(['```','json','\n'],'',$content);
//                    dd($content);
                    // فرض می‌کنیم خروجی هوش مصنوعی JSON باشد مثل:
                    // {"title":"...", "date":"2025/11/21", "summary":"...", "mokatebe":"123"}
                    $dataLetter = json_decode($content, true);
//                    dd($dataLetter,$content);

                    // پر کردن فرم زیرین
                    $livewire->form->fill([
                        'subject'     => $dataLetter['title'] ?? null,
                        'created_at'  => !empty($dataLetter['date'])
                            ? Jalalian::fromFormat('Y/m/d', $dataLetter['date'])->toCarbon()
                            : Carbon::now(),
                        'description' => $dataLetter['description'] ?? $caption,
                        'summary'     => implode("\n", $dataLetter['refrals'] ?? []),
                        'mokatebe'    => $dataLetter['mokatebe'] ?? null,
                        'daftar_id'   => null,
                        'kind'        => $dataLetter['kind'] ?? 1,
                    ]);

                    Notification::make()
                        ->title('پردازش در فرم بارگزاری شد')
                        ->success()
                        ->send();
                }),
        ];
    }
}
