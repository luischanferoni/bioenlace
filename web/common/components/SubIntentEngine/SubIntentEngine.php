<?php

namespace common\components\SubIntentEngine;

use common\components\IntentEngine\UiActionCatalog;
use common\components\Actions\AssistantClientOpenEnricher;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * SubIntentEngine: motor conversacional dentro de un intent_id (stateless).
 *
 * Consume YAML bajo `common/components/SubIntentEngine/schemas/`.
 *
 * Nota: este motor NO implementa IA aquí; expone `prompt_context` para que el caller
 * (cuando integre LLM) sepa qué adjuntar al prompt.
 */
final class SubIntentEngine
{
    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function process(array $snapshot, int $userId): array
    {
        $intentId = isset($snapshot['intent_id']) ? trim((string) $snapshot['intent_id']) : '';
        if ($intentId === '' && isset($snapshot['flow_key'])) {
            $intentId = trim((string) $snapshot['flow_key']);
        }
        if ($intentId === '') {
            return ['success' => false, 'error' => 'Se requiere intent_id'];
        }

        $subintentId = isset($snapshot['subintent_id']) ? trim((string) $snapshot['subintent_id']) : '';
        $draft = isset($snapshot['draft']) && is_array($snapshot['draft']) ? $snapshot['draft'] : [];
        $content = isset($snapshot['content']) ? trim((string) $snapshot['content']) : '';
        $interaction = isset($snapshot['interaction']) && is_array($snapshot['interaction']) ? $snapshot['interaction'] : null;

        $intent = self::loadIntentYaml($intentId);
        if ($intent === null) {
            return ['success' => false, 'error' => 'Intent no soportado', 'intent_id' => $intentId];
        }

        $subintents = isset($intent['subintents']) && is_array($intent['subintents']) ? $intent['subintents'] : [];
        if ($subintents === []) {
            return ['success' => false, 'error' => 'Intent sin subintents', 'intent_id' => $intentId];
        }

        $current = $subintentId !== '' ? self::findSubintent($subintents, $subintentId) : $subintents[0];
        if (!is_array($current) || empty($current['id'])) {
            return ['success' => false, 'error' => 'subintent_id inválido', 'intent_id' => $intentId];
        }
        $currentId = (string) $current['id'];

        // Confirmación tipada: si llega, aplicar draft_delta (confirm) o no (cancel).
        if ($interaction && isset($interaction['kind']) && $interaction['kind'] === 'confirm_selection') {
            return self::handleConfirmSelection($intentId, $currentId, $draft, $interaction, $userId);
        }

        // Determinar qué falta según requires.
        $missing = self::missingDraftFields($current, $draft);
        if ($missing !== []) {
            // Abrir mini-UI del subintent actual (si está declarada).
            $open = self::resolveOpenUiForSubintent($current, $content, $draft);
            if ($open && !empty($open['action_id'])) {
                return self::buildOpenUiResponse(
                    $intentId,
                    $currentId,
                    (string) $open['action_id'],
                    self::assistantTextForPrompt($current, 'Necesito un dato más para continuar.'),
                    $userId,
                    $open
                );
            }
            return [
                'success' => true,
                'text' => 'Necesito más información para continuar.',
                'intent_id' => $intentId,
                'subintent_id' => $currentId,
                'required_draft_fields' => $missing,
                'draft_delta' => (object) [],
            ];
        }

        // Si el subintent provee algo y ya está en draft, avanzar al siguiente.
        $next = isset($current['next']) ? trim((string) $current['next']) : '';
        if ($next !== '') {
            $nextSub = self::findSubintent($subintents, $next);
            if (is_array($nextSub)) {
                $open = self::resolveOpenUiForSubintent($nextSub, $content, $draft);
                if ($open && !empty($open['action_id'])) {
                    return self::buildOpenUiResponse(
                        $intentId,
                        (string) $nextSub['id'],
                        (string) $open['action_id'],
                        self::assistantTextForPrompt($nextSub, 'Perfecto, sigamos con el siguiente paso.'),
                        $userId,
                        $open
                    );
                }
            }
        }

        // Submit final (negocio) si existe.
        if (isset($current['submit']['action_id'])) {
            $submitActionId = trim((string) $current['submit']['action_id']);
            if ($submitActionId !== '') {
                return [
                    'success' => true,
                    'text' => 'Confirmemos y enviemos.',
                    'intent_id' => $intentId,
                    'subintent_id' => $currentId,
                    'open_ui' => self::resolveClientOpen($submitActionId, $userId),
                    'draft_delta' => (object) [],
                ];
            }
        }

        return [
            'success' => true,
            'text' => 'Listo.',
            'intent_id' => $intentId,
            'subintent_id' => $currentId,
            'draft_delta' => (object) [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadIntentYaml(string $intentId)
    {
        $base = dirname(__DIR__) . '/SubIntentEngine/schemas/intents';
        $path = $base . '/' . $intentId . '.yaml';
        if (!is_file($path)) {
            return null;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::error('YAML inválido intent ' . $intentId . ': ' . $e->getMessage(), 'subintent_engine');
            return null;
        }
        return is_array($data) ? $data : null;
    }

    /**
     * @param list<mixed> $subintents
     * @return array<string, mixed>|null
     */
    private static function findSubintent(array $subintents, string $id)
    {
        foreach ($subintents as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (isset($s['id']) && (string) $s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $subintent
     * @param array<string, mixed> $draft
     * @return list<string>
     */
    private static function missingDraftFields(array $subintent, array $draft): array
    {
        $requires = isset($subintent['requires']) && is_array($subintent['requires']) ? $subintent['requires'] : [];
        $missing = [];
        foreach ($requires as $r) {
            $k = trim((string) $r);
            if ($k === '') {
                continue;
            }
            // require format: draft.<field>
            if (strncmp($k, 'draft.', 6) === 0) {
                $field = substr($k, 6);
                if ($field === '') {
                    continue;
                }
                if (!isset($draft[$field]) || $draft[$field] === null || $draft[$field] === '') {
                    $missing[] = $k;
                }
            }
        }
        return $missing;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $interaction
     * @return array<string, mixed>
     */
    private static function handleConfirmSelection(string $intentId, string $subintentId, array $draft, array $interaction, int $userId): array
    {
        $decision = isset($interaction['decision']) ? (string) $interaction['decision'] : '';
        $selection = isset($interaction['selection']) && is_array($interaction['selection']) ? $interaction['selection'] : [];

        if ($decision !== 'confirm') {
            return [
                'success' => true,
                'text' => 'Ok, cambiemos la selección.',
                'intent_id' => $intentId,
                'subintent_id' => $subintentId,
                'draft_delta' => (object) [],
            ];
        }

        // MVP: el cliente envía selection.id + selection.draft_delta opcional.
        $draftDelta = isset($selection['draft_delta']) && is_array($selection['draft_delta']) ? $selection['draft_delta'] : [];
        if ($draftDelta === []) {
            // Compat mínima: si solo hay id, no sabemos qué campo; no aplicamos.
            $draftDelta = [];
        }

        return [
            'success' => true,
            'text' => 'Perfecto.',
            'intent_id' => $intentId,
            'subintent_id' => $subintentId,
            'draft_delta' => $draftDelta === [] ? (object) [] : $draftDelta,
        ];
    }

    /**
     * @param array<string, mixed> $openUiDef
     */
    private static function buildOpenUiResponse(string $intentId, string $subintentId, string $actionId, string $text, int $userId, array $openUiDef = []): array
    {
        $open = self::resolveClientOpen($actionId, $userId);

        // Parametrización declarativa: mapear draft -> query params del open_ui.
        // YAML: open_ui.params: { id_servicio: "draft.id_servicio_asignado" }
        $paramsMap = isset($openUiDef['params']) && is_array($openUiDef['params']) ? $openUiDef['params'] : null;
        if ($paramsMap && isset($open['client_open']) && is_array($open['client_open'])) {
            $co = $open['client_open'];
            if (isset($co['api']) && is_array($co['api'])) {
                $draft = isset($openUiDef['__draft']) && is_array($openUiDef['__draft']) ? $openUiDef['__draft'] : [];
                $query = [];
                foreach ($paramsMap as $k => $v) {
                    $key = is_string($k) ? trim($k) : '';
                    if ($key === '') {
                        continue;
                    }
                    $vv = is_string($v) ? trim($v) : '';
                    if ($vv === '' || strncmp($vv, 'draft.', 6) !== 0) {
                        continue;
                    }
                    $field = substr($vv, 6);
                    if ($field === '') {
                        continue;
                    }
                    if (!isset($draft[$field]) || $draft[$field] === null || $draft[$field] === '') {
                        continue;
                    }
                    $query[$key] = (string) $draft[$field];
                }
                if ($query !== []) {
                    $api = $co['api'];
                    $api['query'] = $query;
                    $co['api'] = $api;
                    $open['client_open'] = $co;
                }
            }
        }

        return [
            'success' => true,
            'text' => $text,
            'intent_id' => $intentId,
            'subintent_id' => $subintentId,
            'open_ui' => $open,
            'draft_delta' => (object) [],
        ];
    }

    /**
     * Resuelve `open_ui` considerando ramas declarativas (p.ej. “cerca” vs listado normal).
     *
     * @param array<string, mixed> $subintent
     * @return array<string, mixed>|null
     */
    private static function resolveOpenUiForSubintent(array $subintent, string $content, array $draft): ?array
    {
        $direct = isset($subintent['open_ui']) && is_array($subintent['open_ui']) ? $subintent['open_ui'] : null;
        $chooser = isset($subintent['chooser']) && is_array($subintent['chooser']) ? $subintent['chooser'] : null;
        if ($chooser === null) {
            if (is_array($direct)) {
                $direct['__draft'] = $draft;
            }
            return $direct;
        }

        $near = isset($chooser['when_user_says_nearby']) && is_array($chooser['when_user_says_nearby'])
            ? $chooser['when_user_says_nearby']
            : null;
        $otherwise = isset($chooser['otherwise']) && is_array($chooser['otherwise']) ? $chooser['otherwise'] : null;

        if ($near !== null && self::userWantsNearby($content)) {
            $open = isset($near['open_ui']) && is_array($near['open_ui']) ? $near['open_ui'] : null;
            if ($open && !empty($open['action_id'])) {
                $open['__draft'] = $draft;
                return $open;
            }
        }

        if ($otherwise !== null) {
            $open = isset($otherwise['open_ui']) && is_array($otherwise['open_ui']) ? $otherwise['open_ui'] : null;
            if ($open && !empty($open['action_id'])) {
                $open['__draft'] = $draft;
                return $open;
            }
        }

        if (is_array($direct)) {
            $direct['__draft'] = $draft;
        }
        return $direct;
    }

    private static function userWantsNearby(string $content): bool
    {
        $s = mb_strtolower(trim($content), 'UTF-8');
        if ($s === '') {
            return false;
        }

        // Heurística MVP (español): “cerca”, “cercano”, “cercanos”, “cercanía”, “cercania”.
        return preg_match('/\b(cerca|cercanos|cercano|cercanas|cercana|cercanía|cercania)\b/u', $s) === 1;
    }

    /**
     * Texto corto para el chat. Preferimos metadata YAML (`assistant_text`) para evitar hardcode por pantalla.
     *
     * @param array<string, mixed> $subintent
     */
    private static function assistantTextForPrompt(array $subintent, string $fallback): string
    {
        $t = isset($subintent['assistant_text']) ? trim((string) $subintent['assistant_text']) : '';
        if ($t !== '') {
            return $t;
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveClientOpen(string $actionId, int $userId): array
    {
        $catalog = UiActionCatalog::forUser($userId);
        $item = $catalog->byActionId[$actionId] ?? null;
        if ($item === null) {
            return [
                'action_id' => $actionId,
                'client_open' => null,
            ];
        }
        $action = [
            'action_id' => $item->action_id,
            'display_name' => $item->display_name,
            'description' => $item->description,
            'entity' => $item->entity,
            'route' => $item->route,
            'parameters' => $item->parameters,
        ];
        if ($item->client_open !== null) {
            $action['client_open'] = $item->client_open;
        }
        $action = AssistantClientOpenEnricher::enrich($action);

        return [
            'action_id' => $actionId,
            'client_open' => $action['client_open'] ?? null,
        ];
    }
}

