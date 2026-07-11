<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de contexto cliente ({@see ProductMetadataPaths::clientContextFile()}).
 */
final class ClientContextMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * Flow/intent orientado solo a autogestión paciente (ocultar en catálogo web staff).
     *
     * @param array<string, mixed> $flow
     */
    public static function isPacienteOnlyFlow(array $flow): bool
    {
        $rules = self::webStaffSection()['hide_paciente_flows'] ?? [];
        if (!is_array($rules)) {
            return false;
        }

        $intentId = trim((string) ($flow['action_id'] ?? ''));
        $rbac = trim((string) ($flow['rbac_route'] ?? ''));

        foreach ($rules['intent_ids'] ?? [] as $id) {
            if (is_string($id) && trim($id) !== '' && $intentId === trim($id)) {
                return true;
            }
        }

        if ($intentId !== '') {
            foreach ($rules['intent_id_contains'] ?? [] as $needle) {
                if (is_string($needle) && $needle !== '' && str_contains($intentId, $needle)) {
                    return true;
                }
            }
        }

        if ($rbac !== '') {
            foreach ($rules['rbac_route_contains'] ?? [] as $needle) {
                if (is_string($needle) && $needle !== '' && str_contains($rbac, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function isPacienteMobileClient(?string $appClientId): bool
    {
        return self::sectionHasAppClient(self::mobilePacienteSection(), $appClientId);
    }

    /**
     * App client de un perfil paciente en client-context (móvil, WhatsApp, …).
     * No incluye web staff.
     */
    public static function isPacienteFacingAppClient(?string $appClientId): bool
    {
        return self::profileSectionKeyForAppClient($appClientId) !== null;
    }

    /**
     * Clave de sección en client-context (p. ej. whatsapp_paciente) para un X-App-Client.
     */
    public static function profileSectionKeyForAppClient(?string $appClientId): ?string
    {
        $id = trim((string) $appClientId);
        if ($id === '') {
            return null;
        }

        foreach (self::loadConfig() as $key => $section) {
            if ($key === 'web_staff' || !is_array($section)) {
                continue;
            }
            if (self::sectionHasAppClient($section, $id)) {
                return is_string($key) ? $key : null;
            }
        }

        return null;
    }

    /**
     * Perfil de atajos para un X-App-Client (mobile_paciente, whatsapp_paciente, …).
     *
     * @return array{catalog_basename: string, use_yaml_action_name: bool, omit_subgroups: bool}|null
     */
    public static function shortcutsDisplayForAppClient(?string $appClientId): ?array
    {
        $section = self::profileSectionForAppClient($appClientId);
        if ($section === null) {
            return null;
        }

        $file = trim((string) ($section['shortcuts_catalog'] ?? ''));
        if ($file === '') {
            return null;
        }

        $display = $section['shortcut_display'] ?? [];
        if (!is_array($display)) {
            $display = [];
        }

        return [
            'catalog_basename' => $file,
            'use_yaml_action_name' => ($display['use_yaml_action_name'] ?? false) === true,
            'omit_subgroups' => ($display['omit_subgroups'] ?? false) === true,
        ];
    }

    public static function pacienteMobileShortcutsCatalogBasename(): string
    {
        $file = trim((string) (self::mobilePacienteSection()['shortcuts_catalog'] ?? ''));

        return $file !== '' ? $file : 'assistant-shortcuts-paciente.yaml';
    }

    public static function pacienteMobileShortcutUseYamlActionName(): bool
    {
        $display = self::mobilePacienteSection()['shortcut_display'] ?? [];

        return is_array($display) && ($display['use_yaml_action_name'] ?? false) === true;
    }

    public static function pacienteMobileShortcutOmitSubgroups(): bool
    {
        $display = self::mobilePacienteSection()['shortcut_display'] ?? [];

        return is_array($display) && ($display['omit_subgroups'] ?? false) === true;
    }

    /**
     * Tipos de notificación in-app de paciente a ocultar en bandeja web staff.
     *
     * @return list<string>
     */
    public static function pacienteNotificacionTipos(): array
    {
        $raw = self::webStaffSection()['hide_notification_tipos'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $tipo) {
            if (is_string($tipo) && trim($tipo) !== '') {
                $out[] = trim($tipo);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function webStaffSection(): array
    {
        $section = self::loadConfig()['web_staff'] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = ['web_staff' => [], 'mobile_paciente' => []];

        $path = ProductMetadataPaths::clientContextFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('ClientContextMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        self::$config = [];
        foreach ($data as $key => $section) {
            if (!is_string($key) || $key === 'version' || !is_array($section)) {
                continue;
            }
            self::$config[$key] = $section;
        }

        if (!isset(self::$config['web_staff'])) {
            self::$config['web_staff'] = [];
        }
        if (!isset(self::$config['mobile_paciente'])) {
            self::$config['mobile_paciente'] = [];
        }

        return self::$config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mobilePacienteSection(): array
    {
        $section = self::loadConfig()['mobile_paciente'] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function profileSectionForAppClient(?string $appClientId): ?array
    {
        $id = trim((string) $appClientId);
        if ($id === '') {
            return null;
        }

        foreach (self::loadConfig() as $key => $section) {
            if ($key === 'web_staff' || !is_array($section)) {
                continue;
            }
            if (self::sectionHasAppClient($section, $id)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function sectionHasAppClient(array $section, ?string $appClientId): bool
    {
        $id = trim((string) $appClientId);
        if ($id === '') {
            return false;
        }
        foreach ($section['app_client_ids'] ?? [] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && trim($candidate) === $id) {
                return true;
            }
        }

        return false;
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }
}
