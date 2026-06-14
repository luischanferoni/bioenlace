<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Plugin de catálogo UI del asistente registrado por un dominio de producto.
 *
 * Los motores genéricos ({@see UiActionCatalog}) iteran providers sin enumerar dominios.
 */
interface UiActionCatalogProviderInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function discoverAll(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId): array;
}
