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
                'clinical.care-plan.gestionar-recordatorios-como-paciente',
                'Recordatorios de tratamiento',
                'Activar alarmas locales y horarios de medicación o estudios.',
                '/api/clinical/care-plan/preferencias-recordatorios-como-paciente',
                ['recordatorios', 'alarmas', 'medicación', 'tomar medicamento', 'recordatorio estudio'],
                false,
                [
                    'kind' => 'native',
                    'mobile' => ['screen_id' => 'care_plan_reminders_settings'],
                    'web' => ['path' => '/configuracion'],
                ]
            ),
            self::def(
                'clinical.care-plan.recordatorios-como-paciente',
                'Agenda de recordatorios (API)',
                'Horarios derivados de care plans activos para programar alarmas locales.',
                '/api/clinical/care-plan/recordatorios-como-paciente',
                ['agenda recordatorios', 'horarios medicación']
            ),
            self::def(
                'clinical.care-plan.preferencias-recordatorios-como-paciente',
                'Preferencias de recordatorios (API)',
                'Sincronización de activación y horarios personalizados del paciente.',
                '/api/clinical/care-plan/preferencias-recordatorios-como-paciente',
                ['preferencias recordatorios', 'activar recordatorios']
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
                'clinical.laboratory-result.mis-resultados-como-paciente',
                'Ver resultados de laboratorio (UI)',
                'Listado UI JSON de informes del paciente autenticado.',
                '/api/clinical/laboratory-result/mis-resultados-como-paciente',
                ['mis resultados', 'laboratorio', 'análisis', 'estudios'],
                true
            ),
            self::def(
                'clinical.laboratory-result.ver-informe-como-paciente',
                'Detalle informe de laboratorio (UI)',
                'Analitos, conclusión y descarga PDF del informe elegido.',
                '/api/clinical/laboratory-result/ver-informe-como-paciente',
                ['detalle laboratorio', 'ver informe', 'analitos'],
                true
            ),
            self::def(
                'clinical.laboratory-result.descargar-pdf-como-paciente',
                'Descargar PDF de laboratorio',
                'PDF generado en servidor para un informe del paciente.',
                '/api/clinical/laboratory-result/descargar-pdf-como-paciente',
                ['pdf laboratorio', 'descargar informe']
            ),
            self::def(
                'clinical.encounter.mis-atenciones-como-paciente',
                'Ver mis atenciones (UI)',
                'Listado de atenciones ambulatorias con resumen publicado.',
                '/api/clinical/encounter/mis-atenciones-como-paciente',
                ['mis atenciones', 'mis consultas', 'historial consultas'],
                true
            ),
            self::def(
                'clinical.encounter.ver-resumen-atencion-como-paciente',
                'Detalle resumen de atención (UI)',
                'Texto IA, recetas y pedidos de la atención elegida.',
                '/api/clinical/encounter/ver-resumen-atencion-como-paciente',
                ['detalle atención', 'resumen consulta'],
                true
            ),
            self::def(
                'clinical.encounter.ultima-atencion-ui-como-paciente',
                'Última atención (UI)',
                'Resumen de la atención ambulatoria más reciente.',
                '/api/clinical/encounter/ultima-atencion-ui-como-paciente',
                ['última atención', 'última consulta', 'qué me dijeron'],
                true
            ),
            self::def(
                'clinical.encounter.listar-atenciones-como-paciente',
                'Listar atenciones (API)',
                'JSON de atenciones publicadas para cliente nativo.',
                '/api/clinical/encounter/listar-atenciones-como-paciente',
                ['api atenciones paciente']
            ),
            self::def(
                'clinical.electronic-prescription.mis-recetas-como-paciente',
                'Ver recetas electrónicas (UI)',
                'Listado UI JSON de recetas emitidas del paciente autenticado.',
                '/api/clinical/electronic-prescription/mis-recetas-como-paciente',
                ['mis recetas', 'receta electrónica', 'medicación'],
                true
            ),
            self::def(
                'clinical.electronic-prescription.ver-receta-como-paciente',
                'Detalle receta electrónica (UI)',
                'Medicación prescrita y descarga PDF de la receta elegida.',
                '/api/clinical/electronic-prescription/ver-receta-como-paciente',
                ['detalle receta', 'ver receta', 'pdf receta'],
                true
            ),
            self::def(
                'clinical.electronic-prescription.descargar-pdf-como-paciente',
                'Descargar PDF de receta',
                'PDF generado en servidor para una receta emitida del paciente.',
                '/api/clinical/electronic-prescription/descargar-pdf-como-paciente',
                ['pdf receta', 'descargar receta']
            ),
            self::def(
                'clinical.electronic-prescription.verificar-receta',
                'Verificar receta por token',
                'Consulta de vigencia e integridad por código de verificación.',
                '/api/clinical/electronic-prescription/verificar-receta',
                ['verificar receta', 'código receta', 'farmacia']
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
    /**
     * @param list<string> $keywords
     * @param array<string, mixed>|null $clientOpen
     */
    private static function def(
        string $actionId,
        string $actionName,
        string $description,
        string $rbacRoute,
        array $keywords,
        bool $uiJsonDescriptor = false,
        ?array $clientOpen = null
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
        } elseif ($clientOpen !== null) {
            $row['client_open'] = $clientOpen;
            $row['client_interaction'] = ($clientOpen['kind'] ?? '') === 'native'
                ? 'native_screen'
                : 'open';
        }

        return $row;
    }
}
