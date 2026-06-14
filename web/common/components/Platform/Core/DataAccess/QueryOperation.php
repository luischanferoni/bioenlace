<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Operaciones permitidas sobre grupos de atributos.
 */
final class QueryOperation
{
    public const FILTER = 'filter';
    public const READ = 'read';
    public const AGGREGATE = 'aggregate';
    public const WRITE = 'write';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::FILTER, self::READ, self::AGGREGATE, self::WRITE];
    }

    public static function isValid(string $operation): bool
    {
        return in_array($operation, self::all(), true);
    }
}
