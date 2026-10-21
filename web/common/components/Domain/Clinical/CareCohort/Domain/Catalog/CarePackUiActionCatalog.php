<?php

namespace common\components\Domain\Clinical\CareCohort\Domain\Catalog;

use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones API care-packs para el catálogo del asistente (asistencia, seguimiento).
 */
final class CarePackUiActionCatalog implements UiActionCatalogProviderInterface
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function discoverAll(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [
            self::def(
                'care-packs.assistance',
                'Asistencia pre-consulta (pack cohorte)',
                'Cuestionario dinámico antes de la consulta según cohorte del paciente.',
                '/api/care-packs/assistance',
                ['pre consulta', 'cuestionario antes de la consulta', 'asistencia preconsulta', 'preguntas antes del turno'],
                true
            ),
            self::def(
                'care-packs.followup',
                'Seguimiento post-consulta (pack cohorte)',
                'Educación y formulario de evolución por touchpoint de seguimiento.',
                '/api/care-packs/followup',
                ['seguimiento post consulta', 'evolución', 'touchpoint seguimiento', 'formulario seguimiento'],
                true
            ),
        ];

        return self::$definitions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        return YamlIntentCatalogService::filterByRbac(self::discoverAll(), $userId);
    }

    public static function httpRouteForActionId(string $actionId): string
    {
        return UiActionCatalogDef::httpRouteFromDefinitions(self::discoverAll(), $actionId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function clientOpenForActionId(string $actionId): ?array
    {
        return UiActionCatalogDef::clientOpenFromDefinitions(self::discoverAll(), $actionId);
    }

    /**
     * @param list<string> $keywords
     * @return array<string, mixed>
     */
    private static function def(
        string $actionId,
        string $actionName,
        string $description,
        string $rbacRoute,
        array $keywords,
        bool $uiJsonDescriptor = false
    ): array {
        return UiActionCatalogDef::make(
            $actionId,
            $actionName,
            $description,
            $rbacRoute,
            $keywords,
            'care-packs',
            ['clinical', 'care-pack', 'paciente'],
            $uiJsonDescriptor
        );
    }
}
