<?php

namespace common\components\Domain\Clinical\Emergency\Application;

use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones API guardia / triage para el catálogo del asistente.
 */
final class EmergencyUiActionCatalog implements UiActionCatalogProviderInterface
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
                'clinical.emergency-guardia.indicadores-resumen',
                'Indicadores de guardia',
                'Resumen operativo del día: activos, sin triage, tiempos.',
                '/api/clinical/emergency-guardia/indicadores-resumen',
                ['indicadores guardia', 'kpi urgencias'],
                'clinical',
                ['clinical', 'emergency']
            ),
            UiActionCatalogDef::make(
                'clinical.emergency-guardia.elegir-paciente-triage',
                'Elegir paciente sin triage (UI)',
                'Lista de ingresos en guardia pendientes de clasificación Manchester.',
                '/api/clinical/emergency-guardia/elegir-paciente-triage',
                ['triage guardia', 'paciente sin triage', 'clasificar urgencia'],
                'clinical',
                ['clinical', 'emergency'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.emergency-guardia.registrar-triage-formulario',
                'Registrar triage (UI)',
                'Formulario de prioridad, motivo y signos vitales opcionales.',
                '/api/clinical/emergency-guardia/registrar-triage-formulario',
                ['formulario triage', 'manchester', 'prioridad guardia'],
                'clinical',
                ['clinical', 'emergency'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.emergency-guardia.egreso-formulario',
                'Paciente se retiró (UI)',
                'Confirmar retiro del paciente (fecha/hora; destino fijo FUGA).',
                '/api/clinical/emergency-guardia/egreso-formulario',
                ['paciente se retiró', 'fuga guardia', 'abandono guardia', 'egreso guardia'],
                'clinical',
                ['clinical', 'emergency'],
                true,
                null,
                '/api/clinical/emergency-guardia/{guardia_id}/egreso-formulario'
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
