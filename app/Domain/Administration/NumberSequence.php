<?php

namespace App\Domain\Administration;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NumberSequence
{
    public function next(string $key): string
    {
        return DB::transaction(function () use ($key) {
            $sequence = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
            if (! $sequence) {
                throw ValidationException::withMessages(['sequence' => 'Number sequence is not configured.']);
            }
            $number = $sequence->next_number;
            // Existing references remain valid when a sequence or prefix is introduced.
            do {
                $reference = $sequence->prefix.str_pad((string) $number++, $sequence->padding, '0', STR_PAD_LEFT);
                $table = match ($key) {
                    'lead' => 'leads', 'customer' => 'accounts', default => null
                };
            } while ($table && DB::table($table)->where('reference', $reference)->exists());
            DB::table('number_sequences')->where('key', $key)->update(['next_number' => $number, 'updated_at' => now()]);

            return $reference;
        }, 5);
    }
}
