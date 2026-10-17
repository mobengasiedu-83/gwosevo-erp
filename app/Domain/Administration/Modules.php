<?php

namespace App\Domain\Administration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Modules
{
    public function enabled(string $key): bool
    {
        $module = config('erp.modules.'.$key);
        if (! $module || ! $module['available']) {
            return false;
        }
        if ($module['required'] ?? false) {
            return true;
        }

        return ! Schema::hasTable('module_settings') || (bool) (DB::table('module_settings')->where('key', $key)->value('enabled') ?? true);
    }
}
