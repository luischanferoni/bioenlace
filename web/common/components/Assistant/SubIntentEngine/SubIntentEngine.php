<?php

namespace common\components\Assistant\SubIntentEngine;

use common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext;
use common\components\Assistant\Service\AssistantDraftNormalizer;
use common\components\Assistant\FlowManifest\FlowManifest;
use common\components\Assistant\Service\FlowHintService;
use common\components\Assistant\IntentEngine\UiActionCatalog;
use common\components\Assistant\UiActions\AssistantClientOpenEnricher;
use common\components\Ui\ApiV1HttpRoute;
use common\components\Ui\UiDefinitionTemplateManager;
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
        $draft = AssistantDraftNormalizer::normalize(self::mergeFlowSnapshotIntoDraft($snapshot, $draft));
        $content = isset($snapshot['content']) ? trim((string) $snapshot['content']) : '';
        $interaction = isset($snapshot['interaction']) && is_array($snapshot['interaction']) ? $snapshot['interaction'] : null;

        $hints = isset($snapshot['hints']) && is_array($snapshot['hints']) ? $snapshot['hints'] : [];
        if ($hints === []) {
            $hints = FlowHintService::resolveForIntent(
                $intentId,
                ChatPreprocessContext::extractions(),
                $userId,
                $draft
            );
        }

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

        $flowSubmitBlock = isset($intent['flow_submit']) && is_array($intent['flow_submit']) ? $intent['flow_submit'] : null;

        // Avanzar mientras el paso actual esté completo. Detenerse en el primer paso pendiente
        // (`missing != []`) o cuando se llegue al cierre del flow.
        //
        // Esto cubre dos escenarios: (1) recorrido lineal normal, donde cada paso tiene draft
        // vacío al llegar (el loop se detiene en la primera iteración con `missing`); y (2)
        // rebobinado del cliente (Cambio 1: cambiar la elección de un list anterior), donde el
        // cliente reenvía el draft sin `subintent_id` y el motor debe saltar los pasos cuya
        // selección ya esté presente hasta llegar al primer paso pendiente.
        //
        // Guard contra YAMLs mal formados (loop en `next_routing`).
        $visited = [];
        $maxHops = max(8, count($subintents) + 2);
        $hops = 0;
        while ($hops++ < $maxHops) {
            if (isset($visited[$currentId])) {
                // Loop en el YAML: corta y trata como cierre.
                break;
            }
            $visited[$currentId] = true;

            $missing = self::missingDraftFields($current, $draft);
            if ($missing !== []) {
                $open = self::resolveOpenUiForSubintent($current, $content, $draft);
                if ($open && !empty($open['action_id'])) {
                    return self::buildOpenUiResponse(
                        $intentId,
                        $currentId,
                        $current,
                        self::assistantTextForPrompt($current, 'Necesito un dato más para continuar.'),
                        $userId,
                        $open,
                        $content,
                        $flowSubmitBlock,
                        $hints
                    );
                }
                // Paso pendiente sin `open_ui` resoluble: pedir más info y cortar.
                return self::withFlowManifest(self::attachHints([
                    'success' => true,
                    'text' => 'Necesito más información para continuar.',
                    'intent_id' => $intentId,
                    'subintent_id' => $currentId,
                    'required_draft_fields' => $missing,
                    'draft_delta' => (object) [],
                ], $hints), $intentId, $currentId);
            }

            // Paso completo: si es terminal y tiene UI (p. ej. detalle tras elegir ítem), mostrarla.
            $nextId = self::resolveNextSubintentId($current, $draft);
            $openWhenComplete = self::resolveOpenUiForSubintent($current, $content, $draft);
            if ($nextId === '') {
                if ($openWhenComplete && !empty($openWhenComplete['action_id'])) {
                    return self::buildOpenUiResponse(
                        $intentId,
                        $currentId,
                        $current,
                        self::assistantTextForPrompt($current, 'Listo.'),
                        $userId,
                        $openWhenComplete,
                        $content,
                        $flowSubmitBlock,
                        $hints
                    );
                }
                break;
            }
            $nextSub = self::findSubintent($subintents, $nextId);
            if (!is_array($nextSub)) {
                break;
            }
            $current = $nextSub;
            $currentId = (string) $current['id'];
        }

        // Salida del loop: el flow no tiene más pasos por delante (o el "siguiente" es un stub
        // sin `open_ui` ni `next`). Cierre por `flow_submit` si está declarado.
        if ($flowSubmitBlock !== null && self::flowSubmitHasActionId($flowSubmitBlock)) {
            return self::buildTerminalSubmitOnlyResponse(
                $intentId,
                $currentId,
                self::assistantTextForPrompt($current, 'Confirmemos y enviemos.'),
                $flowSubmitBlock,
                $hints
            );
        }

        return self::withFlowManifest(self::attachHints([
            'success' => true,
            'text' => self::assistantTextForPrompt($current, 'Listo.'),
            'intent_id' => $intentId,
            'subintent_id' => $currentId,
            'draft_delta' => (object) [],
        ], $hints), $intentId, $currentId);
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
            'hints' => true,
            'action_id' => true,
        ];
        foreach ($snapshot as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $key = trim($k);
            if ($key === '' || isset($reserved[$key])) {
                continue;
            }
            if (AssistantDraftNormalizer::asOptionalString($draft[$key] ?? null) !== null) {
                continue;
            }
            $scalar = AssistantDraftNormalizer::asOptionalString($v);
            if ($scalar === null) {
                continue;
            }
            $draft[$key] = $scalar;
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
        return AssistantDraftNormalizer::asOptionalString($v) !== null;
    }

    private static function flowSubmitHasActionId(array $flowSubmitBlock): bool
    {
        return trim((string) ($flowSubmitBlock['action_id'] ?? '')) !== '';
    }

    /**
     * Un subintent es "terminal" si después de él el flow ya no espera otro paso interactivo:
     * no declara `next` ni `next_routing`, y el intent expone `flow_submit` con `action_id`.
     *
     * El cálculo es **declarativo** (no depende del draft del usuario) para no marcar terminal
     * un paso cuyo `next_routing` aún no se puede resolver. Si un YAML quiere "rama de cierre"
     * dentro de routing, debe modelar el cierre como un subintent sin `next` y dejar que el
     * motor caiga acá.
     *
     * @param array<string, mixed> $subintent
     * @param array<string, mixed>|null $flowSubmitBlock
     */
    private static function isTerminalSubintent(array $subintent, ?array $flowSubmitBlock): bool
    {
        if ($flowSubmitBlock === null || !self::flowSubmitHasActionId($flowSubmitBlock)) {
            return false;
        }
        if (!empty($subintent['terminal_without_submit'])) {
            return false;
        }
        $hasNext = isset($subintent['next']) && trim((string) $subintent['next']) !== '';
        $hasRouting = isset($subintent['next_routing'])
            && is_array($subintent['next_routing'])
            && $subintent['next_routing'] !== [];

        return !$hasNext && !$hasRouting;
    }

    /**
     * `flow_submit` template para el cliente: el cliente resuelve los placeholders `draft.x`
     * con su `_draft` local al apretar el botón "Confirmar y enviar".
     *
     * @param array<string, mixed> $flowSubmitBlock
     * @return array{action_id: string, route: string, method: string, body_template: array<string, string>}|null
     */
    private static function buildFlowSubmitTemplate(array $flowSubmitBlock): ?array
    {
        $actionId = trim((string) ($flowSubmitBlock['action_id'] ?? ''));
        if ($actionId === '') {
            return null;
        }
        $route = self::apiRouteForActionId($actionId);
        if ($route === '') {
            return null;
        }
        $paramsMap = isset($flowSubmitBlock['params']) && is_array($flowSubmitBlock['params'])
            ? $flowSubmitBlock['params']
            : [];
        $template = [];
        foreach ($paramsMap as $k => $v) {
            $key = is_string($k) ? trim($k) : '';
            if ($key === '') {
                continue;
            }
            $vv = is_string($v) ? trim($v) : '';
            if ($vv === '' || strncmp($vv, 'draft.', 6) !== 0) {
                continue;
            }
            $template[$key] = $vv;
        }
        if ($template === [] && $paramsMap !== []) {
            return null;
        }

        return [
            'action_id' => $actionId,
            'route' => $route,
            'method' => 'POST',
            'body_template' => $template,
        ];
    }

    /**
     * Cierre de flow sin `open_ui` previo: el cliente sólo muestra el botón "Confirmar y enviar".
     *
     * @param array<string, mixed> $flowSubmitBlock
     * @return array<string, mixed>
     */
    /**
     * @param list<array<string, mixed>> $hints
     */
    private static function buildTerminalSubmitOnlyResponse(
        string $intentId,
        string $subintentId,
        string $text,
        array $flowSubmitBlock,
        array $hints = []
    ): array {
        $template = self::buildFlowSubmitTemplate($flowSubmitBlock);
        $payload = [
            'success' => true,
            'text' => $text,
            'intent_id' => $intentId,
            'subintent_id' => $subintentId,
            'draft_delta' => (object) [],
        ];
        if ($template !== null) {
            $payload['flow_submit'] = $template;
        }

        return self::withFlowManifest(self::attachHints($payload, $hints), $intentId, $subintentId);
    }

    /**
     * Extrae los nombres "limpios" de campos declarados en `provides` (sin el prefijo `draft.`).
     *
     * @param mixed $provides
     * @return list<string>
     */
    private static function extractDraftKeys($provides): array
    {
        if (!is_array($provides)) {
            return [];
        }
        $out = [];
        foreach ($provides as $p) {
            $p = is_string($p) ? trim($p) : '';
            if ($p === '' || strncmp($p, 'draft.', 6) !== 0) {
                continue;
            }
            $field = substr($p, 6);
            if ($field !== '') {
                $out[] = $field;
            }
        }

        return array_values(array_unique($out));
    }

    private static function apiRouteForActionId(string $actionId): string
    {
        $actionId = trim($actionId);
        if (preg_match('#^clinical\.([\w-]+)\.([\w-]+)$#', $actionId, $m) === 1) {
            return '/api/v1/clinical/'
                . rawurlencode((string) $m[1]) . '/'
                . rawurlencode((string) $m[2]);
        }
        if (preg_match('#^data-access\.(info|listar)$#', $actionId, $m) === 1) {
            return '/api/v1/' . (string) $m[1];
        }
        if (preg_match('#^([\w-]+)\.([\w-]+)$#', $actionId, $m) !== 1) {
            return '';
        }

        return '/api/v1/' . rawurlencode((string) $m[1]) . '/' . rawurlencode((string) $m[2]);
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
     * Arma la respuesta del motor para emitir un `open_ui`.
     *
     * Enriquecimientos del envelope:
     * - `provides`: lista de campos del draft que este subintent producirá. El cliente la usa
     *   para limpiar el draft al "rebobinar" un paso anterior (Cambio 1: editar list ya elegido).
     * - `flow_submit`: si el subintent es **terminal** (ver `isTerminalSubintent`), se adjunta el
     *   template del cierre con `route`, `method` y `body_template` (Cambio 2: botón
     *   "Confirmar y enviar" integrado en el último paso). El cliente NO debe re-postear este
     *   paso al motor: el tap en el último `kind: list` sólo mergea local, y el submit lo dispara
     *   el botón "Confirmar y enviar" del propio paso, POSTeando directo a `flow_submit.route`.
     *
     * @param array<string, mixed> $subintent
     * @param array<string, mixed> $openUiDef
     * @param array<string, mixed>|null $flowSubmitBlock
     */
    /**
     * @param list<array<string, mixed>> $hints
     */
    private static function buildOpenUiResponse(
        string $intentId,
        string $subintentId,
        array $subintent,
        string $text,
        int $userId,
        array $openUiDef,
        string $flowUserContent,
        ?array $flowSubmitBlock,
        array $hints = []
    ): array {
        $actionId = (string) ($openUiDef['action_id'] ?? '');
        $open = self::resolveClientOpen($actionId, $userId);

        // Parametrización declarativa: query del mini-UI desde open_ui.params.
        // YAML: { query_key: "draft.campo" } o literal { step: raiz }.
        $paramsMap = isset($openUiDef['params']) && is_array($openUiDef['params']) ? $openUiDef['params'] : null;
        if ($paramsMap && isset($open['client_open']) && is_array($open['client_open'])) {
            $co = $open['client_open'];
            if (isset($co['api']) && is_array($co['api'])) {
                $draft = isset($openUiDef['__draft']) && is_array($openUiDef['__draft']) ? $openUiDef['__draft'] : [];
                $api = $co['api'];
                $query = isset($api['query']) && is_array($api['query']) ? $api['query'] : [];
                foreach ($paramsMap as $k => $v) {
                    $key = is_string($k) ? trim($k) : '';
                    if ($key === '') {
                        continue;
                    }
                    $vv = is_string($v) ? trim($v) : '';
                    if ($vv === '') {
                        continue;
                    }
                    if (strncmp($vv, 'draft.', 6) === 0) {
                        $field = substr($vv, 6);
                        if ($field === '') {
                            continue;
                        }
                        if (!isset($draft[$field]) || $draft[$field] === null || $draft[$field] === '') {
                            continue;
                        }
                        $scalar = AssistantDraftNormalizer::asOptionalString($draft[$field]);
                        if ($scalar === null) {
                            continue;
                        }
                        $query[$key] = $scalar;
                    } elseif (strncmp($vv, 'client.', 7) !== 0) {
                        $query[$key] = $vv;
                    }
                }
                if ($query !== []) {
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

        self::mergeHintQueryForSubintent($open, $subintent, $hints);

        $payload = [
            'success' => true,
            'text' => $text,
            'intent_id' => $intentId,
            'subintent_id' => $subintentId,
            'open_ui' => $open,
            'provides' => self::extractDraftKeys($subintent['provides'] ?? []),
            'draft_delta' => (object) [],
        ];

        if (self::isTerminalSubintent($subintent, $flowSubmitBlock)) {
            $template = self::buildFlowSubmitTemplate($flowSubmitBlock);
            if ($template !== null) {
                $payload['flow_submit'] = $template;
            }
        }

        $dismiss = self::buildFlowDismissDescriptor($subintent);
        if ($dismiss !== null) {
            $payload['flow_dismiss'] = $dismiss;
        }

        return self::withFlowManifest($payload, $intentId, $subintentId);
    }

    /**
     * Cierre informativo (sin POST): p. ej. derivación a urgencia con banda A.
     *
     * @param array<string, mixed> $subintent
     * @return array{label: string, actions: list<array{label: string, href: string, variant: string}>}|null
     */
    private static function buildFlowDismissDescriptor(array $subintent): ?array
    {
        if (empty($subintent['terminal_without_submit'])) {
            return null;
        }
        $cfg = isset($subintent['flow_dismiss']) && is_array($subintent['flow_dismiss'])
            ? $subintent['flow_dismiss']
            : [];
        $label = trim((string) ($cfg['label'] ?? 'Entendido'));
        if ($label === '') {
            $label = 'Entendido';
        }
        $actions = [];
        $rawActions = isset($subintent['flow_actions']) && is_array($subintent['flow_actions'])
            ? $subintent['flow_actions']
            : [];
        foreach ($rawActions as $row) {
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

        return [
            'label' => $label,
            'actions' => $actions,
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
        // Intents YAML con open_ui al mismo action_id (p. ej. data-access.info): mini-UI HTTP, no re-iniciar flow.
        $dataAccessOpen = \common\components\Assistant\Catalog\DataAccessUiActionCatalog::clientOpenForActionId($actionId);
        if ($dataAccessOpen !== null) {
            return [
                'action_id' => $actionId,
                'client_open' => $dataAccessOpen,
            ];
        }

        $catalog = UiActionCatalog::forUser($userId);
        $item = $catalog->byActionId[$actionId] ?? null;
        if ($item === null) {
            $route = self::apiRouteForActionId($actionId);
            if ($route === '' && preg_match('#^([\w-]+)\.([\w-]+)$#', $actionId, $m) === 1) {
                $route = '/api/v1/' . rawurlencode((string) $m[1]) . '/' . rawurlencode((string) $m[2]);
            }
            $clientOpen = $route !== '' ? self::clientOpenFromHttpRoute($route, $actionId) : null;

            return [
                'action_id' => $actionId,
                'client_open' => $clientOpen,
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
        $clientOpen = $action['client_open'] ?? null;
        if (!is_array($clientOpen) || trim((string) ($clientOpen['kind'] ?? '')) === '') {
            $clientOpen = self::clientOpenFromHttpRoute($item->route, $actionId);
        }

        return [
            'action_id' => $actionId,
            'client_open' => $clientOpen,
        ];
    }

    /**
     * Arma `client_open` para un paso de flow a partir de la ruta HTTP del descriptor UI.
     *
     * @return array<string, mixed>|null
     */
    private static function clientOpenFromHttpRoute(string $route, string $actionId): ?array
    {
        $route = ApiV1HttpRoute::normalize($route);
        if ($route === '' || AssistantClientOpenEnricher::isPostOnlyFlowClosureRoute($route)) {
            return null;
        }

        $action = [
            'action_id' => $actionId,
            'display_name' => $actionId,
            'description' => '',
            'entity' => null,
            'route' => $route,
            'parameters' => ['expected' => [], 'provided' => []],
        ];
        $enriched = AssistantClientOpenEnricher::enrich($action);
        $clientOpen = $enriched['client_open'] ?? null;
        if (is_array($clientOpen) && trim((string) ($clientOpen['kind'] ?? '')) !== '') {
            return $clientOpen;
        }

        if (!UiDefinitionTemplateManager::hasTemplateForApiRoute($route)) {
            return null;
        }

        return [
            'kind' => 'ui_json',
            'api' => [
                'route' => $route,
                'method' => 'GET|POST',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $hints
     * @return array<string, mixed>
     */
    private static function attachHints(array $payload, array $hints): array
    {
        if ($hints !== []) {
            $payload['hints'] = array_values($hints);
        }

        return $payload;
    }

    /**
     * Si el paso tiene `hint.entity` y ya hay match, lo pasa a la query del GET (id + filtro `q`).
     *
     * @param array<string, mixed> $open
     * @param array<string, mixed> $subintent
     * @param list<array<string, mixed>> $hints
     */
    private static function mergeHintQueryForSubintent(array &$open, array $subintent, array $hints): void
    {
        if ($hints === [] || !isset($open['client_open']) || !is_array($open['client_open'])) {
            return;
        }
        $hintCfg = isset($subintent['hint']) && is_array($subintent['hint']) ? $subintent['hint'] : null;
        if ($hintCfg === null) {
            return;
        }
        $entity = trim((string) ($hintCfg['entity'] ?? ''));
        if ($entity === '') {
            return;
        }
        $h = FlowHintService::findHintForEntity($hints, $entity);
        if ($h === null) {
            return;
        }
        $field = trim((string) ($h['draft_field'] ?? ''));
        $id = trim((string) ($h['id'] ?? ''));
        if ($field === '' || $id === '') {
            return;
        }
        $co = $open['client_open'];
        if (!isset($co['api']) || !is_array($co['api'])) {
            return;
        }
        $api = $co['api'];
        $query = isset($api['query']) && is_array($api['query']) ? $api['query'] : [];
        $query[$field] = $id;
        $value = trim((string) ($h['value'] ?? ''));
        if ($value !== '') {
            $query['q'] = $value;
        }
        $api['query'] = $query;
        $co['api'] = $api;
        $open['client_open'] = $co;
    }
}

