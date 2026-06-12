<?php

namespace common\components\Assistant\Catalog;

/**
 * Alias de intent_id (legacy o alucinaciones de IA) → manifest vigente.
 *
 * Fuente: {@see schemas/intent-aliases.yaml}.
 */
final class IntentIdAliasResolver
{
    public static function resolve(string $intentId): string
    {
        return IntentAliasCatalog::resolve($intentId);
    }
}
