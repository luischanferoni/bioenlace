<?php

namespace common\components\Ui\Home\Service;

use common\components\Core\Product\ProductRegistryConfig;
use common\components\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

/**
 * Registry: provider_id → implementación. Mapa en {@see common/config/product-registries.php}.
 */
final class HomePanelSectionRegistry
{
    /** @var array<string, HomePanelSectionProviderInterface>|null */
    private static ?array $instances = null;

    public function get(string $providerId): ?HomePanelSectionProviderInterface
    {
        return $this->all()[$providerId] ?? null;
    }

    /**
     * @return array<string, HomePanelSectionProviderInterface>
     */
    private function all(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }

        self::$instances = [];
        foreach (self::providerClasses() as $providerId => $class) {
            if (!is_subclass_of($class, HomePanelSectionProviderInterface::class)) {
                continue;
            }
            self::$instances[$providerId] = new $class();
        }

        return self::$instances;
    }

    /**
     * @return array<string, class-string<HomePanelSectionProviderInterface>>
     */
    private static function providerClasses(): array
    {
        $map = ProductRegistryConfig::section('homePanelSectionProviders');
        $out = [];
        foreach ($map as $providerId => $class) {
            if (!is_string($providerId) || $providerId === '' || !is_string($class) || $class === '') {
                continue;
            }
            $out[$providerId] = $class;
        }

        return $out;
    }

    public static function resetForTests(): void
    {
        self::$instances = null;
        ProductRegistryConfig::resetForTests();
    }
}
