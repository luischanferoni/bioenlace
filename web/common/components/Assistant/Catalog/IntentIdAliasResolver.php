<?php

namespace common\components\Assistant\Catalog;

/**
 * Alias de intent_id (legacy o alucinaciones de IA) → manifest YAML vigente.
 */
final class IntentIdAliasResolver
{
    /** @var array<string, string> */
    private const ALIASES = [
        'agenda.modificar-profesional-flow' => 'data-access.editar',
        'agenda.editar-agenda-flow' => 'data-access.editar',
    ];

    public static function resolve(string $intentId): string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return '';
        }

        return self::ALIASES[$intentId] ?? $intentId;
    }
}
