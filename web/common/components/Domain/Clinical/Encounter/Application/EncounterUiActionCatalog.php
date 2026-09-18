<?php

namespace common\components\Domain\Clinical\Encounter\Application;

use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones API Encounter / EpisodeOfCare para el catálogo del asistente.
 */
final class EncounterUiActionCatalog implements UiActionCatalogProviderInterface
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    public static function discoverAll(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $provided = [
            'encounter_id' => ['description' => 'Encounter clínico (alias id_consulta en clientes legacy)'],
        ];

        self::$definitions = [
            UiActionCatalogDef::make(
                'clinical.encounter.analizar',
                'Analizar captura clínica (IA)',
                'Preproceso de texto/audio sobre un encounter antes de guardar.',
                '/api/clinical/encounter/analizar',
                ['analizar', 'encounter', 'documentación', 'captura clínica'],
                'clinical',
                ['clinical', 'encounter', 'capture'],
                false,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.encounter.guardar',
                'Guardar documentación clínica',
                'Persistir condiciones, órdenes y datos del encounter.',
                '/api/clinical/encounter/guardar',
                ['guardar', 'encounter', 'consulta', 'evolución'],
                'clinical',
                ['clinical', 'encounter', 'capture'],
                false,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.encounter.listar-ordenes-activas',
                'Órdenes activas del encounter (UI)',
                'Listado UI JSON de medicación y prácticas del encuentro.',
                '/api/clinical/encounter/listar-ordenes-activas',
                ['órdenes', 'medicación', 'indicaciones', 'encounter'],
                'clinical',
                ['clinical', 'encounter'],
                true,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.encounter.mis-atenciones-como-paciente',
                'Ver mis atenciones (UI)',
                'Listado de atenciones ambulatorias con resumen publicado.',
                '/api/clinical/encounter/mis-atenciones-como-paciente',
                ['mis atenciones', 'mis consultas', 'historial consultas'],
                'clinical',
                ['clinical', 'encounter', 'paciente'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.encounter.ver-resumen-atencion-como-paciente',
                'Detalle resumen de atención (UI)',
                'Texto IA, recetas y pedidos de la atención elegida.',
                '/api/clinical/encounter/ver-resumen-atencion-como-paciente',
                ['detalle atención', 'resumen consulta'],
                'clinical',
                ['clinical', 'encounter', 'paciente'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.encounter.ultima-atencion-ui-como-paciente',
                'Última atención (UI)',
                'Resumen de la atención ambulatoria más reciente.',
                '/api/clinical/encounter/ultima-atencion-ui-como-paciente',
                ['última atención', 'última consulta', 'qué me dijeron'],
                'clinical',
                ['clinical', 'encounter', 'paciente'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.encounter.listar-atenciones-como-paciente',
                'Listar atenciones (API)',
                'JSON de atenciones publicadas para cliente nativo.',
                '/api/clinical/encounter/listar-atenciones-como-paciente',
                ['api atenciones paciente'],
                'clinical',
                ['clinical', 'encounter', 'paciente']
            ),
            UiActionCatalogDef::make(
                'clinical.episode-of-care.by-internacion',
                'Episodio de internación',
                'Resumen EpisodeOfCare por id de internación.',
                '/api/clinical/episode-of-care/by-internacion',
                ['internación', 'episodio', 'ingreso'],
                'clinical',
                ['clinical', 'encounter', 'inpatient']
            ),
            UiActionCatalogDef::make(
                'clinical.episode-of-care.clinical-bundle',
                'Bundle clínico de internación',
                'Órdenes y condiciones del episodio inpatient.',
                '/api/clinical/episode-of-care/clinical-bundle',
                ['internación', 'medicación', 'indicaciones'],
                'clinical',
                ['clinical', 'encounter', 'inpatient']
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
