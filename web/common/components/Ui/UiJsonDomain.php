<?php

namespace common\components\Ui;

/**
 * Dominio de carpetas bajo `views/json/` (fase 11 — reorganización por área).
 */
final class UiJsonDomain
{
    public const SCHEDULING = 'scheduling';
    public const CLINICAL = 'clinical';
    public const PERSONA = 'persona';
    public const ORGANIZATION = 'organization';

    /** @var array<string, string> entidad (kebab) → dominio */
    private const ENTITY_TO_DOMAIN = [
        'turnos' => self::SCHEDULING,
        'profesional-agenda' => self::SCHEDULING,
        'efectores' => self::SCHEDULING,
        'servicios' => self::SCHEDULING,
        'care-plan' => self::CLINICAL,
        'emergency-guardia' => self::CLINICAL,
        'internacion' => self::CLINICAL,
        'laboratory-result' => self::CLINICAL,
        'electronic-prescription' => self::CLINICAL,
        'encounter' => self::CLINICAL,
        'medication-request' => self::CLINICAL,
        'service-request' => self::CLINICAL,
        'condition' => self::CLINICAL,
        'persona' => self::PERSONA,
        'profesional-efector-servicio' => self::ORGANIZATION,
        'data-access' => 'core',
    ];

    /**
     * Plantillas reutilizadas por otro action_id (misma entidad).
     * Clave: «entidad/acción» → acción del JSON existente.
     *
     * @see EncounterPatientSummaryController::actionUltimaAtencionUiComoPaciente()
     */
    private const ACTION_TEMPLATE_ALIASES = [
        'encounter/ultima-atencion-ui-como-paciente' => 'ver-resumen-atencion-como-paciente',
    ];

    public static function forEntity(string $entity): ?string
    {
        $key = strtolower(trim($entity));

        return self::ENTITY_TO_DOMAIN[$key] ?? null;
    }

    /**
     * Parsea action_id canónico (p. ej. turnos.crear-como-paciente, clinical.internacion.mapa-camas).
     *
     * @return array{entity: string, action: string}|null
     */
    public static function parseActionId(string $actionId): ?array
    {
        $actionId = strtolower(trim($actionId));
        if ($actionId === '' || strpos($actionId, '.') === false) {
            return null;
        }

        $parts = explode('.', $actionId);
        if ($parts[0] === self::CLINICAL && count($parts) >= 3) {
            return [
                'entity' => $parts[1],
                'action' => implode('.', array_slice($parts, 2)),
            ];
        }

        return [
            'entity' => $parts[0],
            'action' => implode('.', array_slice($parts, 1)),
        ];
    }

    /**
     * Ruta absoluta del template ui_json para un action_id, o null si no hay archivo estático.
     */
    public static function resolveActionIdTemplatePath(string $actionId): ?string
    {
        $parsed = self::parseActionId($actionId);
        if ($parsed === null) {
            return null;
        }

        $path = UiDefinitionTemplateManager::resolveTemplateAbsolutePath(
            $parsed['entity'],
            $parsed['action']
        );
        if ($path !== null) {
            return $path;
        }

        $aliasKey = $parsed['entity'] . '/' . $parsed['action'];
        $aliasAction = self::ACTION_TEMPLATE_ALIASES[$aliasKey] ?? null;
        if ($aliasAction === null || $aliasAction === '') {
            return null;
        }

        return UiDefinitionTemplateManager::resolveTemplateAbsolutePath(
            $parsed['entity'],
            $aliasAction
        );
    }

    /**
     * Rutas relativas a probar (dominio primero, luego legacy plano).
     *
     * @return list<string> sin prefijo alias; ej. `scheduling/turnos/foo.json`
     */
    public static function candidateRelativePaths(string $entity, string $action): array
    {
        $entity = strtolower(trim($entity));
        $action = trim($action);
        if ($entity === '' || $action === '') {
            return [];
        }

        $file = $action . '.json';
        $out = [];
        $domain = self::forEntity($entity);
        if ($domain !== null) {
            $out[] = $domain . '/' . $entity . '/' . $file;
        }
        $out[] = $entity . '/' . $file;

        return array_values(array_unique($out));
    }

    /**
     * Parsea ruta HTTP `/api/v1/...` a par entity + action (+ dominio clínico con prefijo).
     *
     * @return array{entity: string, action: string}|null
     */
    public static function parseApiV1UiRoute(string $route): ?array
    {
        $path = parse_url(trim($route), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = trim($route);
        }

        if (preg_match('#^/api/v\d+/clinical/([\\w-]+)/([\\w-]+)$#', $path, $m) === 1) {
            return ['entity' => strtolower((string) $m[1]), 'action' => (string) $m[2]];
        }

        if (preg_match('#^/api/v\d+/([\\w-]+)/([\\w-]+)$#', $path, $m) === 1) {
            return ['entity' => strtolower((string) $m[1]), 'action' => (string) $m[2]];
        }

        if (preg_match('#^/api/v\d+/(info|listar)$#', $path, $m) === 1) {
            return ['entity' => 'data-access', 'action' => (string) $m[1]];
        }

        return null;
    }
}
