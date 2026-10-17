<?php

namespace App\Domain\Administration;

use App\Models\CustomField;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomFields
{
    public function validate(array $values): array
    {
        $rules = [];
        foreach (CustomField::where('record_type', 'lead')->where('is_active', true)->get() as $field) {
            $rules[$field->key] = [
                $field->is_required ? 'required' : 'nullable',
                ...match ($field->type) {
                    'number' => ['numeric'], 'date' => ['date_format:Y-m-d'],
                    'select' => [Rule::in($field->options ?? [])],
                    default => ['string', 'max:1000'],
                },
            ];
        }

        return Validator::make($values, $rules)->validate();
    }
}
