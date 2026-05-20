<?php

namespace common\components\Assistant\EntryPoints\Chat\Envelope;

use common\components\Assistant\FlowManifest\FlowManifest;

/**
 * Sobre público del chat (`kind`: message | interactive | flow).
 * Conversión única desde payloads internos de IntentEngine / SubIntentEngine.
 */
final class AssistantEnvelope
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function isPublicEnvelope(array $payload): bool
    {
        $kind = isset($payload['kind']) ? trim((string) $payload['kind']) : '';

        return in_array($kind, ['message', 'interactive', 'flow'], true);
    }

    /**
     * Convierte payload interno del motor (`success: true` + campos de dominio) al sobre público v3.
     *
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    public static function fromMotorResponse(array $motor): array
    {
        if (self::isPublicEnvelope($motor)) {
            return $motor;
        }

        if (empty($motor['success'])) {
            return $motor;
        }

        if (self::looksLikeFlowMotorPayload($motor)) {
            return self::flowFromMotor($motor);
        }

        if (self::looksLikeInteractiveMotorPayload($motor)) {
            return self::interactiveFromMotor($motor);
        }

        if (isset($motor['text']) && trim((string) $motor['text']) !== '') {
            return self::message((string) $motor['text']);
        }

        return self::message('');
    }

    /**
     * @return array{kind: string, text: string}
     */
    public static function message(string $text): array
    {
        return [
            'kind' => 'message',
            'text' => $text,
        ];
    }

    /**
     * @param list<array{label: string, intent_id: string}> $buttons
     * @return array{kind: string, text: string, buttons: list<array{label: string, intent_id: string}>}
     */
    public static function interactive(string $text, array $buttons): array
    {
        $normalized = [];
        foreach ($buttons as $b) {
            if (!is_array($b)) {
                continue;
            }
            $normalized[] = [
                'label' => trim((string) ($b['label'] ?? '')),
                'intent_id' => trim((string) ($b['intent_id'] ?? '')),
            ];
        }

        return [
            'kind' => 'interactive',
            'text' => $text,
            'buttons' => $normalized,
        ];
    }

    /**
     * @param array<string, mixed> $motor
     */
    private static function interactiveFromMotor(array $motor): array
    {
        $text = self::resolvePrimaryText($motor);
        if ($text === '' && isset($motor['total_actions_available'])) {
            $text = 'Estas son algunas pantallas disponibles para vos.';
        }
        if ($text === '' && isset($motor['actions']) && is_array($motor['actions']) && $motor['actions'] !== []) {
            $text = 'No encontré una pantalla que encaje claramente con tu pedido.';
        }
        if ($text === '') {
            $text = 'Elegí una opción';
        }

        $buttons = [];
        if (isset($motor['remediation']) && is_array($motor['remediation'])) {
            foreach ($motor['remediation'] as $ch) {
                if (!is_array($ch)) {
                    continue;
                }
                $iid = trim((string) ($ch['intent_id'] ?? ''));
                if ($iid === '') {
                    continue;
                }
                $buttons[] = [
                    'label' => trim((string) ($ch['label'] ?? $iid)),
                    'intent_id' => $iid,
                ];
            }
        }

        if ($buttons === []) {
            $actions = isset($motor['actions']) && is_array($motor['actions']) ? $motor['actions'] : [];
            foreach ($actions as $action) {
                if (!is_array($action)) {
                    continue;
                }
                $aid = trim((string) ($action['action_id'] ?? ''));
                if ($aid === '') {
                    continue;
                }
                $label = trim((string) ($action['display_name'] ?? ''));
                if ($label === '') {
                    $label = $aid;
                }
                $buttons[] = [
                    'label' => $label,
                    'intent_id' => $aid,
                ];
            }
        }

        if ($buttons === []) {
            return self::message($text);
        }

        return self::interactive($text, $buttons);
    }

    /**
     * @param array<string, mixed> $motor
     */
    private static function looksLikeInteractiveMotorPayload(array $motor): bool
    {
        if (isset($motor['remediation']) && is_array($motor['remediation']) && $motor['remediation'] !== []) {
            return true;
        }

        return isset($motor['actions']) && is_array($motor['actions']) && $motor['actions'] !== [];
    }

    /**
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    public static function flowFromMotor(array $motor): array
    {
        $text = self::resolvePrimaryText($motor);
        $intentId = trim((string) ($motor['intent_id'] ?? ''));
        $subintentId = trim((string) ($motor['subintent_id'] ?? ''));
        $draftDelta = self::normalizeObjectMap($motor['draft_delta'] ?? []);

        $manifest = isset($motor['flow_manifest']) && is_array($motor['flow_manifest'])
            ? $motor['flow_manifest']
            : null;
        if ($manifest === null && $intentId !== '') {
            $slice = FlowManifest::buildActiveSliceForSubintent($intentId, $subintentId);
            $manifest = $slice !== null ? $slice : self::emptyManifestScaffold($intentId);
        }
        if ($manifest === null) {
            $manifest = self::emptyManifestScaffold($intentId);
        }

        $openUi = isset($motor['open_ui']) && is_array($motor['open_ui']) ? $motor['open_ui'] : null;
        $step = self::buildStepFromOpenUi($openUi, $motor);

        $flowSubmit = isset($motor['flow_submit']) && is_array($motor['flow_submit']) ? $motor['flow_submit'] : null;
        $submit = self::buildSubmit($flowSubmit);

        $hints = self::normalizeHintsList($motor['hints'] ?? []);

        return [
            'kind' => 'flow',
            'text' => $text,
            'session' => [
                'intent_id' => $intentId,
                'subintent_id' => $subintentId,
                'draft_delta' => $draftDelta,
            ],
            'manifest' => $manifest,
            'step' => $step,
            'submit' => $submit,
            'hints' => $hints,
        ];
    }

    /**
     * @param array<string, mixed>|null $openUi
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    private static function buildStepFromOpenUi(?array $openUi, array $motor): array
    {
        $provides = [];
        if (isset($motor['provides']) && is_array($motor['provides'])) {
            foreach ($motor['provides'] as $p) {
                $s = trim((string) $p);
                if ($s !== '') {
                    $provides[] = $s;
                }
            }
        }

        $pending = [];
        if (isset($motor['required_draft_fields']) && is_array($motor['required_draft_fields'])) {
            foreach ($motor['required_draft_fields'] as $f) {
                $s = trim((string) $f);
                if ($s !== '') {
                    $pending[] = $s;
                }
            }
        }

        if ($openUi === null || trim((string) ($openUi['action_id'] ?? '')) === '') {
            return [
                'active' => false,
                'action_id' => '',
                'client_open' => self::emptyClientOpen(),
                'provides' => $provides,
                'pending_fields' => $pending,
            ];
        }

        $co = isset($openUi['client_open']) && is_array($openUi['client_open']) ? $openUi['client_open'] : [];

        return [
            'active' => true,
            'action_id' => trim((string) ($openUi['action_id'] ?? '')),
            'client_open' => self::normalizeClientOpen($co),
            'provides' => $provides,
            'pending_fields' => $pending,
        ];
    }

    /**
     * @param array<string, mixed>|null $flowSubmit
     * @return array<string, mixed>
     */
    private static function buildSubmit(?array $flowSubmit): array
    {
        if ($flowSubmit === null) {
            return [
                'active' => false,
                'route' => '',
                'method' => 'POST',
                'body_template' => (object) [],
            ];
        }

        $route = trim((string) ($flowSubmit['route'] ?? ''));
        if ($route === '') {
            return [
                'active' => false,
                'route' => '',
                'method' => 'POST',
                'body_template' => (object) [],
            ];
        }

        $method = trim((string) ($flowSubmit['method'] ?? 'POST'));
        if ($method === '') {
            $method = 'POST';
        }

        $template = isset($flowSubmit['body_template']) && is_array($flowSubmit['body_template'])
            ? $flowSubmit['body_template']
            : [];

        return [
            'active' => true,
            'route' => $route,
            'method' => $method,
            'body_template' => $template === [] ? (object) [] : $template,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyClientOpen(): array
    {
        return [
            'kind' => '',
            'api' => [
                'route' => '',
                'method' => '',
                'query' => (object) [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $co
     * @return array<string, mixed>
     */
    private static function normalizeClientOpen(array $co): array
    {
        $kind = trim((string) ($co['kind'] ?? ''));
        $api = isset($co['api']) && is_array($co['api']) ? $co['api'] : [];
        $route = trim((string) ($api['route'] ?? ''));
        $method = trim((string) ($api['method'] ?? ''));
        $query = isset($api['query']) && is_array($api['query']) ? $api['query'] : [];

        return [
            'kind' => $kind,
            'api' => [
                'route' => $route,
                'method' => $method,
                'query' => $query === [] ? (object) [] : $query,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyManifestScaffold(string $intentId): array
    {
        return [
            'schema_version' => '1',
            'intent_id' => $intentId,
            'action_name' => '',
            'draft_keys' => [],
            'entry_subintent_id' => '',
            'steps' => [],
            'active_subintent_id' => '',
            'active_step' => (object) [],
        ];
    }

    /**
     * @param array<string, mixed> $motor
     */
    private static function looksLikeFlowMotorPayload(array $motor): bool
    {
        if (trim((string) ($motor['intent_id'] ?? '')) === '') {
            return false;
        }

        return isset($motor['open_ui'])
            || isset($motor['flow_manifest'])
            || isset($motor['flow_submit'])
            || isset($motor['subintent_id']);
    }

    /**
     * @param array<string, mixed> $motor
     */
    private static function resolvePrimaryText(array $motor): string
    {
        $text = isset($motor['text']) ? trim((string) $motor['text']) : '';
        if ($text !== '') {
            return $text;
        }

        return isset($motor['explanation']) ? trim((string) $motor['explanation']) : '';
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>|object
     */
    private static function normalizeObjectMap($value)
    {
        if (!is_array($value) || $value === []) {
            return (object) [];
        }

        return $value;
    }

    /**
     * @param mixed $raw
     * @return list<array{entity: string, id: string, value: string, draft_field: string}>
     */
    private static function normalizeHintsList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $h) {
            if (!is_array($h)) {
                continue;
            }
            $out[] = [
                'entity' => trim((string) ($h['entity'] ?? '')),
                'id' => trim((string) ($h['id'] ?? '')),
                'value' => trim((string) ($h['value'] ?? '')),
                'draft_field' => trim((string) ($h['draft_field'] ?? '')),
            ];
        }

        return $out;
    }
}
