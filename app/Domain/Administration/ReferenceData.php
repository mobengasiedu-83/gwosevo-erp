<?php

namespace App\Domain\Administration;

use App\Models\ReferenceValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferenceData
{
    public function validate(array $data): array
    {
        return Validator::make($data, [
            'category' => ['required', Rule::in(ReferenceValue::CATEGORIES)],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/'],
            'label' => ['required', 'string', 'max:150'],
            'parent_code' => ['nullable', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
        ])->validate();
    }

    public function validateParent(array $row, array $batchCountries = []): void
    {
        if ($row['category'] === 'region') {
            $code = $row['parent_code'] ?? null;
            if (! $code || (! in_array($code, $batchCountries, true) && ! ReferenceValue::where('category', 'country')->where('code', $code)->exists())) {
                throw ValidationException::withMessages(['parent_code' => 'Regions must reference an existing country code.']);
            }
        } elseif (! empty($row['parent_code'])) {
            throw ValidationException::withMessages(['parent_code' => 'Only regions have a parent country code.']);
        }
    }

    public function map(array $upload, array $mapping): array
    {
        $rows = [];
        $seen = [];
        foreach ($upload['rows'] as $i => $values) {
            $data = [];
            foreach (['category', 'code', 'label', 'parent_code', 'is_active'] as $field) {
                $index = $mapping[$field] ?? '';
                $data[$field] = $index === '' || $index === null ? ($field === 'is_active' ? '1' : null) : trim((string) ($values[(int) $index] ?? ''));
            }
            $data['parent_code'] = $data['parent_code'] ?: null;
            try {
                $data = $this->validate($data);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['mapping' => 'Row '.($i + 2).': '.implode(' ', $e->validator->errors()->all())]);
            }
            $key = $data['category'].':'.$data['code'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['mapping' => 'Duplicate category/code at row '.($i + 2).'.']);
            }
            $seen[$key] = true;
            $rows[] = $data;
        }
        $countries = array_column(array_filter($rows, fn ($row) => $row['category'] === 'country'), 'code');
        foreach ($rows as $row) {
            $this->validateParent($row, $countries);
        }

        return $rows;
    }
}
