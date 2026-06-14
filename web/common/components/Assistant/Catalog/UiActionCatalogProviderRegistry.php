<?php

namespace common\components\Assistant\Catalog;

use common\components\Clinical\Assistant\ClinicalUiActionCatalog;
use common\components\Clinical\CareCohort\Assistant\CarePackUiActionCatalog;
use common\components\Person\Representation\Assistant\PersonRepresentationUiActionCatalog;

/**
 * Registro estable de providers de catálogo UI (solo clases, sin reglas de dominio).
 */
final class UiActionCatalogProviderRegistry
{
    /** @var list<class-string<UiActionCatalogProviderInterface>> */
    private const PROVIDERS = [
        ClinicalUiActionCatalog::class,
        CarePackUiActionCatalog::class,
        PersonRepresentationUiActionCatalog::class,
        DataAccessUiActionCatalog::class,
    ];

    /** @var list<class-string<UiActionCatalogProviderInterface>>|null */
    private static ?array $extraProviders = null;

    /**
     * Registra providers adicionales en runtime (tests, módulos verticales).
     *
     * @param list<class-string<UiActionCatalogProviderInterface>> $classes
     */
    public static function registerExtra(array $classes): void
    {
        self::$extraProviders = array_values(array_unique(array_merge(
            self::$extraProviders ?? [],
            $classes
        )));
    }

    public static function resetForTests(): void
    {
        self::$extraProviders = null;
    }

    /**
     * @return list<class-string<UiActionCatalogProviderInterface>>
     */
    public static function allProviderClasses(): array
    {
        return array_values(array_unique(array_merge(
            self::PROVIDERS,
            self::$extraProviders ?? []
        )));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function discoverAllFromProviders(): array
    {
        $out = [];
        foreach (self::allProviderClasses() as $class) {
            foreach ($class::discoverAll() as $def) {
                if (is_array($def)) {
                    $out[] = $def;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUserFromProviders(int $userId): array
    {
        $out = [];
        foreach (self::allProviderClasses() as $class) {
            foreach ($class::forUser($userId) as $def) {
                if (is_array($def)) {
                    $out[] = $def;
                }
            }
        }

        return $out;
    }

    public static function httpRouteForActionId(string $actionId): string
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return '';
        }

        foreach (self::allProviderClasses() as $class) {
            if (!method_exists($class, 'httpRouteForActionId')) {
                continue;
            }
            $route = trim((string) $class::httpRouteForActionId($actionId));
            if ($route !== '') {
                return $route;
            }
        }

        return '';
    }
}
