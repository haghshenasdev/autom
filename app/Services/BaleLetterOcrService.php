<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BaleLetterOcrService
{
    /**
     * Extract text from a Bale attachment using the same EBOO gateway as MobileAiController.
     * The caller is responsible for catching failures so OCR remains optional.
     */
    public function extract(string $contents, string $filename): string
    {
        if ($contents === '') {
            throw new \RuntimeException('محتوای فایل خالی است.');
        }

        $token = trim((string) config('services.eboo.ocr_token', env('EBOO_OCR_TOKEN')));
        if ($token === '') {
            throw new \RuntimeException('کلید EBOO_OCR_TOKEN در تنظیمات سرور تعریف نشده است.');
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'pdf';
        $tempName = app(TempFileService::class)->save($contents, $extension);
        $fileUrl = url('/temp-download/' . $tempName);

        $uploaded = Http::timeout(90)->asForm()->post('https://www.eboo.ir/api/ocr/getway', [
            'token' => $token,
            'command' => 'addfile',
            'filelink' => $fileUrl,
        ]);

        if (!$uploaded->successful()) {
            throw new \RuntimeException('سرویس OCR فایل نامه را نپذیرفت (HTTP ' . $uploaded->status() . ').');
        }

        $fileToken = $uploaded->json('FileToken') ?? $uploaded->json('filetoken');
        if (!$fileToken) {
            throw new \RuntimeException('سرویس OCR شناسه فایل را برنگرداند: ' . mb_substr($uploaded->body(), 0, 300));
        }

        $converted = Http::timeout(120)->asForm()->post('https://www.eboo.ir/api/ocr/getway', [
            'token' => $token,
            'command' => 'convert',
            'output' => 'txtraw',
            'filetoken' => $fileToken,
            'method' => 4,
        ]);

        if (!$converted->successful()) {
            throw new \RuntimeException('تبدیل فایل نامه به متن با خطا مواجه شد (HTTP ' . $converted->status() . ').');
        }

        $text = trim((string) $converted->body());
        if ($text === '') {
            throw new \RuntimeException('سرویس OCR متنی از فایل نامه استخراج نکرد.');
        }

        return $text;
    }
}
