<?php

namespace App\Helpers;

class FileHelper
{
    public static function mimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            default => 'application/octet-stream',
        };
    }

    public static function getMimeTypeFromExtension($filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3'  => 'audio/mpeg',
            'mp4'  => 'video/mp4',
            'zip'  => 'application/zip',
            'rar'  => 'application/vnd.rar',
            // می‌تونی پسوندهای بیشتری اضافه کنی
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
