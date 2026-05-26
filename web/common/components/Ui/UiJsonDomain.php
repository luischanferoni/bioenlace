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
    ];

    public static function forEntity(string $entity): ?string
    {
        $key = strtolower(trim($entity));

        return self::ENTITY_TO_DOMAIN[$key] ?? null;
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

        return null;
    }
}
