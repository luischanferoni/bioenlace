<?php

namespace common\components\Core\DataAccess;

/**
 * Modo de salida de una métrica compilada.
 */
final class QueryOutputMode
{
    public const AGGREGATE = 'aggregate';
    public const ROWS = 'rows';
    public const GROUPED = 'grouped';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::AGGREGATE, self::ROWS, self::GROUPED];
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::all(), true);
    }

    public static function normalize(string $mode): string
    {
        $mode = mb_strtolower(trim($mode), 'UTF-8');

        return self::isValid($mode) ? $mode : self::AGGREGATE;
    }
}
