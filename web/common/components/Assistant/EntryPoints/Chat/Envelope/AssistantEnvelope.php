<?php

namespace common\components\Assistant\EntryPoints\Chat\Envelope;

use common\components\Assistant\FlowManifest\FlowManifest;
use common\components\Assistant\UiActions\AssistantClientOpenEnricher;
use common\components\Ui\ApiV1HttpRoute;
use common\components\Ui\UiDefinitionTemplateManager;

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
            $label = trim((string) ($b['label'] ?? ''));
            $intentId = trim((string) ($b['intent_id'] ?? ''));
            if ($label === '' || $intentId === '') {
                continue;
            }
            $normalized[] = [
                'label' => $label,
                'intent_id' => $intentId,
            ];
        }

        if ($normalized === []) {
            return self::message($text);
        }

        return [
            'kind' => 'interactive',
            'text' => self::appendInteractiveButtonsHint($text, count($normalized)),
            'buttons' => $normalized,
        ];
    }

    /**
     * Sufijo fijo cuando hay botones accionables (no depende de la redacción de la IA).
     */
    private static function appendInteractiveButtonsHint(string $text, int $buttonCount): string
    {
        if ($buttonCount <= 0) {
            return $text;
        }

        $text = rtrim($text);
        if ($text !== '' && !preg_match('/[.!?…]"?\s*$/u', $text)) {
            $text .= '.';
        }

        $suffix = $buttonCount === 1
            ? ' O tocar el botón de abajo.'
            : ' O tocar los botones de abajo.';

        return $text . $suffix;
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
        $step = self::buildStepFromOpenUi($openUi, $motor, $manifest);

        $flowSubmit = isset($motor['flow_submit']) && is_array($motor['flow_submit']) ? $motor['flow_submit'] : null;
        $submit = self::buildSubmit($flowSubmit);
        $flowDismiss = isset($motor['flow_dismiss']) && is_array($motor['flow_dismiss']) ? $motor['flow_dismiss'] : null;
        $dismiss = self::buildDismiss($flowDismiss);

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
            'dismiss' => $dismiss,
            'hints' => $hints,
        ];
    }

    /**
     * @param array<string, mixed>|null $openUi
     * @param array<string, mixed> $motor
     * @param array<string, mixed>|null $manifest
     * @return array<string, mixed>
     */
    private static function buildStepFromOpenUi(?array $openUi, array $motor, ?array $manifest = null): array
    {
        $provides = [];
        if (isset($motor['provides']) && is_array($motor['provides'])) {
            foreach ($motor['provides'] as $p) {
                $s = self::scalarString($p);
                if ($s !== '') {
                    $provides[] = $s;
                }
            }
        }

        $pending = [];
        if (isset($motor['required_draft_fields']) && is_array($motor['required_draft_fields'])) {
            foreach ($motor['required_draft_fields'] as $f) {
                $s = self::scalarString($f);
                if ($s !== '') {
                    $pending[] = $s;
                }
            }
        }

        if ($openUi === null || self::scalarString($openUi['action_id'] ?? '') === '') {
            return [
                'active' => false,
                'action_id' => '',
                'client_open' => self::emptyClientOpen(),
                'provides' => $provides,
                'pending_fields' => $pending,
            ];
        }

        $co = isset($openUi['client_open']) && is_array($openUi['client_open']) ? $openUi['client_open'] : [];
        if (self::scalarString($co['kind'] ?? '') === '' && $manifest !== null) {
            $fromManifest = self::clientOpenFromManifest($manifest);
            if ($fromManifest !== null) {
                $co = $fromManifest;
            }
        }

        return [
            'active' => true,
            'action_id' => self::scalarString($openUi['action_id'] ?? ''),
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

        $route = self::scalarString($flowSubmit['route'] ?? '');
        if ($route === '') {
            return [
                'active' => false,
                'route' => '',
                'method' => 'POST',
                'body_template' => (object) [],
            ];
        }

        $method = self::scalarString($flowSubmit['method'] ?? 'POST', 'POST');
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
     * @param array<string, mixed>|null $flowDismiss
     * @return array{active: bool, label: string, actions: list<array{label: string, href: string, variant: string}>}
     */
    private static function buildDismiss(?array $flowDismiss): array
    {
        if ($flowDismiss === null) {
            return [
                'active' => false,
                'label' => '',
                'actions' => [],
            ];
        }
        $label = trim((string) ($flowDismiss['label'] ?? 'Entendido'));
        if ($label === '') {
            $label = 'Entendido';
        }
        $actions = [];
        if (isset($flowDismiss['actions']) && is_array($flowDismiss['actions'])) {
            foreach ($flowDismiss['actions'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $actionLabel = trim((string) ($row['label'] ?? ''));
                $href = trim((string) ($row['href'] ?? ''));
                if ($actionLabel === '' || $href === '') {
                    continue;
                }
                $actions[] = [
                    'label' => $actionLabel,
                    'href' => $href,
                    'variant' => trim((string) ($row['variant'] ?? 'secondary')),
                ];
            }
        }

        return [
            'active' => true,
            'label' => $label,
            'actions' => $actions,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>|null
     */
    private static function clientOpenFromManifest(array $manifest): ?array
    {
        $active = isset($manifest['active_step']) && is_array($manifest['active_step']) ? $manifest['active_step'] : null;
        if ($active === null) {
            return null;
        }
        $ui = isset($active['ui']) && is_array($active['ui']) ? $active['ui'] : null;
        if ($ui === null) {
            return null;
        }
        $tabs = isset($ui['tabs']) && is_array($ui['tabs']) ? $ui['tabs'] : [];
        foreach ($tabs as $tab) {
            if (!is_array($tab)) {
                continue;
            }
            $route = ApiV1HttpRoute::normalize(self::scalarString($tab['route'] ?? ''));
            if ($route === '') {
                continue;
            }
            $actionId = self::scalarString($tab['action_id'] ?? '');
            $action = [
                'action_id' => $actionId !== '' ? $actionId : 'flow.ui',
                'display_name' => $actionId,
                'description' => '',
                'entity' => null,
                'route' => $route,
                'parameters' => ['expected' => [], 'provided' => []],
            ];
            $enriched = AssistantClientOpenEnricher::enrich($action);
            $co = $enriched['client_open'] ?? null;
            if (is_array($co) && trim((string) ($co['kind'] ?? '')) !== '') {
                if (($co['kind'] ?? '') === 'intent' && UiDefinitionTemplateManager::hasTemplateForApiRoute($route)) {
                    return [
                        'kind' => 'ui_json',
                        'api' => [
                            'route' => $route,
                            'method' => 'GET|POST',
                        ],
                    ];
                }

                return $co;
            }
            if (UiDefinitionTemplateManager::hasTemplateForApiRoute($route)) {
                return [
                    'kind' => 'ui_json',
                    'api' => [
                        'route' => $route,
                        'method' => 'GET|POST',
                    ],
                ];
            }
        }

        return null;
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
        $kind = self::scalarString($co['kind'] ?? '');
        $api = isset($co['api']) && is_array($co['api']) ? $co['api'] : [];
        $route = self::scalarString($api['route'] ?? '');
        $method = self::scalarString($api['method'] ?? '');
        $query = isset($api['query']) && is_array($api['query']) ? $api['query'] : [];
        $queryOut = [];
        foreach ($query as $qk => $qv) {
            $key = is_string($qk) ? trim($qk) : '';
            if ($key === '' || is_array($qv) || is_object($qv)) {
                continue;
            }
            if (is_bool($qv)) {
                $queryOut[$key] = $qv ? '1' : '0';
                continue;
            }
            if (!is_string($qv) && !is_int($qv) && !is_float($qv)) {
                continue;
            }
            $queryOut[$key] = trim((string) $qv);
        }

        return [
            'kind' => $kind,
            'api' => [
                'route' => $route,
                'method' => $method,
                'query' => $queryOut === [] ? (object) [] : $queryOut,
            ],
        ];
    }

    /**
     * @param mixed $value
     */
    private static function scalarString($value, string $default = ''): string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return $default;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return $default;
        }

        return trim((string) $value);
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
        $text = self::scalarString($motor['text'] ?? '');
        if ($text !== '') {
            return $text;
        }

        return self::scalarString($motor['explanation'] ?? '');
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
