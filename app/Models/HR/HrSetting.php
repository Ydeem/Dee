<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrSetting extends Model
{
    protected $table = 'hr_settings';

    protected $fillable = ['key', 'value', 'group'];

    protected $attributes = [
        'group' => 'general',
    ];

    public static function getAllSettings(): array
    {
        return static::query()
            ->pluck('value', 'key')
            ->toArray();
    }

    public static function getGroup(string $group): array
    {
        return static::query()
            ->where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }

    public static function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            static::updateOrCreate(
                ['key' => $key],
                ['value' => is_null($value) ? null : (string) $value, 'group' => $group]
            );
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }
}
