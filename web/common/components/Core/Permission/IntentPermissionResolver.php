<?php

namespace common\components\Core\Permission;

use common\components\Assistant\Catalog\IntentSchemaPaths;

/**
 * Resuelve la clave de permiso lógico de un intent (`Entidad.operacion`).
 */
final class IntentPermissionResolver
{
    /**
     * Clave efectiva: YAML `permission` → inferida por categoría/nombre → `rbac_route` → intent_id.
     *
     * @param array<string, mixed> $manifest
     */
    public static function resolve(string $intentId, array $manifest): string
    {
        $explicit = trim((string) ($manifest['permission'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $inferred = self::inferFromIntentId($intentId, (string) ($manifest['category'] ?? IntentSchemaPaths::categoryForIntentId($intentId) ?? ''));
        if ($inferred !== '') {
            return $inferred;
        }

        $rbac = trim((string) ($manifest['rbac_route'] ?? ''));
        if ($rbac !== '') {
            return '/' . ltrim($rbac, '/');
        }

        return $intentId;
    }

    public static function inferFromIntentId(string $intentId, ?string $category = null): string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return '';
        }

        $category = $category !== null ? trim($category) : '';
        if ($category === '') {
            $category = IntentSchemaPaths::categoryForIntentId($intentId) ?? '';
        }

        $entity = self::entityFromIntentPrefix($intentId);
        if ($entity === '') {
            return '';
        }

        $operation = self::operationFromIntentSuffix($intentId, $category);
        if ($operation === '') {
            return '';
        }

        return $entity . '.' . $operation;
    }

    private static function entityFromIntentPrefix(string $intentId): string
    {
        $dot = strpos($intentId, '.');
        if ($dot === false) {
            return self::mapDomainToEntity($intentId);
        }

        return self::mapDomainToEntity(substr($intentId, 0, $dot));
    }

    private static function mapDomainToEntity(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $map = [
            'turnos' => 'Turno',
            'profesional-efector-servicio' => 'ProfesionalEfectorServicio',
            'profesional-agenda' => 'ProfesionalEfectorServicioAgenda',
            'agenda' => 'ProfesionalEfectorServicioAgenda',
            'personas' => 'Persona',
            'internacion' => 'Internacion',
            'urgencias' => 'GuardiaEpisode',
            'licencia' => 'Licencia',
            'atencion' => 'Atencion',
            'receta' => 'Receta',
            'laboratorio' => 'Laboratorio',
            'tratamiento' => 'Tratamiento',
            'care-packs' => 'CarePack',
            'data-access' => 'DataAccess',
        ];

        return $map[$domain] ?? self::domainToPascal($domain);
    }

    private static function domainToPascal(string $domain): string
    {
        $parts = preg_split('/[-_]+/', $domain) ?: [];
        $out = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $out .= ucfirst($part);
        }

        return $out;
    }

    private static function operationFromIntentSuffix(string $intentId, string $category): string
    {
        $suffix = $intentId;
        $dot = strpos($intentId, '.');
        if ($dot !== false) {
            $suffix = substr($intentId, $dot + 1);
        }
        $suffix = preg_replace('/-flow$/', '', $suffix) ?? $suffix;

        $explicit = [
            'crear-como-paciente' => 'create',
            'crear-para-paciente' => 'create',
            'crear-sobreturno' => 'create',
            'crear-flow' => 'create',
            'crear-profesional-flow' => 'create',
            'crear-profesional' => 'create',
            'modificar-como-paciente' => 'reprogramar',
            'reubicar-como-paciente' => 'reprogramar',
            'cancelar-como-paciente' => 'cancel',
            'cancelar-para-paciente' => 'cancel',
            'confirmar-asistencia' => 'confirmar_asistencia',
            'no-se-presento' => 'marcar_no_presentado',
            'editar' => 'edit',
            'editar-agenda' => 'configure',
            'editar-mi-agenda' => 'configure',
            'info' => 'info',
            'listar' => 'list',
            'ingreso' => 'create',
            'ingreso-flow' => 'create',
            'alta-estructurada' => 'discharge',
            'alta-estructurada-flow' => 'discharge',
            'cambio-cama' => 'change_bed',
            'cambio-cama-flow' => 'change_bed',
            'mapa-camas' => 'view_map',
            'mapa-camas-flow' => 'view_map',
            'ver-tablero-guardia' => 'view_board',
            'triage-paciente-guardia' => 'triage',
            'resolver-conflictos' => 'resolve_conflicts',
            'resolver-conflictos-staff' => 'resolve_conflicts',
            'consultar-politica-autogestion' => 'view_policy',
            'consultar-ocupacion-dia' => 'view_occupancy',
            'ver-agenda-dia-profesional' => 'view_day',
            'indicadores-agenda' => 'view_indicators',
            'conflicto-agenda' => 'view_conflicts',
            'vincular-menor' => 'link_minor',
            'designar-representante' => 'designate_representative',
            'cargar-como-profesional' => 'create',
            'cargar-para-profesional' => 'create',
            'asistencia-pre-consulta' => 'pre_consultation',
            'ver-recetas-como-paciente' => 'view',
            'ver-resultados-como-paciente' => 'view',
            'ver-ultima-como-paciente' => 'view_last',
            'mis-atenciones-como-paciente' => 'view_mine',
            'necesito-atencion' => 'request',
            'recordatorios-como-paciente' => 'view_reminders',
            'adherencia-resumen-staff' => 'view_adherence',
        ];

        if (isset($explicit[$suffix])) {
            return $explicit[$suffix];
        }

        if ($category === IntentSchemaPaths::CATEGORY_CREATE || str_contains($suffix, 'crear') || str_contains($suffix, 'cargar') || str_contains($suffix, 'ingreso')) {
            return 'create';
        }
        if ($category === IntentSchemaPaths::CATEGORY_DELETE || str_contains($suffix, 'cancelar')) {
            return 'cancel';
        }
        if ($category === IntentSchemaPaths::CATEGORY_UPDATE || str_contains($suffix, 'modificar') || str_contains($suffix, 'editar') || str_contains($suffix, 'reubicar')) {
            return 'update';
        }
        if ($category === IntentSchemaPaths::CATEGORY_READ || str_starts_with($suffix, 'ver-') || str_starts_with($suffix, 'consultar') || str_starts_with($suffix, 'mis-')) {
            return 'view';
        }

        return '';
    }
}
