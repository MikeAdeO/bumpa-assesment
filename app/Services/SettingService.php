<?php

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    /**
     * Retrieve an integer setting by key, or return the supplied default when it does not exist.
     */
    public function getInt(string $key, int $default = 0): int
    {
        $setting = Setting::where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return (int) $setting->value;
    }
}