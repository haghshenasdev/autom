<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Adds a schema-driven, Persian filter for each safe database field on a resource. */
final class CompleteRecordFilter
{
    private const EXCLUDED_COLUMNS = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'api_token', 'access_token', 'refresh_token', 'state',
    ];

    private const BOOLEAN_COLUMNS = [
        'active_status', 'checked', 'completed', 'repeat', 'official', 'required',
        'is_sendnotif', 'is_minute_signer_enabled',
    ];

    private const LABELS = [
        'id' => 'شناسه ثبت', 'name' => 'نام یا عنوان', 'title' => 'عنوان',
        'subject' => 'موضوع', 'description' => 'توضیحات', 'summary' => 'خلاصه',
        'text' => 'متن', 'body' => 'محتوا', 'file' => 'فایل', 'phone' => 'شماره تماس',
        'address' => 'نشانی', 'email' => 'ایمیل', 'birth_date' => 'تاریخ تولد',
        'email_verified_at' => 'تاریخ تأیید ایمیل',
        'code_melli' => 'کد ملی', 'created_at' => 'تاریخ ایجاد',
        'updated_at' => 'تاریخ آخرین ویرایش', 'deleted_at' => 'تاریخ حذف',
        'completed_at' => 'تاریخ انجام', 'started_at' => 'زمان شروع',
        'ended_at' => 'زمان پایان', 'date' => 'تاریخ', 'status' => 'وضعیت',
        'kind' => 'نوع نامه', 'type' => 'نوع', 'amount' => 'مبلغ',
        'required_amount' => 'مبلغ موردنیاز', 'progress' => 'درصد پیشرفت',
        'completed' => 'انجام شده', 'checked' => 'بررسی شده', 'repeat' => 'تکرارشونده',
        'active_status' => 'وضعیت فعال بودن', 'official' => 'سمت رسمی',
        'is_minute_signer_enabled' => 'امکان امضای صورت‌جلسه', 'is_sendnotif' => 'ارسال اعلان',
        'created_by' => 'ایجادکننده', 'user_id' => 'کاربر', 'Responsible_id' => 'مسئول',
        'typer_id' => 'نویسنده', 'task_id' => 'فعالیت', 'minutes_id' => 'صورت‌جلسه',
        'letter_id' => 'نامه', 'project_id' => 'دستورکار', 'organ_id' => 'ارگان',
        'organ_type_id' => 'نوع ارگان', 'city_id' => 'شهر', 'parent_id' => 'زیرمجموعهٔ',
        'task_group_id' => 'دسته‌بندی فعالیت', 'group_id' => 'دسته‌بندی',
        'titleholder_id' => 'صاحب‌عنوان', 'daftar_id' => 'دفتر',
        'type_id' => 'نوع نامه', 'peiroow_letter_id' => 'نامهٔ پیرو',
        'mokatebe' => 'شمارهٔ مکاتبه', 'rule' => 'دستور', 'result' => 'نتیجه',
        'password_confirmation' => 'تکرار رمز عبور', 'allowed_words' => 'کلمات مجاز',
        'blocked_words' => 'کلمات غیرمجاز', 'sensitivity' => 'میزان حساسیت',
        'model_type' => 'نوع رکورد', 'model_id' => 'شناسهٔ رکورد',
        'target_field' => 'فیلد مقصد', 'frequency' => 'تعداد تکرار',
        'percent' => 'درصد', 'order' => 'ترتیب', 'appendix_other_type' => 'نوع پیوست',
        'time' => 'مدت',
        'appendix_other_id' => 'شناسهٔ رکورد پیوست', 'bale_id' => 'شناسهٔ بله',
        'bale_username' => 'نام کاربری بله', 'post_id' => 'شناسهٔ مطلب',
        'event' => 'رویداد', 'description_from' => 'توضیحات از',
    ];

    private const WORDS = [
        'created' => 'ایجاد', 'updated' => 'ویرایش', 'deleted' => 'حذف',
        'at' => 'در', 'from' => 'از', 'until' => 'تا', 'date' => 'تاریخ',
        'id' => 'شناسه', 'user' => 'کاربر', 'organ' => 'ارگان', 'city' => 'شهر',
        'project' => 'دستورکار', 'task' => 'فعالیت', 'minutes' => 'صورت‌جلسه',
        'letter' => 'نامه', 'group' => 'دسته‌بندی', 'type' => 'نوع',
        'parent' => 'والد', 'titleholder' => 'صاحب‌عنوان', 'daftar' => 'دفتر',
        'responsible' => 'مسئول', 'typer' => 'نویسنده', 'creator' => 'ایجادکننده',
        'amount' => 'مبلغ', 'required' => 'موردنیاز', 'status' => 'وضعیت',
        'completed' => 'انجام', 'progress' => 'پیشرفت', 'phone' => 'تلفن',
        'name' => 'نام', 'title' => 'عنوان', 'file' => 'فایل', 'appendix' => 'پیوست',
        'other' => 'سایر', 'model' => 'مدل', 'target' => 'مقصد', 'field' => 'فیلد',
        'allowed' => 'مجاز', 'blocked' => 'غیرمجاز', 'words' => 'واژه‌ها',
        'sensitivity' => 'حساسیت', 'frequency' => 'تکرار', 'percent' => 'درصد',
        'order' => 'ترتیب', 'kind' => 'نوع', 'active' => 'فعال',
        'checked' => 'بررسی', 'repeat' => 'تکرار', 'official' => 'رسمی',
        'sendnotif' => 'ارسال اعلان', 'minute' => 'صورت‌جلسه', 'signer' => 'امضاکننده',
        'enabled' => 'فعال', 'code' => 'کد', 'melli' => 'ملی', 'birth' => 'تولد',
        'address' => 'نشانی', 'email' => 'ایمیل', 'password' => 'رمز عبور',
        'result' => 'نتیجه', 'rule' => 'دستور', 'state' => 'وضعیت', 'post' => 'مطلب',
        'bale' => 'بله', 'username' => 'نام کاربری', 'event' => 'رویداد',
        'text' => 'متن', 'description' => 'توضیحات', 'summary' => 'خلاصه',
        'subject' => 'موضوع', 'body' => 'محتوا', 'date' => 'تاریخ',
        'start' => 'شروع', 'end' => 'پایان', 'phone' => 'تلفن',
    ];

    public static function make(string $model): Filter
    {
        /** @var Model $record */
        $record = new $model();
        $table = $record->getTable();
        $columns = array_values(array_diff(Schema::getColumnListing($table), self::EXCLUDED_COLUMNS));
        $types = [];
        $schema = [];

        foreach ($columns as $column) {
            $type = Schema::getColumnType($table, $column);
            $types[$column] = $type;
            $label = self::label($column);

            if (in_array($type, ['date', 'datetime', 'timestamp', 'datetimetz'], true)) {
                $schema[] = DatePicker::make("{$column}_from")->label("{$label} از")->jalali();
                $schema[] = DatePicker::make("{$column}_until")->label("{$label} تا")->jalali();
            } elseif ($column === 'status' && method_exists($model, 'getStatusListDefine')) {
                $schema[] = Select::make($column)->label($label)->placeholder('همه')
                    ->options($model::getStatusListDefine());
            } elseif ($column === 'kind' && $table === 'letters') {
                $schema[] = Select::make($column)->label($label)->placeholder('همه')
                    ->options([0 => 'وارده', 1 => 'صادره']);
            } elseif (in_array($column, self::BOOLEAN_COLUMNS, true) || $type === 'boolean') {
                $schema[] = Select::make($column)
                    ->label($label)
                    ->placeholder('همه')
                    ->options([1 => 'بله', 0 => 'خیر']);
            } elseif (self::isNumeric($type)) {
                $schema[] = TextInput::make($column)->label($label)->numeric();
            } elseif (in_array($type, ['string', 'text', 'mediumtext', 'longtext', 'enum'], true)) {
                $schema[] = TextInput::make($column)->label($label)->maxLength(255);
            }
        }

        return Filter::make('database_fields')
            ->label('فیلتر فیلدهای جدول')
            ->form($schema)
            ->query(function (Builder $query, array $data) use ($columns, $types): Builder {
                foreach ($columns as $column) {
                    $type = $types[$column] ?? '';
                    if (in_array($type, ['date', 'datetime', 'timestamp', 'datetimetz'], true)) {
                        $from = $data["{$column}_from"] ?? null;
                        $until = $data["{$column}_until"] ?? null;
                        if ($from !== null && $from !== '') $query->whereDate($column, '>=', $from);
                        if ($until !== null && $until !== '') $query->whereDate($column, '<=', $until);
                        continue;
                    }

                    $value = $data[$column] ?? null;
                    if ($value === null || $value === '') continue;
                    if (in_array($column, self::BOOLEAN_COLUMNS, true) || $type === 'boolean') {
                        $query->where($column, (int) $value);
                    } elseif (self::isNumeric($type)) {
                        if (is_numeric($value)) $query->where($column, $value);
                    } else {
                        $query->where($column, 'like', '%' . trim((string) $value) . '%');
                    }
                }

                return $query;
            });
    }

    private static function isNumeric(string $type): bool
    {
        return in_array($type, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float', 'double', 'real', 'numeric'], true);
    }

    private static function label(string $column): string
    {
        if (isset(self::LABELS[$column])) return self::LABELS[$column];
        if (str_ends_with($column, '_from')) return self::label(substr($column, 0, -5)) . ' از';
        if (str_ends_with($column, '_until')) return self::label(substr($column, 0, -6)) . ' تا';
        if (str_ends_with($column, '_id')) {
            $base = substr($column, 0, -3);
            return 'شناسهٔ ' . (self::LABELS[$base] ?? self::translateWords($base));
        }

        return self::translateWords($column);
    }

    private static function translateWords(string $value): string
    {
        $parts = preg_split('/_+/', $value) ?: [$value];
        return implode(' ', array_map(fn (string $part) => self::WORDS[$part] ?? $part, $parts));
    }
}
