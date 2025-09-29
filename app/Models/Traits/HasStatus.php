<?php

namespace App\Models\Traits;

trait HasStatus
{
    public static function getStatusListDefine(): array
    {
        return [
            0 => 'بایگانی',
            1 => 'اتمام',
            2 => 'در حال پیگیری',
            3 => 'غیرقابل پیگیری',
        ];
    }

    public static function getStatusLabel(int|null $i): int|string
    {
        $data = self::getStatusListDefine();

        if (array_key_exists($i,$data)){
            return $data[$i];
        }elseif (is_null($i)){
            return 'بدون وضعیت';
        }

        return $i;
    }

    public static function getStatusColor($stateLabel): string
    {
        return match ($stateLabel) {
            'بایگانی' => 'gray',
            'اتمام' => 'success',
            'در حال پیگیری'=> 'danger',
            'غیرقابل پیگیری'=> 'warning',
            'بدون وضعیت'=> 'info',
        };
    }
}
