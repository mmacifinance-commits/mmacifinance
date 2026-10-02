<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReferenceNumberService
{
    public function next(string $table, string $column, string $prefix, int $width = 4): string
    {
        return DB::transaction(function () use ($table, $column, $prefix, $width) {
            $name = $table.':'.$column.':'.$prefix;
            DB::table('reference_sequences')->insertOrIgnore(['name' => $name, 'value' => 0]);
            $sequence = DB::table('reference_sequences')->where('name', $name)->lockForUpdate()->first();
            $highest = (int) $sequence->value;
            // Existing/imported references may have gaps, arbitrary formats, or be out of order.
            foreach (DB::table($table)->where($column, 'like', $prefix.'%')->select($column)->cursor() as $row) {
                $suffix = substr($row->$column, strlen($prefix));
                if (ctype_digit($suffix)) {
                    $highest = max($highest, (int) $suffix);
                }
            }
            $next = $highest + 1;
            DB::table('reference_sequences')->where('name', $name)->update(['value' => $next]);

            return $prefix.str_pad((string) $next, $width, '0', STR_PAD_LEFT);
        }, 5);
    }
}
