<?php

namespace common\components\Domain\Clinical\Home\Domain\Catalog;

use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones de panel home clínico para el catálogo del asistente.
 */
final class HomeUiActionCatalog implements UiActionCatalogProviderInterface
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    public static function discoverAll(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [
            UiActionCatalogDef::make(
                'clinical.home.panel',
                'Panel de inicio',
                'Inicio operativo según encounter (tablero EMER, agenda AMB, internación IMP).',
                '/api/home/panel',
                ['tablero guardia', 'urgencias', 'guardia', 'cola emergencia', 'sala de espera', 'inicio'],
                'clinical',
                ['clinical', 'home'],
                false,
                [
                    'kind' => 'native',
                    'web' => ['path' => '/site/index'],
                    'mobile' => ['screen_id' => 'home.emergency_board'],
                ]
            ),
        ];

        return self::$definitions;
    }

    public static function forUser(int $userId): array
    {
        return YamlIntentCatalogService::filterByRbac(self::discoverAll(), $userId);
    }

    public static function httpRouteForActionId(string $actionId): string
    {
        return UiActionCatalogDef::httpRouteFromDefinitions(self::discoverAll(), $actionId);
    }

    public static function clientOpenForActionId(string $actionId): ?array
    {
        return UiActionCatalogDef::clientOpenFromDefinitions(self::discoverAll(), $actionId);
    }
}
