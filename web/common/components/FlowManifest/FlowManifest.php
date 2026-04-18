<?php

namespace common\components\FlowManifest;

use Yii;
use yii\helpers\Json;

/**
 * Carga el descriptor `ui_type=flow` compilado para un intent_id y expone un recorte para el cliente.
 */
final class FlowManifest
{
    /**
     * @return array<string, mixed>|null
     */
    public static function loadCompiledRoot(string $intentId): ?array
    {
        $intentId = trim($intentId);
        if ($intentId === '' || preg_match('/^([a-z0-9_-]+)\.(.+)$/i', $intentId, $m) !== 1) {
            return null;
        }
        $entity = strtolower((string) $m[1]);
        $action = (string) $m[2];
        $rel = $entity . '/' . $action . '.json';
        $path = Yii::getAlias('@frontend/modules/api/v1/views/json/' . $rel);
        if (!is_string($path) || !is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = Json::decode($raw);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }
        if (!isset($decoded['ui_type']) || strtolower((string) $decoded['ui_type']) !== 'flow') {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null null si no hay manifiesto compilado
     */
    public static function buildActiveSliceForSubintent(string $intentId, string $activeSubintentId): ?array
    {
        $root = self::loadCompiledRoot($intentId);
        if ($root === null) {
            return null;
        }
        $uiMeta = isset($root['ui_meta']) && is_array($root['ui_meta']) ? $root['ui_meta'] : [];
        $flow = isset($uiMeta['flow']) && is_array($uiMeta['flow']) ? $uiMeta['flow'] : [];
        $steps = isset($flow['steps']) && is_array($flow['steps']) ? $flow['steps'] : [];

        $activeStep = null;
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            if (($step['id'] ?? '') === $activeSubintentId) {
                $activeStep = $step;
                break;
            }
        }

        return [
            'schema_version' => isset($uiMeta['schema_version']) ? (string) $uiMeta['schema_version'] : '1',
            'intent_id' => isset($flow['intent_id']) ? (string) $flow['intent_id'] : $intentId,
            'draft_keys' => isset($flow['draft_keys']) && is_array($flow['draft_keys']) ? $flow['draft_keys'] : [],
            'entry_subintent_id' => isset($flow['entry_subintent_id']) ? (string) $flow['entry_subintent_id'] : '',
            'steps' => $steps,
            'active_subintent_id' => $activeSubintentId,
            'active_step' => $activeStep,
            'open_ui_hints' => isset($flow['open_ui_hints']) && is_array($flow['open_ui_hints']) ? $flow['open_ui_hints'] : [],
        ];
    }
}
