<?php

namespace common\components\Platform\Core\Product;

/**
 * Rutas de metadata declarativa del producto (intents, reglas NL, permisos, UI, AI).
 *
 * Canónico: YAML colocalizado bajo `components/Domain/<BC>/…` y `components/Platform/…`
 * (p. ej. `Application/Flows/intents`). Knobs de negocio → PHP `*Catalog` / `*AgentPolicy`.
 *
 * `common/metadata/bioenlace/` queda solo como README histórico (sin YAML).
 *
 * @see web/docs/decisions/ddd-bounded-contexts-capas-y-metadata.md
 */
final class ProductMetadataPaths
{
    /** `common/components` (padre de Domain/ y Platform/). */
    public static function componentsRoot(): string
    {
        $default = dirname(__DIR__, 3);
        $resolved = realpath($default);

        return $resolved !== false ? $resolved : $default;
    }

    public static function componentsDomainRoot(): string
    {
        return self::componentsRoot() . DIRECTORY_SEPARATOR . 'Domain';
    }

    public static function componentsPlatformRoot(): string
    {
        return self::componentsRoot() . DIRECTORY_SEPARATOR . 'Platform';
    }

    /**
     * Raíces de intents colocalizadas.
     *
     * - `Domain/<BC>/Application/Flows/intents`
     * - `Platform/Assistant/Application/Flows/intents`
     *
     * @return list<string> rutas absolutas
     */
    public static function colocatedIntentRoots(): array
    {
        $roots = [];
        $domainRoot = realpath(self::componentsDomainRoot());
        if ($domainRoot !== false && is_dir($domainRoot)) {
            foreach (glob($domainRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $bcDir) {
                $intents = $bcDir
                    . DIRECTORY_SEPARATOR . 'Application'
                    . DIRECTORY_SEPARATOR . 'Flows'
                    . DIRECTORY_SEPARATOR . 'intents';
                if (is_dir($intents)) {
                    $resolved = realpath($intents);
                    $roots[] = $resolved !== false ? $resolved : $intents;
                }
            }
        }

        $platformFlows = self::componentsPlatformRoot()
            . DIRECTORY_SEPARATOR . 'Assistant'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Flows'
            . DIRECTORY_SEPARATOR . 'intents';
        if (is_dir($platformFlows)) {
            $resolved = realpath($platformFlows);
            $roots[] = $resolved !== false ? $resolved : $platformFlows;
        }

        sort($roots);

        return $roots;
    }

    /** Metadata del asistente en `components/Platform/Assistant/`. */
    public static function assistantDir(): string
    {
        return self::componentsPlatformRoot() . DIRECTORY_SEPARATOR . 'Assistant';
    }

    /**
     * Auth composition YAML en Permission/metadata/.
     */
    public static function permissionDir(): string
    {
        return self::componentsPlatformRoot()
            . DIRECTORY_SEPARATOR . 'Core'
            . DIRECTORY_SEPARATOR . 'Permission'
            . DIRECTORY_SEPARATOR . 'metadata';
    }

    public static function uiPresentationDir(): string
    {
        return self::componentsPlatformRoot()
            . DIRECTORY_SEPARATOR . 'Ui'
            . DIRECTORY_SEPARATOR . 'Presentation';
    }

    public static function aiApplicationDir(): string
    {
        return self::componentsPlatformRoot()
            . DIRECTORY_SEPARATOR . 'Ai'
            . DIRECTORY_SEPARATOR . 'Application';
    }

    /**
     * @deprecated Los intents viven en Application/Flows/intents. Usar {@see IntentSchemaPaths::intentRoots()}.
     */
    public static function intentsDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'Flows' . DIRECTORY_SEPARATOR . 'intents';
    }

    public static function globalsDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'globals';
    }

    /** YAML de canales (`prompt.yaml` / `ui-text.yaml`); PHP de canales vive en Chat/Channels/. */
    public static function assistantChannelsDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Channels';
    }

    /** Textos UX transversales (Presentation). */
    public static function assistantUiTextDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Presentation';
    }

    /** Prompt preprocess (Application/Preprocess); loaders PHP en Assistant/Preprocess/. */
    public static function assistantPreprocessDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'Preprocess';
    }

    public static function assistantRoutingDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'Routing';
    }

    public static function assistantChannelDir(string $channelName): string
    {
        $name = trim($channelName);
        if ($name === '') {
            return self::assistantChannelsDir();
        }

        return self::assistantChannelsDir() . DIRECTORY_SEPARATOR . $name;
    }

    public static function assistantChannelPromptFile(string $channelName): string
    {
        return self::assistantChannelDir($channelName) . DIRECTORY_SEPARATOR . 'prompt.yaml';
    }

    public static function assistantChannelUiTextFile(string $channelName): string
    {
        return self::assistantChannelDir($channelName) . DIRECTORY_SEPARATOR . 'ui-text.yaml';
    }

    public static function assistantCatalogDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'Catalog';
    }

    public static function contextHisAreasCatalogFile(): string
    {
        return self::assistantCatalogDir() . DIRECTORY_SEPARATOR . 'context-his-areas.yaml';
    }

    public static function smartCatalogFile(): string
    {
        return self::assistantCatalogDir() . DIRECTORY_SEPARATOR . 'smart-catalog.yaml';
    }

    public static function preprocessExtractionCategoriesFile(): string
    {
        return self::assistantCatalogDir() . DIRECTORY_SEPARATOR . 'preprocess-extraction-categories.yaml';
    }

    public static function preprocessRoutingHintsFile(): string
    {
        return self::assistantCatalogDir() . DIRECTORY_SEPARATOR . 'preprocess-routing-hints.yaml';
    }

    public static function smartCatalogRoutingFile(): string
    {
        return self::assistantRoutingFile('smart-catalog-routing');
    }

    public static function assistantSchemasDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'Schemas';
    }

    public static function assistantSchemaFile(string $basename): string
    {
        $name = trim($basename);
        if ($name === '') {
            return self::assistantSchemasDir();
        }
        if (substr($name, -5) !== '.yaml') {
            $name .= '.yaml';
        }

        return self::assistantSchemasDir() . DIRECTORY_SEPARATOR . $name;
    }

    public static function assistantRoutingFile(string $basename): string
    {
        $name = trim($basename);
        if ($name === '') {
            return self::assistantRoutingDir();
        }
        if (substr($name, -5) !== '.yaml') {
            $name .= '.yaml';
        }

        return self::assistantRoutingDir() . DIRECTORY_SEPARATOR . $name;
    }

    public static function guideChannelFile(): string
    {
        return self::assistantChannelPromptFile('Guide');
    }

    public static function plannerPromptFile(): string
    {
        return self::assistantChannelPromptFile('Planner');
    }

    public static function preprocessPromptFile(): string
    {
        return self::assistantPreprocessDir() . DIRECTORY_SEPARATOR . 'prompt.yaml';
    }

    public static function ambiguousChannelFile(): string
    {
        return self::assistantChannelUiTextFile('Ambiguous');
    }

    public static function bookingOfferFile(): string
    {
        return self::assistantRoutingFile('booking-offer');
    }

    public static function intentFamiliesFile(): string
    {
        return self::assistantRoutingFile('intent-families');
    }

    public static function threadStateFile(): string
    {
        return self::assistantRoutingFile('thread-state');
    }

    public static function assistantShortcutsFile(?string $basename = null): string
    {
        $file = trim((string) ($basename ?? ''));
        if ($file === '') {
            $file = 'assistant-shortcuts.yaml';
        }

        return self::assistantDir() . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . $file;
    }

    public static function assistantUiTextByClientFile(): string
    {
        return self::assistantUiTextDir() . DIRECTORY_SEPARATOR . 'by-client.yaml';
    }

    public static function domainOperationPoliciesFile(): string
    {
        return self::permissionDir() . DIRECTORY_SEPARATOR . 'domain-operation-policies.yaml';
    }

    public static function capabilitiesDir(): string
    {
        return self::permissionDir() . DIRECTORY_SEPARATOR . 'capabilities';
    }

    public static function homePanelManifestFile(): string
    {
        return self::uiPresentationDir() . DIRECTORY_SEPARATOR . 'home-panel-manifest.yaml';
    }

    public static function clientContextFile(): string
    {
        return self::uiPresentationDir() . DIRECTORY_SEPARATOR . 'client-context.yaml';
    }

    public static function pacienteContextoOfferingFile(): string
    {
        return self::uiPresentationDir() . DIRECTORY_SEPARATOR . 'paciente-contexto-offering.yaml';
    }

    public static function uiScreenParamsFile(): string
    {
        return self::uiPresentationDir() . DIRECTORY_SEPARATOR . 'screen-params.yaml';
    }

    public static function clinicalTextIaFile(): string
    {
        return self::aiApplicationDir() . DIRECTORY_SEPARATOR . 'clinical-text-ia.yaml';
    }

    public static function aiCostReferenceFile(): string
    {
        return self::aiApplicationDir() . DIRECTORY_SEPARATOR . 'ai-cost-reference.yaml';
    }
}
