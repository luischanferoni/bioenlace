<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Core\Product\ProductRegistryConfig;

final class AssistantContextAspectLoaderRegistry
{
    /** @var array<string, AssistantContextAspectLoaderInterface>|null */
    private static ?array $byAspect = null;

    /**
     * @return array<string, mixed>
     */
    public static function load(string $aspectKey, AssistantContextLoadContext $ctx): array
    {
        $loader = self::loaderFor($aspectKey);
        if ($loader === null) {
            return ['error' => 'aspect_loader_not_registered'];
        }

        return $loader->load($ctx);
    }

    public static function loaderFor(string $aspectKey): ?AssistantContextAspectLoaderInterface
    {
        self::ensureBuilt();
        $key = trim($aspectKey);

        return self::$byAspect[$key] ?? null;
    }

    private static function ensureBuilt(): void
    {
        if (self::$byAspect !== null) {
            return;
        }
        self::$byAspect = [];
        foreach (ProductRegistryConfig::section('assistantContextAspectLoaders') as $class) {
            if (!is_string($class) || $class === '') {
                continue;
            }
            if (!is_subclass_of($class, AssistantContextAspectLoaderInterface::class)) {
                continue;
            }
            $instance = new $class();
            self::$byAspect[$instance->aspectKey()] = $instance;
        }
    }

    public static function resetForTests(): void
    {
        self::$byAspect = null;
    }
}
