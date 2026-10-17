<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\Modules;
use App\Domain\Administration\ReferenceData;
use App\Domain\Administration\Settings;
use App\Http\Controllers\Controller;
use App\Jobs\HealthHeartbeat;
use App\Models\AuditEvent;
use App\Models\CustomField;
use App\Models\ReferenceValue;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdministrationController extends Controller
{
    public function index(Request $request)
    {
        $section = $request->query('section', 'general');
        abort_unless(in_array($section, ['general', 'branding', 'preferences', 'modules', 'fields', 'references', 'sequences', 'health', 'maintenance', 'audit'], true), 404);

        return view('admin.index', [
            'section' => $section,
            'fields' => $section === 'fields' ? CustomField::orderBy('id')->get() : [],
            'references' => $section === 'references' ? ReferenceValue::orderBy('category')->orderBy('code')->paginate(app(Settings::class)->get('pagination'))->withQueryString() : [],
            'sequences' => $section === 'sequences' ? DB::table('number_sequences')->orderBy('key')->get() : [],
            'events' => $section === 'audit' ? AuditEvent::where('event', 'like', 'admin.%')->latest('id')->paginate(25)->withQueryString() : [],
            'modules' => app(Modules::class),
        ]);
    }

    public function settings(Request $request, Settings $settings)
    {
        $section = $request->validate(['section' => ['required', Rule::in(['general', 'branding', 'preferences', 'maintenance'])]])['section'];
        $rules = match ($section) {
            'general' => [
                'company_name' => ['required', 'string', 'max:150'], 'company_email' => ['nullable', 'email', 'max:150'],
                'company_phone' => ['nullable', 'string', 'max:50'], 'company_address' => ['nullable', 'string', 'max:1000'],
                'timezone' => ['required', 'timezone'], 'date_format' => ['required', Rule::in(['d/m/Y', 'm/d/Y', 'Y-m-d', 'd M Y'])],
            ],
            'branding' => [
                'login_message' => ['required', 'string', 'max:300'], 'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'document_footer' => ['nullable', 'string', 'max:500'],
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
                'remove_logo' => ['nullable', 'boolean'],
            ],
            'preferences' => [
                'currency' => ['required', 'regex:/^[A-Z]{3}$/'], 'financial_year_month' => ['required', 'integer', 'between:1,12'],
                'pagination' => ['required', 'integer', Rule::in([10, 15, 25, 50, 100])],
            ],
            'maintenance' => [
                'maintenance_enabled' => ['required', 'boolean'], 'maintenance_message' => ['required', 'string', 'max:500'],
                'operational_notice' => ['nullable', 'string', 'max:500'],
            ],
        };
        $data = $request->validate($rules);
        $previousLogo = $settings->get('logo_path');
        $newLogo = null;
        if ($request->hasFile('logo')) {
            $newLogo = $request->file('logo')->store('branding', 'local');
            throw_unless($newLogo, \RuntimeException::class, 'Logo could not be stored.');
            $data['logo_path'] = $newLogo;
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }
        unset($data['logo'], $data['remove_logo']);
        foreach (['pagination', 'financial_year_month'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }
        if (isset($data['maintenance_enabled'])) {
            $data['maintenance_enabled'] = (bool) $data['maintenance_enabled'];
        }
        try {
            DB::transaction(function () use ($data, $settings) {
                $old = array_intersect_key($settings->all(), $data);
                foreach ($data as $key => $value) {
                    SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
                }
                AdminAudit::record('admin.settings.updated', SystemSetting::class, 0, $old, $data);
            });
        } catch (\Throwable $e) {
            if ($newLogo) {
                Storage::disk('local')->delete($newLogo);
            }
            throw $e;
        }
        if (array_key_exists('logo_path', $data) && $previousLogo && $previousLogo !== $data['logo_path']) {
            Storage::disk('local')->delete($previousLogo);
        }
        $settings->refresh();

        return to_route('admin.index', ['section' => $section])->with('success', 'Settings saved.');
    }

    public function module(Request $request, string $key)
    {
        $module = config('erp.modules.'.$key);
        abort_unless($module && $module['available'] && ! ($module['required'] ?? false), 422, 'This module cannot be toggled.');
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($key, $data) {
            $old = app(Modules::class)->enabled($key);
            DB::table('module_settings')->updateOrInsert(['key' => $key], ['enabled' => (bool) $data['enabled'], 'updated_at' => now(), 'created_at' => now()]);
            AdminAudit::record('admin.module.updated', 'module_settings', 0, ['key' => $key, 'enabled' => $old], ['key' => $key, 'enabled' => (bool) $data['enabled']]);
        });

        return back()->with('success', 'Module availability updated.');
    }

    public function field(Request $request, ?CustomField $field = null)
    {
        $data = $request->validate([
            'key' => [$field ? 'sometimes' : 'required', 'regex:/^[a-z][a-z0-9_]{0,49}$/', Rule::unique('custom_fields')->where('record_type', 'lead')->ignore($field?->id)],
            'label' => ['required', 'string', 'max:100'],
            'type' => [$field ? 'sometimes' : 'required', Rule::in(['text', 'number', 'date', 'select'])],
            'options_text' => ['nullable', 'string', 'max:2000'], 'is_required' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
        $options = array_values(array_unique(array_filter(array_map('trim', explode("\n", $data['options_text'] ?? '')), fn ($value) => $value !== '')));
        $type = $field?->type ?? $data['type'];
        if ($type === 'select' && (! $options || count($options) > 50)) {
            throw ValidationException::withMessages(['options_text' => 'Select fields require 1–50 options, one per line.']);
        }
        unset($data['options_text']);
        if ($field) {
            unset($data['key'], $data['type']);
        }
        DB::transaction(function () use ($field, $data, $options, $type) {
            $old = $field?->toArray();
            $field ??= new CustomField;
            $field->fill([...$data, 'record_type' => 'lead', 'options' => $type === 'select' ? $options : null])->save();
            AdminAudit::record('admin.custom_field.saved', CustomField::class, $field->id, $old, $field->toArray());
        });

        return back()->with('success', 'Custom field saved. Existing record values are retained when fields are disabled.');
    }

    public function reference(Request $request, ReferenceData $service, ?ReferenceValue $reference = null)
    {
        $data = $service->validate($request->all());
        if ($reference) {
            $data['category'] = $reference->category;
            $data['code'] = $reference->code;
        }
        $service->validateParent($data);
        $request->validate(['code' => [Rule::unique('reference_values')->where('category', $data['category'])->ignore($reference?->id)]]);
        DB::transaction(function () use ($reference, $data) {
            $old = $reference?->toArray();
            $reference ??= new ReferenceValue;
            $reference->fill($data)->save();
            AdminAudit::record('admin.reference.saved', ReferenceValue::class, $reference->id, $old, $reference->toArray());
        });

        return back()->with('success', 'Reference value saved.');
    }

    public function sequence(Request $request, string $key)
    {
        $data = $request->validate(['prefix' => ['required', 'regex:/^[A-Z0-9_-]{1,20}$/'], 'padding' => ['required', 'integer', 'between:3,12'], 'next_number' => ['required', 'integer', 'between:1,999999999999']]);
        DB::transaction(function () use ($key, $data) {
            $sequence = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
            abort_unless($sequence, 404);
            if ($data['next_number'] < $sequence->next_number) {
                throw ValidationException::withMessages(['next_number' => 'Next number cannot move backwards.']);
            }
            DB::table('number_sequences')->where('key', $key)->update([...$data, 'updated_at' => now()]);
            AdminAudit::record('admin.sequence.updated', 'number_sequences', 0, (array) $sequence, ['key' => $key, ...$data]);
        });

        return back()->with('success', 'Number sequence saved. Issued references have not changed.');
    }

    public function heartbeat()
    {
        HealthHeartbeat::dispatch();
        AdminAudit::record('admin.health.probe', 'system_settings', 0, null, ['queue' => config('queue.default')]);

        return back()->with('success', 'Queue probe dispatched. Refresh the health screen after the worker processes it.');
    }

    public function logo(Settings $settings)
    {
        $path = $settings->get('logo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }
}
