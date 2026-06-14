<?php

namespace common\components\Core\DataAccess;

use common\components\Core\Product\ProductRegistryConfig;

/**
 * Registro estable de scope checkers (IDs declarados en metadata YAML).
 *
 * Clases en {@see common/config/product-registries.php} (`dataAccessScopeCheckers`).
 */
final class ScopeCheckerRegistry
{
    /** @var array<string, ScopeCheckerInterface> */
    private static array $instances = [];

    public static function get(string $checkerId): ScopeCheckerInterface
    {
        $checkerId = trim($checkerId);
        if ($checkerId === '') {
            throw new \InvalidArgumentException('scope_checker vacío.');
        }

        if (!isset(self::$instances[$checkerId])) {
            self::$instances[$checkerId] = self::build($checkerId);
        }

        return self::$instances[$checkerId];
    }

    private static function build(string $checkerId): ScopeCheckerInterface
    {
        $handlers = ProductRegistryConfig::section('dataAccessScopeCheckers');
        if (!isset($handlers[$checkerId])) {
            throw new \InvalidArgumentException('scope_checker desconocido: ' . $checkerId);
        }

        $class = $handlers[$checkerId];
        if (!is_string($class) || !is_subclass_of($class, ScopeCheckerInterface::class)) {
            throw new \InvalidArgumentException('scope_checker inválido: ' . $checkerId);
        }

        return new $class();
    }

    /** @return list<string> */
    public static function knownIds(): array
    {
        return array_keys(ProductRegistryConfig::section('dataAccessScopeCheckers'));
    }

    /** @return array<string, string> */
    public static function optionsForForm(): array
    {
        $out = ['' => '(sin scope checker)'];
        foreach (self::knownIds() as $id) {
            $out[$id] = $id;
        }

        return $out;
    }

    public static function resetForTests(): void
    {
        self::$instances = [];
        ProductRegistryConfig::resetForTests();
    }
}
