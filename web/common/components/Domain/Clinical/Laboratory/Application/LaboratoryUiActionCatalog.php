<?php

namespace common\components\Domain\Clinical\Laboratory\Application;

use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones API laboratorio para el catálogo del asistente.
 */
final class LaboratoryUiActionCatalog implements UiActionCatalogProviderInterface
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
                'clinical.laboratory-result.mis-resultados-como-paciente',
                'Ver resultados de laboratorio (UI)',
                'Listado UI JSON de informes del paciente autenticado.',
                '/api/clinical/laboratory-result/mis-resultados-como-paciente',
                ['mis resultados', 'laboratorio', 'análisis', 'estudios'],
                'clinical',
                ['clinical', 'laboratory', 'paciente'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.laboratory-result.ver-informe-como-paciente',
                'Detalle informe de laboratorio (UI)',
                'Analitos, recomendaciones y descarga PDF del informe elegido.',
                '/api/clinical/laboratory-result/ver-informe-como-paciente',
                ['detalle laboratorio', 'ver informe', 'analitos'],
                'clinical',
                ['clinical', 'laboratory', 'paciente'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.laboratory-result.descargar-pdf-como-paciente',
                'Descargar PDF de laboratorio',
                'PDF generado en servidor para un informe del paciente.',
                '/api/clinical/laboratory-result/descargar-pdf-como-paciente',
                ['pdf laboratorio', 'descargar informe'],
                'clinical',
                ['clinical', 'laboratory', 'paciente']
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
