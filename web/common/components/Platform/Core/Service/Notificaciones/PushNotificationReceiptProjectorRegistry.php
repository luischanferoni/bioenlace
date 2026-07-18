<?php

namespace common\components\Platform\Core\Service\Notificaciones;

use common\components\Platform\Core\Product\ProductRegistryConfig;

/**
 * Registry estable handler_id → projector de recibos push.
 *
 * Cableado en {@see product-registries.php} (`pushNotificationReceiptProjectors`).
 */
final class PushNotificationReceiptProjectorRegistry
{
    /** @var array<string, PushNotificationReceiptProjectorInterface>|null */
    private static ?array $instances = null;

    public static function get(string $handlerId): ?PushNotificationReceiptProjectorInterface
    {
        $handlerId = trim($handlerId);
        if ($handlerId === '') {
            return null;
        }
        $all = self::instances();

        return $all[$handlerId] ?? null;
    }

    public static function resetForTests(): void
    {
        self::$instances = null;
        ProductRegistryConfig::resetForTests();
    }

    /**
     * @return array<string, PushNotificationReceiptProjectorInterface>
     */
    private static function instances(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }
        self::$instances = [];
        foreach (ProductRegistryConfig::section('pushNotificationReceiptProjectors') as $handlerId => $class) {
            if (!is_string($handlerId) || $handlerId === '' || !is_string($class) || $class === '') {
                continue;
            }
            if (!class_exists($class)) {
                continue;
            }
            $obj = new $class();
            if (!$obj instanceof PushNotificationReceiptProjectorInterface) {
                continue;
            }
            self::$instances[$handlerId] = $obj;
        }

        return self::$instances;
    }
}
