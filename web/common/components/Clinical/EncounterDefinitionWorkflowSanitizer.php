<?php

namespace common\components\Clinical;

use yii\helpers\Json;
use yii\helpers\Url;

/**
 * Limpia URLs MVC legacy en {@see \common\models\Clinical\EncounterDefinition::workflow_json}.
 *
 * Tras fase 12 el wizard Yii por paso (`consulta-*`, `consultas/*`) no existe; la captura es
 * API (`clinical/encounter/guardar`) + formulario en `paciente/formulario-consulta`.
 * Se conservan `titulo`, `relacion` y `requerido` para IA y documentación.
 */
final class EncounterDefinitionWorkflowSanitizer
{
    /** Rutas Yii MVC de consulta retiradas (fase 12). */
    private const LEGACY_URL_PATTERN = '#^(consultas(?:/|$)|consulta-[a-z0-9-]+(?:/|$))#i';

    public static function isLegacyMvcClinicalUrl(string $path): bool
    {
        $normalized = ltrim(trim($path), '/');

        return $normalized !== '' && (bool) preg_match(self::LEGACY_URL_PATTERN, $normalized);
    }

    /**
     * Resuelve URL de paso para navegación legacy; retorna null si no aplica (captura vía API).
     */
    public static function resolveStepUrl(?string $rawUrl): ?string
    {
        $path = trim((string) $rawUrl);
        if ($path === '' || $path === '#') {
            return null;
        }
        if (self::isLegacyMvcClinicalUrl($path)) {
            return null;
        }

        return Url::toRoute($path);
    }

    /**
     * @return array{
     *   json: string,
     *   changed: bool,
     *   legacy_urls: list<string>,
     *   error: ?string
     * }
     */
    public static function sanitizeWorkflowJson(string $workflowJson): array
    {
        $legacyUrls = [];
        try {
            $decoded = Json::decode($workflowJson);
        } catch (\Throwable $e) {
            return [
                'json' => $workflowJson,
                'changed' => false,
                'legacy_urls' => [],
                'error' => $e->getMessage(),
            ];
        }

        if (!is_array($decoded) || !isset($decoded['conf']) || !is_array($decoded['conf'])) {
            return [
                'json' => $workflowJson,
                'changed' => false,
                'legacy_urls' => [],
                'error' => 'workflow_json sin clave conf[]',
            ];
        }

        $changed = false;
        foreach ($decoded['conf'] as $idx => $step) {
            if (!is_array($step)) {
                continue;
            }
            $url = isset($step['url']) ? trim((string) $step['url']) : '';
            if ($url === '' || !self::isLegacyMvcClinicalUrl($url)) {
                continue;
            }
            $legacyUrls[] = $url;
            $decoded['conf'][$idx]['url'] = '';
            $changed = true;
        }

        return [
            'json' => $changed ? Json::encode($decoded) : $workflowJson,
            'changed' => $changed,
            'legacy_urls' => $legacyUrls,
            'error' => null,
        ];
    }

    /**
     * @return list<array{id: int, service_id: int, encounter_class: string, legacy_urls: list<string>, error: ?string}>
     */
    public static function auditRows(iterable $rows): array
    {
        $report = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $json = (string) ($row['workflow_json'] ?? $row['pasos_json'] ?? '');
            $legacy = [];
            $error = null;
            try {
                $decoded = Json::decode($json);
                if (is_array($decoded['conf'] ?? null)) {
                    foreach ($decoded['conf'] as $step) {
                        if (!is_array($step)) {
                            continue;
                        }
                        $url = trim((string) ($step['url'] ?? ''));
                        if ($url !== '' && self::isLegacyMvcClinicalUrl($url)) {
                            $legacy[] = $url;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
            if ($legacy !== [] || $error !== null) {
                $report[] = [
                    'id' => $id,
                    'service_id' => (int) ($row['service_id'] ?? $row['id_servicio'] ?? 0),
                    'encounter_class' => (string) ($row['encounter_class'] ?? ''),
                    'legacy_urls' => $legacy,
                    'error' => $error,
                ];
            }
        }

        return $report;
    }
}
