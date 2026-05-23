<?php

namespace common\components\Assistant\Catalog;

use common\components\Ui\ApiV1HttpRoute;

/**
 * Acciones API clínicas FHIR para el catálogo del asistente (no son flows YAML).
 *
 * Rutas HTTP: `/api/v1/clinical/...`; RBAC: `/api/clinical/...` (sin segmento v1).
 */
final class ClinicalUiActionCatalog
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
                'clinical.encounter.analizar',
                'Analizar captura clínica (IA)',
                'Preproceso de texto/audio sobre un encounter antes de guardar.',
                '/api/clinical/encounter/analizar',
                ['analizar', 'encounter', 'documentación', 'captura clínica']
            ),
            self::def(
                'clinical.encounter.guardar',
                'Guardar documentación clínica',
                'Persistir condiciones, órdenes y datos del encounter.',
                '/api/clinical/encounter/guardar',
                ['guardar', 'encounter', 'consulta', 'evolución']
            ),
            self::def(
                'clinical.care-plan.active',
                'Ver planes de tratamiento activos',
                'Listado de care plans activos del paciente autenticado.',
                '/api/clinical/care-plan/active',
                ['tratamiento', 'care plan', 'plan activo', 'mi tratamiento']
            ),
            self::def(
                'clinical.care-plan.ver-tratamiento-paciente',
                'Ver mi tratamiento (UI)',
                'Descriptor UI JSON con planes activos del paciente.',
                '/api/clinical/care-plan/ver-tratamiento-paciente',
                ['ver mi tratamiento', 'plan de tratamiento', 'ui tratamiento']
            ),
            self::def(
                'clinical.encounter.listar-ordenes-activas',
                'Órdenes activas del encounter (UI)',
                'Listado UI JSON de medicación y prácticas del encuentro.',
                '/api/clinical/encounter/listar-ordenes-activas',
                ['órdenes', 'medicación', 'indicaciones', 'encounter']
            ),
            self::def(
                'clinical.care-plan.view',
                'Detalle de plan de tratamiento',
                'Care plan por id con actividades.',
                '/api/clinical/care-plan/view',
                ['care plan', 'detalle tratamiento']
            ),
            self::def(
                'clinical.episode-of-care.by-internacion',
                'Episodio de internación',
                'Resumen EpisodeOfCare por id de internación.',
                '/api/clinical/episode-of-care/by-internacion',
                ['internación', 'episodio', 'ingreso']
            ),
            self::def(
                'clinical.episode-of-care.clinical-bundle',
                'Bundle clínico de internación',
                'Órdenes y condiciones del episodio inpatient.',
                '/api/clinical/episode-of-care/clinical-bundle',
                ['internación', 'medicación', 'indicaciones']
            ),
            self::def(
                'clinical.laboratory-results.mis-resultados-como-paciente',
                'Ver resultados de laboratorio (UI)',
                'Listado UI JSON de informes del paciente autenticado.',
                '/api/clinical/laboratory-results/mis-resultados-como-paciente',
                ['mis resultados', 'laboratorio', 'análisis', 'estudios'],
                true
            ),
            self::def(
                'clinical.laboratory-results.sincronizar-como-paciente',
                'Actualizar resultados de laboratorio (UI)',
                'Sincronización pull desde LIS; POST devuelve ui_submit_result.',
                '/api/clinical/laboratory-results/sincronizar-como-paciente',
                ['actualizar resultados', 'sincronizar laboratorio', 'traer análisis'],
                true
            ),
            self::def(
                'clinical.laboratory-results.ver-informe-como-paciente',
                'Detalle informe de laboratorio (UI)',
                'Analitos, conclusión y descarga PDF del informe elegido.',
                '/api/clinical/laboratory-results/ver-informe-como-paciente',
                ['detalle laboratorio', 'ver informe', 'analitos'],
                true
            ),
            self::def(
                'clinical.laboratory-results.descargar-pdf-como-paciente',
                'Descargar PDF de laboratorio',
                'PDF generado en servidor para un informe del paciente.',
                '/api/clinical/laboratory-results/descargar-pdf-como-paciente',
                ['pdf laboratorio', 'descargar informe']
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

        $row = [
            'action_id' => $actionId,
            'action_name' => $actionName,
            'display_name' => $actionName,
            'description' => $description,
            'entity' => 'clinical',
            'route' => $httpRoute,
            'rbac_route' => $rbacRoute,
            'keywords' => $keywords,
            'synonyms' => [],
            'tags' => ['clinical', 'fhir'],
            'parameters' => [
                'expected' => [],
                'provided' => [
                    'encounter_id' => ['description' => 'Encounter clínico (alias id_consulta en clientes legacy)'],
                    'care_plan_id' => ['description' => 'CarePlan activo del paciente'],
                ],
            ],
            'intent_semantics' => null,
            'flow_capable' => false,
        ];

        if ($uiJsonDescriptor) {
            $row['client_open'] = [
                'kind' => 'ui_json',
                'api' => [
                    'route' => $httpRoute,
                    'method' => 'GET|POST',
                ],
            ];
            $row['client_interaction'] = 'ui_asistente_json';
        }

        return $row;
    }
}
