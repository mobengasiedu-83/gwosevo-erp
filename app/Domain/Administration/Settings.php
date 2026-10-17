<?php

namespace App\Domain\Administration;

use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class Settings
{
    private ?array $values = null;

    public function all(): array
    {
        if ($this->values === null) {
            $this->values = Schema::hasTable('system_settings') ? SystemSetting::all()->pluck('value', 'key')->all() : [];
        }

        return array_replace($this->defaults(), $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function refresh(): void
    {
        $this->values = null;
    }

    public function formatDate(mixed $date, bool $time = false): string
    {
        return $date ? Carbon::parse($date)->timezone($this->get('timezone'))->format($this->get('date_format').($time ? ' H:i' : '')) : '—';
    }

    public function defaults(): array
    {
        return [
            'company_name' => 'GWO SEVO ERP', 'company_email' => '', 'company_phone' => '',
            'company_address' => '', 'timezone' => 'Africa/Accra', 'date_format' => 'd/m/Y',
            'currency' => 'GHS', 'financial_year_month' => 1, 'pagination' => 15,
            'login_message' => 'Sign in to the staff workspace.', 'brand_color' => '#449E37',
            'document_footer' => '', 'logo_path' => null, 'maintenance_enabled' => false,
            'maintenance_message' => 'The workspace is undergoing maintenance. Please try again shortly.',
            'operational_notice' => '',
        ];
    }
}
