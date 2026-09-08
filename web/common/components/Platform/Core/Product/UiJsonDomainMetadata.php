<?php

namespace common\components\Platform\Core\Product;

/**
 * Mapeo entidad API → dominio / carpeta de templates UI JSON.
 */
final class UiJsonDomainMetadata
{
    private const CLINICAL_PREFIX = 'clinical';

    /** @var array<string, string> */
    private const ENTITY_DOMAINS = [
        'turnos' => 'scheduling',
        'turnos-perfil' => 'scheduling',
        'consultas-seguimiento' => 'scheduling',
        'profesional-agenda' => 'scheduling',
        'profesional-horarios' => 'organization',
        'servicio-teleconsulta' => 'scheduling',
        'efectores' => 'scheduling',
        'servicios' => 'scheduling',
        'care-plan' => 'clinical',
        'care-plans' => 'clinical',
        'emergency-guardia' => 'clinical',
        'internacion' => 'clinical',
        'laboratory-result' => 'clinical',
        'electronic-prescription' => 'clinical',
        'encounter' => 'clinical',
        'medication-request' => 'clinical',
        'service-request' => 'clinical',
        'condition' => 'clinical',
        'persona' => 'persona',
        'profesional-efector-servicio' => 'organization',
        'data-access' => 'core',
        'queja-paciente' => 'core',
        'paciente-contexto' => 'persona',
        'person-representation' => 'persona',
    ];

    /** @var array<string, string> */
    private const ACTION_TEMPLATE_ALIASES = [
        'encounter/ultima-atencion-ui-como-paciente' => 'ver-resumen-atencion-como-paciente',
    ];

    /** @var array<string, string> */
    private const ENTITY_FOLDER_ALIASES = [
        'care-plans' => 'care-plan',
    ];

    public static function domainForEntity(string $entity): ?string
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return null;
        }
        $domain = self::ENTITY_DOMAINS[$entity] ?? null;

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    public static function clinicalActionIdPrefix(): string
    {
        return self::CLINICAL_PREFIX;
    }

    public static function templateAliasAction(string $entity, string $action): ?string
    {
        $entity = strtolower(trim($entity));
        $action = trim($action);
        if ($entity === '' || $action === '') {
            return null;
        }
        $key = $entity . '/' . $action;
        $target = self::ACTION_TEMPLATE_ALIASES[$key] ?? null;

        return is_string($target) && $target !== '' ? $target : null;
    }

    public static function templateFolderForEntity(string $entity): string
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return '';
        }
        $target = self::ENTITY_FOLDER_ALIASES[$entity] ?? null;

        return is_string($target) && $target !== '' ? $target : $entity;
    }

    public static function resetCacheForTests(): void
    {
        // Sin cache de archivo; no-op para compatibilidad de tests.
    }
}
