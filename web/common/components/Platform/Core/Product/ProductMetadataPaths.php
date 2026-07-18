<?php

namespace common\components\Platform\Core\Product;

/**
 * Rutas de metadata declarativa del producto (intents, reglas NL, permisos de dominio).
 *
 * Para otro rubro: apuntar {@see \Yii::$app->params productMetadataDir} a otra carpeta bajo common/metadata/.
 */
final class ProductMetadataPaths
{
    public static function baseDir(): string
    {
        if (class_exists(\Yii::class, false) && \Yii::$app !== null && \Yii::$app->has('params')) {
            $configured = \Yii::$app->params['productMetadataDir'] ?? null;
            if (is_string($configured) && trim($configured) !== '') {
                $dir = realpath(trim($configured));

                return $dir !== false ? $dir : rtrim(trim($configured), '/\\');
            }
        }

        $default = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'metadata' . DIRECTORY_SEPARATOR . 'bioenlace';
        $resolved = realpath($default);

        return $resolved !== false ? $resolved : $default;
    }

    public static function assistantDir(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'assistant';
    }

    public static function permissionDir(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'permission';
    }

    public static function intentsDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'intents';
    }

    public static function globalsDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'globals';
    }

    public static function intentClassificationRulesFile(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'intent-classification-rules.yaml';
    }

    public static function intentFamiliesFile(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'intent-families.yaml';
    }

    public static function hintResolutionFile(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'hint-resolution.yaml';
    }

    public static function assistantShortcutsFile(?string $basename = null): string
    {
        $file = trim((string) ($basename ?? ''));
        if ($file === '') {
            $file = 'assistant-shortcuts.yaml';
        }

        return self::assistantDir() . DIRECTORY_SEPARATOR . $file;
    }

    public static function assistantChannelCopyFile(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'channel-copy.yaml';
    }

    public static function domainOperationPoliciesFile(): string
    {
        return self::permissionDir() . DIRECTORY_SEPARATOR . 'domain-operation-policies.yaml';
    }

    public static function intentGrantMigrationMapFile(): string
    {
        return self::permissionDir() . DIRECTORY_SEPARATOR . 'intent-grant-migration-map.yaml';
    }

    public static function homePanelManifestFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'home_panel_manifest.yaml';
    }

    public static function clientContextFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'client-context.yaml';
    }

    public static function pacienteContextoOfferingFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'paciente-contexto-offering.yaml';
    }

    public static function recursosProvincialesFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'geo' . DIRECTORY_SEPARATOR . 'recursos-provinciales.yaml';
    }

    public static function uiJsonDomainsFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'json-domains.yaml';
    }

    public static function uiScreenParamsFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'screen-params.yaml';
    }

    public static function uiSelectOptionSourcesFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'select-option-sources.yaml';
    }

    public static function clinicalTextIaFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'clinical-text-ia.yaml';
    }

    public static function aiCostReferenceFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'ai-cost-reference.yaml';
    }

    public static function autonomousAgentsDir(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'autonomous_agents';
    }

    public static function autonomousAgentFile(string $agentId): string
    {
        return self::autonomousAgentsDir() . DIRECTORY_SEPARATOR . $agentId . '.yaml';
    }

    public static function snomedTerminologyFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'terminology' . DIRECTORY_SEPARATOR . 'snomed-terminology.yaml';
    }

    public static function organizationDir(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'organization';
    }

    public static function agendaByEncounterClassFile(): string
    {
        return self::organizationDir() . DIRECTORY_SEPARATOR . 'agenda-by-encounter-class.yaml';
    }

    public static function pricingPesByEncounterClassFile(): string
    {
        return self::organizationDir() . DIRECTORY_SEPARATOR . 'pricing-pes-by-encounter-class.yaml';
    }

    public static function efectorAtributosFile(): string
    {
        return self::organizationDir() . DIRECTORY_SEPARATOR . 'efector-atributos.yaml';
    }

    public static function schedulingDir(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'scheduling';
    }

    public static function turnoBehaviorProfileContractFile(): string
    {
        return self::schedulingDir() . DIRECTORY_SEPARATOR . 'turno-behavior-profile-contract.yaml';
    }
}
