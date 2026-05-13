<?php

namespace common\components\Assistant\SubIntentEngine;

use common\components\Assistant\FlowManifest\FlowManifest;
use common\components\Assistant\IntentEngine\UiActionCatalog;
use common\components\Assistant\UiActions\AssistantClientOpenEnricher;
use common\components\Assistant\UiActions\AllowedRoutesResolver;
use common\components\UiDefinitionTemplateManager;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * SubIntentEngine: motor conversacional dentro de un intent_id (stateless).
 *
 * Consume YAML bajo `common/components/Assistant/SubIntentEngine/schemas/`.
 * Contrato de intents (`flow_submit`, subintents): `schemas/SUBINTENT_CONTRACT.md`.
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
        $draft = self::mergeFlowSnapshotIntoDraft($snapshot, $draft);
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
                    $open,
                    $content
                );
            }
            return self::withFlowManifest([
                'success' => true,
                'text' => 'Necesito más información para continuar.',
                'intent_id' => $intentId,
                'subintent_id' => $currentId,
                'required_draft_fields' => $missing,
                'draft_delta' => (object) [],
            ], $intentId, $currentId);
        }

        // Si el subintent provee algo y ya está en draft, avanzar al siguiente.
        $next = self::resolveNextSubintentId($current, $draft);
        $flowSubmitBlock = isset($intent['flow_submit']) && is_array($intent['flow_submit']) ? $intent['flow_submit'] : null;

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
                        $open,
                        $content
                    );
                }
                // Siguiente paso sin open_ui: cierre de rama (p. ej. sin agenda) o paso vacío → flow_submit o Listo.
                $missingNext = self::missingDraftFields($nextSub, $draft);
                $nextNext = isset($nextSub['next']) ? trim((string) $nextSub['next']) : '';
                $hasOpenAction = is_array($open) && !empty($open['action_id']);
                if ($missingNext === [] && !$hasOpenAction && $nextNext === '') {
                    $nextIdStr = (string) ($nextSub['id'] ?? '');
                    if ($flowSubmitBlock !== null && self::flowSubmitHasActionId($flowSubmitBlock)) {
                        return self::emitFlowSubmitPayload($intentId, $nextIdStr, $draft, $userId, $flowSubmitBlock);
                    }

                    return self::withFlowManifest([
                        'success' => true,
                        'text' => self::assistantTextForPrompt($nextSub, 'Listo.'),
                        'intent_id' => $intentId,
                        'subintent_id' => $nextIdStr,
                        'draft_delta' => (object) [],
                    ], $intentId, $nextIdStr);
                }
            }
        }

        if ($flowSubmitBlock !== null && self::flowSubmitHasActionId($flowSubmitBlock)) {
            return self::emitFlowSubmitPayload($intentId, $currentId, $draft, $userId, $flowSubmitBlock);
        }

        return self::withFlowManifest([
            'success' => true,
            'text' => 'Listo.',
            'intent_id' => $intentId,
            'subintent_id' => $currentId,
            'draft_delta' => (object) [],
        ], $intentId, $currentId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function withFlowManifest(array $payload, string $intentId, string $activeSubintentId): array
    {
        if (empty($payload['success'])) {
            return $payload;
        }
        $slice = FlowManifest::buildActiveSliceForSubintent($intentId, $activeSubintentId);
        if ($slice !== null) {
            $payload['flow_manifest'] = $slice;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private static function mergeFlowSnapshotIntoDraft(array $snapshot, array $draft): array
    {
        // Motor agnóstico: mergea valores escalares del snapshot al draft sin conocer claves de dominio.
        // Solo completa claves que no existan aún en draft.
        $reserved = [
            'intent_id' => true,
            'flow_key' => true,
            'subintent_id' => true,
            'draft' => true,
            'content' => true,
            'interaction' => true,
        ];
        foreach ($snapshot as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $key = trim($k);
            if ($key === '' || isset($reserved[$key])) {
                continue;
            }
            if (isset($draft[$key]) && $draft[$key] !== null && trim((string) $draft[$key]) !== '') {
                continue;
            }
            if (!self::scalarNonEmpty($v)) {
                continue;
            }
            $draft[$key] = trim((string) $v);
        }

        return $draft;
    }

    /**
     * Resuelve el siguiente subintent: `next_routing` (primera regla que coincide) o `next`.
     *
     * Regla soportada:
     * - `when.draft_equals`: mapa campo draft (sin prefijo) => valor esperado (string).
     * - `when.default: true`: comodín (convención: declararlo último en el YAML).
     *
     * @param array<string, mixed> $subintent
     * @param array<string, mixed> $draft
     */
    private static function resolveNextSubintentId(array $subintent, array $draft): string
    {
        $routing = isset($subintent['next_routing']) && is_array($subintent['next_routing']) ? $subintent['next_routing'] : null;
        if ($routing !== null) {
            $fallback = '';
            foreach ($routing as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $when = isset($rule['when']) && is_array($rule['when']) ? $rule['when'] : null;
                if ($when === null) {
                    continue;
                }
                if (isset($when['default']) && $when['default'] === true) {
                    $n = isset($rule['next']) ? trim((string) $rule['next']) : '';
                    if ($n !== '') {
                        $fallback = $n;
                    }
                    continue;
                }
                if (isset($when['draft_equals']) && is_array($when['draft_equals'])) {
                    if (self::draftMatchesEquals($draft, $when['draft_equals'])) {
                        $n = isset($rule['next']) ? trim((string) $rule['next']) : '';
                        if ($n !== '') {
                            return $n;
                        }
                    }
                }
            }
            if ($fallback !== '') {
                return $fallback;
            }
        }

        return isset($subintent['next']) ? trim((string) $subintent['next']) : '';
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $expectedMap campo draft => valor esperado
     */
    private static function draftMatchesEquals(array $draft, array $expectedMap): bool
    {
        foreach ($expectedMap as $field => $expected) {
            $k = is_string($field) ? trim($field) : '';
            if ($k === '') {
                continue;
            }
            $dv = isset($draft[$k]) ? trim((string) $draft[$k]) : '';
            $ev = '';
            if (is_string($expected)) {
                $ev = trim($expected);
            } elseif (is_int($expected) || is_float($expected)) {
                $ev = trim((string) $expected);
            } elseif ($expected === true) {
                $ev = '1';
            } elseif ($expected === false) {
                $ev = '0';
            }
            if ($dv !== $ev) {
                return false;
            }
        }

        return true;
    }

    private static function draftFieldNonEmpty(array $draft, string $field): bool
    {
        if (!isset($draft[$field]) || $draft[$field] === null) {
            return false;
        }

        return trim((string) $draft[$field]) !== '';
    }

    private static function scalarNonEmpty($v): bool
    {
        if ($v === null) {
            return false;
        }

        return trim((string) $v) !== '';
    }

    private static function flowSubmitHasActionId(array $flowSubmitBlock): bool
    {
        return trim((string) ($flowSubmitBlock['action_id'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $flowSubmitBlock
     * @return array<string, mixed>
     */
    private static function emitFlowSubmitPayload(string $intentId, string $subintentId, array $draft, int $userId, array $flowSubmitBlock): array
    {
        $submitActionId = trim((string) $flowSubmitBlock['action_id']);
        $openSubmit = self::resolveClientOpen($submitActionId, $userId);
        $openSubmit = self::applyDraftParamsMapToOpenUi($openSubmit, $draft, $flowSubmitBlock);

        $payload = [
            'success' => true,
            'text' => 'Confirmemos y enviemos.',
            'intent_id' => $intentId,
            'subintent_id' => $subintentId,
            'open_ui' => $openSubmit,
            'draft_delta' => (object) [],
        ];
        $inlineSubmit = self::buildFlowSubmitRequestDescriptor($submitActionId, $draft, $flowSubmitBlock);
        if ($inlineSubmit !== null) {
            $payload['flow_submit_request'] = $inlineSubmit;
        }

        return self::withFlowManifest($payload, $intentId, $subintentId);
    }

    /**
     * POST listo para clientes que no abren GET de descriptor (`client_open` null): mismo mapeo `params` que `flow_submit` en YAML.
     *
     * @param array<string, mixed> $flowSubmitBlock
     * @return array{method: string, route: string, body: array<string, string>}|null
     */
    private static function buildFlowSubmitRequestDescriptor(string $submitActionId, array $draft, array $flowSubmitBlock): ?array
    {
        $route = self::apiRouteForActionId($submitActionId);
        if ($route === '') {
            return null;
        }
        $body = self::buildSubmitBodyFromParamsMap($draft, $flowSubmitBlock);
        if ($body === []) {
            return null;
        }

        return [
            'method' => 'POST',
            'route' => $route,
            'body' => $body,
        ];
    }

    private static function apiRouteForActionId(string $actionId): string
    {
        $actionId = trim($actionId);
        if (preg_match('#^([\w-]+)\.([\w-]+)$#', $actionId, $m) !== 1) {
            return '';
        }

        return '/api/v1/' . rawurlencode((string) $m[1]) . '/' . rawurlencode((string) $m[2]);
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed>|null $submitBlock
     * @return array<string, string>
     */
    private static function buildSubmitBodyFromParamsMap(array $draft, ?array $submitBlock): array
    {
        $paramsMap = is_array($submitBlock) && isset($submitBlock['params']) && is_array($submitBlock['params'])
            ? $submitBlock['params']
            : null;
        if ($paramsMap === null || $paramsMap === []) {
            return [];
        }
        $out = [];
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
            if (!isset($draft[$field]) || $draft[$field] === null || trim((string) $draft[$field]) === '') {
                continue;
            }
            $out[$key] = (string) $draft[$field];
        }

        return $out;
    }

    /**
     * Añade query params al `client_open.api` desde `params` de `flow_submit` o `submit` en YAML (valores `draft.*`).
     *
     * @param array<string, mixed> $open
     * @param array<string, mixed> $draft
     * @param array<string, mixed>|null $submitBlock
     * @return array<string, mixed>
     */
    private static function applyDraftParamsMapToOpenUi(array $open, array $draft, ?array $submitBlock): array
    {
        $paramsMap = is_array($submitBlock) && isset($submitBlock['params']) && is_array($submitBlock['params'])
            ? $submitBlock['params']
            : null;
        if ($paramsMap === null || $paramsMap === [] || !isset($open['client_open']) || !is_array($open['client_open'])) {
            return $open;
        }
        $co = $open['client_open'];
        if (!isset($co['api']) || !is_array($co['api'])) {
            return $open;
        }
        $api = $co['api'];
        $query = isset($api['query']) && is_array($api['query']) ? $api['query'] : [];
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
            if (!isset($draft[$field]) || $draft[$field] === null || trim((string) $draft[$field]) === '') {
                continue;
            }
            $query[$key] = (string) $draft[$field];
        }
        $api['query'] = $query;
        $co['api'] = $api;
        $open['client_open'] = $co;

        return $open;
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
        // Para subintents “de selección”, lo que el paso **provee** (draft.*) también es obligatorio
        // para poder considerarlo completo y avanzar.
        $provides = isset($subintent['provides']) && is_array($subintent['provides']) ? $subintent['provides'] : [];
        $needs = array_merge($requires, $provides);
        $missing = [];
        foreach ($needs as $r) {
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
        return array_values(array_unique($missing));
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
            return self::withFlowManifest([
                'success' => true,
                'text' => 'Ok, cambiemos la selección.',
                'intent_id' => $intentId,
                'subintent_id' => $subintentId,
                'draft_delta' => (object) [],
            ], $intentId, $subintentId);
        }

        // `selection.draft_delta` explícito, o inferencia desde `provides` del subintent + `selection.id`.
        $draftDelta = isset($selection['draft_delta']) && is_array($selection['draft_delta']) ? $selection['draft_delta'] : [];
        if ($draftDelta === []) {
            $selId = $selection['id'] ?? null;
            if ($selId !== null && (is_string($selId) || is_int($selId))) {
                $sidStr = trim((string) $selId);
                if ($sidStr !== '') {
                    $intent = self::loadIntentYaml($intentId);
                    $sub = is_array($intent) ? self::findSubintent($intent['subintents'] ?? [], $subintentId) : null;
                    $provides = is_array($sub) && isset($sub['provides']) && is_array($sub['provides']) ? $sub['provides'] : [];
                    foreach ($provides as $p) {
                        $p = is_string($p) ? trim($p) : '';
                        if ($p === '' || strncmp($p, 'draft.', 6) !== 0) {
                            continue;
                        }
                        $field = substr($p, 6);
                        if ($field === '') {
                            continue;
                        }
                        $draftDelta[$field] = $sidStr;
                        break;
                    }
                }
            }
        }

        return self::withFlowManifest([
            'success' => true,
            'text' => 'Perfecto.',
            'intent_id' => $intentId,
            'subintent_id' => $subintentId,
            'draft_delta' => $draftDelta === [] ? (object) [] : $draftDelta,
        ], $intentId, $subintentId);
    }

    /**
     * @param array<string, mixed> $openUiDef
     */
    private static function buildOpenUiResponse(string $intentId, string $subintentId, string $actionId, string $text, int $userId, array $openUiDef = [], string $flowUserContent = ''): array
    {
        $open = self::resolveClientOpen($actionId, $userId);

        // Parametrización declarativa: mapear draft -> query params del open_ui.
        // YAML: open_ui.params: { campo: "draft.campo" }
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

        // YAML opcional: open_ui.pass_content_as_query: q — envía el último texto del usuario como filtro inicial.
        $passContentKey = isset($openUiDef['pass_content_as_query']) ? trim((string) $openUiDef['pass_content_as_query']) : '';
        $flowContent = trim($flowUserContent);
        if ($passContentKey !== '' && $flowContent !== '' && isset($open['client_open']) && is_array($open['client_open'])) {
            $co = $open['client_open'];
            if (isset($co['api']) && is_array($co['api'])) {
                $api = $co['api'];
                $qq = isset($api['query']) && is_array($api['query']) ? $api['query'] : [];
                $qq[$passContentKey] = $flowContent;
                $api['query'] = $qq;
                $co['api'] = $api;
                $open['client_open'] = $co;
            }
        }

        return self::withFlowManifest([
            'success' => true,
            'text' => $text,
            'intent_id' => $intentId,
            'subintent_id' => $subintentId,
            'open_ui' => $open,
            'draft_delta' => (object) [],
        ], $intentId, $subintentId);
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
            // Dentro de un flow, el YAML puede referenciar UIs embebibles que no estén en el catálogo UI global,
            // pero sí existan como templates y estén permitidas por RBAC por ruta.
            // Resolvemos de forma determinística: action_id "entidad.accion" => "/api/v1/entidad/accion".
            $route = null;
            if (preg_match('#^([\w-]+)\.([\w-]+)$#', $actionId, $m) === 1) {
                $route = '/api/v1/' . rawurlencode((string) $m[1]) . '/' . rawurlencode((string) $m[2]);
            }
            if ($route && UiDefinitionTemplateManager::hasTemplateForApiRoute($route)) {
                $map = AllowedRoutesResolver::getTargetRoutesMapForUserId($userId, true);
                if (AllowedRoutesResolver::routeAllowedByMap($route, $map)) {
                    $action = [
                        'action_id' => $actionId,
                        'display_name' => $actionId,
                        'description' => '',
                        'entity' => null,
                        'route' => $route,
                        'parameters' => ['expected' => [], 'provided' => []],
                    ];
                    $action = AssistantClientOpenEnricher::enrich($action);
                    return [
                        'action_id' => $actionId,
                        'client_open' => $action['client_open'] ?? null,
                    ];
                }
            }

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

