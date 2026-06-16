<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\Product\ProductRegistryConfig;

/**
 * Handlers declarados en subject_resolution.handler de intents YAML.
 *
 * @phpstan-type SubjectResolverCallable callable(string $intentId, array<string, mixed>&): void
 */
final class IntentSubjectResolutionRegistry
{
    /** @var array<string, SubjectResolverCallable>|null */
    private static ?array $handlers = null;

    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     */
    public static function apply(string $handlerId, string $intentId, array &$body): void
    {
        $handlerId = trim($handlerId);
        $handlers = self::handlers();
        if ($handlerId === '' || !isset($handlers[$handlerId])) {
            throw new \InvalidArgumentException(
                'subject_resolution.handler desconocido: ' . $handlerId
                . '. Registrados: ' . implode(', ', array_keys($handlers))
            );
        }

        $handlers[$handlerId]($intentId, $body);
    }

    public static function resetForTests(): void
    {
        self::$handlers = null;
        ProductRegistryConfig::resetForTests();
    }

    /**
     * @return array<string, SubjectResolverCallable>
     */
    private static function handlers(): array
    {
        if (self::$handlers !== null) {
            return self::$handlers;
        }

        self::$handlers = [];
        foreach (ProductRegistryConfig::section('intentSubjectResolvers') as $handlerId => $callable) {
            if (!is_string($handlerId) || $handlerId === '') {
                continue;
            }
            if (!is_callable($callable)) {
                continue;
            }
            /** @var SubjectResolverCallable $callable */
            self::$handlers[$handlerId] = $callable;
        }

        return self::$handlers;
    }
}
