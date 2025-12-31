<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Letter;
use App\Models\Minutes;
use App\Models\Referral;
use App\Models\Task;
use Filament\Widgets\ChartWidget;
use Morilog\Jalali\Jalalian;

class ActivityCalenderCountChart extends ChartWidget
{
    protected static ?string $heading = 'تقویم ایجاد ها';

    public string|null $selectedYear = null;
    public array|null $betYear = null; // [startCarbon, endCarbon]

    public ?int $user_id = null;

    protected function getData(): array
    {
        // کوئری‌ها
        $letters = Letter::query();
        $referrals = Referral::query();
        $minutes = Minutes::query();

        if ($this->selectedYear && $this->betYear) {
            $letters->whereBetween('created_at', $this->betYear);
            $referrals->whereBetween('created_at', $this->betYear);
            $minutes->whereBetween('date', $this->betYear);
        }

        if ($this->user_id) {
            $letters->where('user_id', $this->user_id);
            $referrals->where('to_user_id', $this->user_id);
            $minutes->where('typer_id', $this->user_id);
        }

        $letters = $letters->get(['created_at']);
        $referrals = $referrals->get(['created_at']);
        $minutes = $minutes->get(['date']);

        // آماده‌سازی کلیدهای ماه
        $makeMonths = function () {
            $months = collect([]);
            if ($this->selectedYear) {
                for ($i = 1; $i <= 12; $i++) {
                    $monthKey = $this->selectedYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $months[$monthKey] = 0;
                }
            } else {
                for ($i = 1; $i <= 12; $i++) {
                    $monthKey = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $months[$monthKey] = 0;
                }
            }
            return $months;
        };

        $lettersData = $makeMonths();
        $referralsData = $makeMonths();
        $minutesData = $makeMonths();

        $countByMonth = function ($date, &$groupedData) {
            if ($date) {
                $jalaliDate = Jalalian::fromDateTime($date);
                $monthKey = $jalaliDate->format($this->selectedYear ? 'Y-m' : 'm');
                if (isset($groupedData[$monthKey])) {
                    $groupedData[$monthKey] += 1;
                }
            }
        };

        $letters->each(fn($l) => $countByMonth($l->created_at, $lettersData));
        $referrals->each(fn($r) => $countByMonth($r->created_at, $referralsData));
        $minutes->each(fn($m) => $countByMonth($m->date, $minutesData));

        return [
            'datasets' => [
                [
                    'label' => 'نامه‌ها',
                    'data' => $lettersData->values(),
                    'backgroundColor' => '#3490dc', // آبی
                ],
                [
                    'label' => 'ارجاعات',
                    'data' => $referralsData->values(),
                    'backgroundColor' => '#38c172', // سبز
                ],
                [
                    'label' => 'صورتجلسات',
                    'data' => $minutesData->values(),
                    'backgroundColor' => '#ffed4a', // زرد
                ],
            ],
            'labels' => [
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر',
                'مرداد', 'شهریور', 'مهر', 'آبان',
                'آذر', 'دی', 'بهمن', 'اسفند'
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
