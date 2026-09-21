<?php

namespace common\components\Domain\Clinical\Inpatient\Domain\Catalog;

use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones API internación para el catálogo del asistente.
 */
final class InpatientUiActionCatalog implements UiActionCatalogProviderInterface
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
                'clinical.internacion.mapa-camas',
                'Mapa de camas (UI)',
                'Ocupación por piso y sala: libre, ocupada, bloqueada o aislamiento.',
                '/api/clinical/internacion/mapa-camas',
                ['mapa de camas', 'camas libres', 'ocupación internación', 'plano de camas'],
                'clinical',
                ['clinical', 'inpatient'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.internacion.indicadores-resumen',
                'Indicadores de internación',
                'Ocupación de camas y estadía media de pacientes activos.',
                '/api/clinical/internacion/indicadores-resumen',
                ['indicadores internación', 'ocupación camas', 'estadía'],
                'clinical',
                ['clinical', 'inpatient']
            ),
            UiActionCatalogDef::make(
                'clinical.internacion.cambio-cama-formulario',
                'Cambio de cama (UI)',
                'Traslado del paciente internado a otra cama del efector.',
                '/api/clinical/internacion/cambio-cama-formulario',
                ['cambio de cama', 'traslado internación', 'mover cama'],
                'clinical',
                ['clinical', 'inpatient'],
                true,
                null,
                '/api/clinical/internacion/{internacion_id}/cambio-cama-formulario'
            ),
            UiActionCatalogDef::make(
                'clinical.internacion.ingreso-formulario',
                'Ingreso a internación (UI)',
                'Admisión de paciente a cama libre del efector.',
                '/api/clinical/internacion/ingreso-formulario',
                ['ingreso internación', 'internar paciente', 'asignar cama', 'admitir paciente'],
                'clinical',
                ['clinical', 'inpatient'],
                true
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
