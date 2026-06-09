<?php

namespace common\components\Assistant\Catalog;

use common\components\Ui\ApiV1HttpRoute;

/**
 * Acciones API care-packs para el catálogo del asistente (asistencia, seguimiento).
 */
final class CarePackUiActionCatalog
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
        $httpRoute = ApiV1HttpRoute::normalize($rbacRoute);

        return [
            'action_id' => $actionId,
            'action_name' => $actionName,
            'display_name' => $actionName,
            'description' => $description,
            'entity' => 'care-packs',
            'route' => $httpRoute,
            'rbac_route' => $rbacRoute,
            'keywords' => $keywords,
            'synonyms' => [],
            'tags' => ['clinical', 'care-pack', 'paciente'],
            'parameters' => [
                'expected' => [],
                'provided' => [],
            ],
            'ui_json_descriptor' => $uiJsonDescriptor,
        ];
    }
}
