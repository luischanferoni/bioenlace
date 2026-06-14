<?php

namespace common\components\Assistant\SubIntentEngine;

use common\components\Core\Product\ProductRegistryConfig;

/**
 * Registro de handlers de enriquecimiento de draft (lógica de dominio en capas inferiores).
 *
 * Los intents YAML declaran `draft_hydrator.handler`; el orquestador del chat no lista intents.
 * Implementaciones en {@see common/config/product-registries.php} (`flowDraftHydrators`).
 *
 * @phpstan-type HydratorCallable callable(array<string, mixed>&, array<string, mixed>): void
 */
final class FlowDraftHydratorRegistry
{
    /** @var array<string, HydratorCallable>|null */
    private static ?array $handlers = null;

    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options opciones del manifiesto YAML (`draft_hydrator`)
     */
    public static function apply(string $handlerId, array &$body, array $options = []): void
    {
        $handlerId = trim($handlerId);
        $handlers = self::handlers();
        if ($handlerId === '' || !isset($handlers[$handlerId])) {
            throw new \InvalidArgumentException(
                'draft_hydrator.handler desconocido: ' . $handlerId
                . '. Registrados: ' . implode(', ', array_keys($handlers))
            );
        }

        $handlers[$handlerId]($body, $options);
    }

    /**
     * @return list<string>
     */
    public static function registeredHandlerIds(): array
    {
        return array_keys(self::handlers());
    }

    public static function resetForTests(): void
    {
        self::$handlers = null;
        ProductRegistryConfig::resetForTests();
    }

    /**
     * @return array<string, HydratorCallable>
     */
    private static function handlers(): array
    {
        if (self::$handlers !== null) {
            return self::$handlers;
        }

        self::$handlers = [];
        foreach (ProductRegistryConfig::section('flowDraftHydrators') as $handlerId => $callable) {
            if (!is_string($handlerId) || $handlerId === '') {
                continue;
            }
            if (!is_array($callable) || count($callable) !== 2) {
                continue;
            }
            [$class, $method] = $callable;
            if (!is_string($class) || !is_string($method) || !method_exists($class, $method)) {
                continue;
            }
            self::$handlers[$handlerId] = [$class, $method];
        }

        return self::$handlers;
    }
}
