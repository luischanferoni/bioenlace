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

        self::$config = ['web_staff' => []];

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

        if (is_array($data) && isset($data['web_staff']) && is_array($data['web_staff'])) {
            self::$config['web_staff'] = $data['web_staff'];
        }

        return self::$config;
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }
}
