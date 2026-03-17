<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Database-agnostic SQL expression helpers.
 * Bridges MySQL-specific functions to SQLite equivalents.
 */
class DbHelper
{
    /**
     * Return a raw SQL expression for formatting a date column.
     * MySQL uses DATE_FORMAT(), SQLite uses strftime().
     * Both share the same format tokens (%Y, %m, %d).
     */
    public static function dateFormat(string $column, string $format): string
    {
        if (self::isSqlite()) {
            return "strftime('{$format}', {$column})";
        }

        return "DATE_FORMAT({$column}, '{$format}')";
    }

    /**
     * Return a raw SQL expression to extract the substring before a delimiter.
     * MySQL uses SUBSTRING_INDEX(), SQLite uses substr+instr.
     */
    public static function substringBefore(string $column, string $delimiter): string
    {
        if (self::isSqlite()) {
            return "substr({$column}, 1, instr({$column}, '{$delimiter}') - 1)";
        }

        return "SUBSTRING_INDEX({$column}, '{$delimiter}', 1)";
    }

    private static function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
}
