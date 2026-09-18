<?php

namespace common\components\Domain\Clinical\CarePlan\Application;

use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones API CarePlan / tratamiento para el catálogo del asistente.
 */
final class CarePlanUiActionCatalog implements UiActionCatalogProviderInterface
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    public static function discoverAll(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $provided = [
            'care_plan_id' => ['description' => 'CarePlan activo del paciente'],
        ];

        self::$definitions = [
            UiActionCatalogDef::make(
                'clinical.care-plan.active',
                'Ver planes de tratamiento activos',
                'Listado de care plans activos del paciente autenticado.',
                '/api/clinical/care-plan/active',
                ['tratamiento', 'care plan', 'plan activo', 'mi tratamiento'],
                'clinical',
                ['clinical', 'care-plan'],
                false,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.ver-tratamiento-paciente',
                'Ver mi tratamiento (UI)',
                'Descriptor UI JSON con planes activos del paciente.',
                '/api/clinical/care-plan/ver-tratamiento-paciente',
                ['ver mi tratamiento', 'plan de tratamiento', 'ui tratamiento'],
                'clinical',
                ['clinical', 'care-plan', 'paciente'],
                true,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.medicamentos-como-paciente',
                'Medicación del tratamiento (UI)',
                'Listado multi-selección de MedicationRequest del CarePlan.',
                '/api/clinical/care-plan/medicamentos-como-paciente',
                ['medicación', 'renovar', 'ajustar', 'medicamentos del plan'],
                'clinical',
                ['clinical', 'care-plan', 'paciente'],
                true,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.confirmar-renovacion-como-paciente',
                'Confirmar renovación de medicación (UI)',
                'Confirmación sin texto libre para solicitar renovación async.',
                '/api/clinical/care-plan/confirmar-renovacion-como-paciente',
                ['renovar medicación', 'confirmar renovación'],
                'clinical',
                ['clinical', 'care-plan', 'paciente'],
                true,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.gestionar-recordatorios-como-paciente',
                'Recordatorios de tratamiento',
                'Activar alarmas locales y horarios de medicación o estudios.',
                '/api/clinical/care-plan/preferencias-recordatorios-como-paciente',
                ['recordatorios', 'alarmas', 'medicación', 'tomar medicamento', 'recordatorio estudio'],
                'clinical',
                ['clinical', 'care-plan', 'paciente'],
                false,
                [
                    'kind' => 'native',
                    'mobile' => ['screen_id' => 'care_plan_reminders_settings'],
                    'web' => ['path' => '/configuracion'],
                ],
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.recordatorios-como-paciente',
                'Agenda de recordatorios (API)',
                'Horarios derivados de care plans activos para programar alarmas locales.',
                '/api/clinical/care-plan/recordatorios-como-paciente',
                ['agenda recordatorios', 'horarios medicación'],
                'clinical',
                ['clinical', 'care-plan', 'paciente'],
                false,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.preferencias-recordatorios-como-paciente',
                'Preferencias de recordatorios (API)',
                'Sincronización de activación y horarios personalizados del paciente.',
                '/api/clinical/care-plan/preferencias-recordatorios-como-paciente',
                ['preferencias recordatorios', 'activar recordatorios'],
                'clinical',
                ['clinical', 'care-plan', 'paciente'],
                false,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.view',
                'Detalle de plan de tratamiento',
                'Care plan por id con actividades.',
                '/api/clinical/care-plan/view',
                ['care plan', 'detalle tratamiento'],
                'clinical',
                ['clinical', 'care-plan'],
                false,
                null,
                null,
                $provided
            ),
            UiActionCatalogDef::make(
                'clinical.care-plan.adherencia-resumen-staff',
                'Adherencia a tratamientos (staff)',
                'Dashboard de planes activos y cumplimiento de actividades por efector.',
                '/api/clinical/care-plans/adherencia-resumen-staff',
                ['adherencia tratamiento', 'planes activos', 'cumplimiento terapia'],
                'clinical',
                ['clinical', 'care-plan', 'staff'],
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
