<?php

namespace common\components\Platform\Assistant\Qa;

use common\components\Platform\Assistant\Chat\ChatOrchestrator;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadContext;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadStateService;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Smoke de consultas paciente vía {@see ChatOrchestrator} (IA real, sin simulación de costos).
 *
 * @see web/docs/qa/paciente/asistente-consultas.md
 * @see web/common/data/qa/asistente-consultas.yaml
 */
final class AsistenteConsultasQaService
{
    private const CATALOG_ALIAS = '@common/data/qa/asistente-consultas.yaml';

    /** Coberturas que fallan el batch si un assert no pasa. */
    private const HARD_COVERAGES = ['Hoy', 'Fuera'];

    /**
     * @return array{version: int|string|null, source_doc: string, cases: list<array<string, mixed>>}
     */
    public static function loadCatalog(): array
    {
        $path = Yii::getAlias(self::CATALOG_ALIAS);
        if (!is_file($path)) {
            throw new \RuntimeException('Catálogo QA no encontrado: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Catálogo QA YAML inválido.');
        }

        $cases = $data['cases'] ?? [];
        if (!is_array($cases)) {
            $cases = [];
        }

        $normalized = [];
        foreach ($cases as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $mensajes = $row['mensajes'] ?? [];
            if (!is_array($mensajes) || $mensajes === []) {
                continue;
            }
            $normalized[] = [
                'id' => $id,
                'seccion' => trim((string) ($row['seccion'] ?? '')),
                'tipo' => trim((string) ($row['tipo'] ?? '')),
                'cobertura' => trim((string) ($row['cobertura'] ?? 'Hoy')),
                'mensajes' => array_values(array_filter(array_map(
                    static fn ($m) => is_string($m) ? trim($m) : '',
                    $mensajes
                ), static fn (string $m): bool => $m !== '')),
                'expect' => is_array($row['expect'] ?? null) ? $row['expect'] : [],
            ];
        }

        return [
            'version' => $data['version'] ?? null,
            'source_doc' => (string) ($data['source_doc'] ?? ''),
            'cases' => $normalized,
        ];
    }

    /**
     * @param list<string>|null $coberturas null = todas
     * @return list<array<string, mixed>>
     */
    public static function filterCases(
        ?array $coberturas = null,
        ?string $seccion = null,
        ?string $caseId = null,
        ?int $limit = null
    ): array {
        $cases = self::loadCatalog()['cases'];
        $out = [];
        foreach ($cases as $case) {
            if ($caseId !== null && $caseId !== '' && $case['id'] !== $caseId) {
                continue;
            }
            if ($seccion !== null && $seccion !== '' && ($case['seccion'] ?? '') !== $seccion) {
                continue;
            }
            if ($coberturas !== null && $coberturas !== []) {
                if (!in_array((string) ($case['cobertura'] ?? ''), $coberturas, true)) {
                    continue;
                }
            }
            $out[] = $case;
            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $cases
     * @return array{
     *   started_at: string,
     *   finished_at: string,
     *   user_id: int,
     *   report_path: string,
     *   summary: array{total: int, pass: int, fail: int, observe: int, error: int},
     *   results: list<array<string, mixed>>
     * }
     */
    public static function run(array $cases, int $userId, ?string $reportPath = null): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('userId debe ser un usuario paciente válido (> 0).');
        }

        $startedAt = date('c');
        $results = [];
        $summary = [
            'total' => 0,
            'pass' => 0,
            'fail' => 0,
            'observe' => 0,
            'error' => 0,
        ];

        foreach ($cases as $case) {
            $summary['total']++;
            $result = self::runCase($case, $userId);
            $results[] = $result;
            $status = (string) ($result['status'] ?? 'error');
            if (isset($summary[$status])) {
                $summary[$status]++;
            } else {
                $summary['error']++;
            }

            Yii::info([
                'qa_asistente_case' => $result,
            ], 'qa-asistente-consultas');
        }

        $finishedAt = date('c');
        $path = $reportPath ?? self::defaultReportPath();
        $payload = [
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'user_id' => $userId,
            'report_path' => $path,
            'summary' => $summary,
            'results' => $results,
        ];
        self::writeReport($path, $payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    public static function runCase(array $case, int $userId): array
    {
        $id = (string) ($case['id'] ?? '');
        $cobertura = (string) ($case['cobertura'] ?? 'Hoy');
        $hard = in_array($cobertura, self::HARD_COVERAGES, true);
        $mensajes = is_array($case['mensajes'] ?? null) ? $case['mensajes'] : [];
        $expect = is_array($case['expect'] ?? null) ? $case['expect'] : [];

        AssistantThreadStateService::clearPersistedStateForUser($userId);
        ChatPreprocessContext::clear();
        AssistantPlanningLogService::resetForTests();
        AssistantThreadContext::clear();

        $detalle = [];
        $lastObservation = null;

        try {
            foreach ($mensajes as $indice => $mensaje) {
                if (!is_string($mensaje) || trim($mensaje) === '') {
                    continue;
                }
                ChatPreprocessContext::clear();
                AssistantPlanningLogService::resetForTests();

                $out = ChatOrchestrator::handle(['content' => trim($mensaje)], $userId);
                $observation = self::observeEnvelope($out);
                $lastObservation = $observation;
                $detalle[] = [
                    'indice' => (int) $indice,
                    'mensaje' => trim($mensaje),
                    'observation' => $observation,
                ];
            }
        } catch (\Throwable $e) {
            return [
                'id' => $id,
                'seccion' => $case['seccion'] ?? '',
                'tipo' => $case['tipo'] ?? '',
                'cobertura' => $cobertura,
                'status' => 'error',
                'failures' => [$e->getMessage()],
                'detalle' => $detalle,
            ];
        }

        if ($lastObservation === null) {
            return [
                'id' => $id,
                'seccion' => $case['seccion'] ?? '',
                'tipo' => $case['tipo'] ?? '',
                'cobertura' => $cobertura,
                'status' => 'error',
                'failures' => ['El caso no tiene mensajes ejecutables.'],
                'detalle' => $detalle,
            ];
        }

        $failures = self::evaluateExpect($expect, $lastObservation);
        if ($failures === []) {
            $status = $hard ? 'pass' : 'observe';
        } else {
            $status = $hard ? 'fail' : 'observe';
        }

        return [
            'id' => $id,
            'seccion' => $case['seccion'] ?? '',
            'tipo' => $case['tipo'] ?? '',
            'cobertura' => $cobertura,
            'status' => $status,
            'failures' => $failures,
            'last' => $lastObservation,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public static function observeEnvelope(array $envelope): array
    {
        $intentRefs = self::collectIntentRefs($envelope);
        $text = ChatOrchestrator::botReplyTextForPersistence($envelope);
        $planning = AssistantPlanningLogService::snapshot();

        return [
            'success' => (bool) ($envelope['success'] ?? ($envelope['kind'] ?? '') !== ''),
            'kind' => AssistantDraftNormalizer::scalarString($envelope['kind'] ?? ''),
            'user_goal' => ChatPreprocessContext::userGoal(),
            'routing_hint' => ChatPreprocessContext::routingHint(),
            'normalized_text' => ChatPreprocessContext::normalizedText(),
            'tags' => ChatPreprocessContext::tags(),
            'context_areas' => ChatPreprocessContext::contextAreas(),
            'intent_ids_hint' => ChatPreprocessContext::intentIdsHint(),
            'thread_tag' => AssistantThreadContext::threadTag(),
            'offer_cta' => AssistantThreadContext::offerCta(),
            'intent_refs' => $intentRefs,
            'flow_intent_id' => self::flowIntentId($envelope),
            'button_intent_ids' => self::buttonIntentIds($envelope),
            'reply_text' => $text,
            'error' => AssistantDraftNormalizer::scalarString($envelope['error'] ?? ''),
            'planning_applied' => $planning,
        ];
    }

    /**
     * @param array<string, mixed> $expect
     * @param array<string, mixed> $observation
     * @return list<string>
     */
    public static function evaluateExpect(array $expect, array $observation): array
    {
        if ($expect === []) {
            return [];
        }

        $failures = [];
        $userGoal = (string) ($observation['user_goal'] ?? '');
        $intentRefs = is_array($observation['intent_refs'] ?? null)
            ? array_map('strval', $observation['intent_refs'])
            : [];
        $buttonIds = is_array($observation['button_intent_ids'] ?? null)
            ? array_map('strval', $observation['button_intent_ids'])
            : [];
        $reply = mb_strtolower((string) ($observation['reply_text'] ?? ''));

        if (isset($expect['user_goal'])) {
            $wanted = trim((string) $expect['user_goal']);
            if ($wanted !== '' && $userGoal !== $wanted) {
                $failures[] = "user_goal esperado '{$wanted}', obtuvo '{$userGoal}'";
            }
        }

        if (isset($expect['user_goals_any']) && is_array($expect['user_goals_any'])) {
            $allowed = array_values(array_filter(array_map(
                static fn ($g) => is_string($g) ? trim($g) : '',
                $expect['user_goals_any']
            )));
            if ($allowed !== [] && !in_array($userGoal, $allowed, true)) {
                $failures[] = 'user_goal no está en [' . implode(', ', $allowed) . "], obtuvo '{$userGoal}'";
            }
        }

        if (isset($expect['intent_id'])) {
            $wanted = trim((string) $expect['intent_id']);
            if ($wanted !== '' && !in_array($wanted, $intentRefs, true)) {
                $failures[] = "intent_id '{$wanted}' no apareció en " . json_encode($intentRefs, JSON_UNESCAPED_UNICODE);
            }
        }

        if (isset($expect['intent_ids_any']) && is_array($expect['intent_ids_any'])) {
            $any = array_values(array_filter(array_map(
                static fn ($g) => is_string($g) ? trim($g) : '',
                $expect['intent_ids_any']
            )));
            $hit = false;
            foreach ($any as $candidate) {
                if (in_array($candidate, $intentRefs, true)) {
                    $hit = true;
                    break;
                }
            }
            if ($any !== [] && !$hit) {
                $failures[] = 'ningún intent de [' . implode(', ', $any) . '] en '
                    . json_encode($intentRefs, JSON_UNESCAPED_UNICODE);
            }
        }

        if (isset($expect['must_not_intent'])) {
            $banned = is_array($expect['must_not_intent'])
                ? $expect['must_not_intent']
                : [$expect['must_not_intent']];
            foreach ($banned as $b) {
                $b = is_string($b) ? trim($b) : '';
                if ($b !== '' && in_array($b, $intentRefs, true)) {
                    $failures[] = "intent prohibido apareció: '{$b}'";
                }
            }
        }

        if (isset($expect['offer_intent'])) {
            $offer = trim((string) $expect['offer_intent']);
            if ($offer !== '' && !in_array($offer, $buttonIds, true) && !in_array($offer, $intentRefs, true)) {
                $failures[] = "offer_intent '{$offer}' no está en buttons/intent_refs";
            }
        }

        if (isset($expect['must_not_offer_intent'])) {
            $bannedOffer = is_array($expect['must_not_offer_intent'])
                ? $expect['must_not_offer_intent']
                : [$expect['must_not_offer_intent']];
            foreach ($bannedOffer as $b) {
                $b = is_string($b) ? trim($b) : '';
                if ($b !== '' && in_array($b, $buttonIds, true)) {
                    $failures[] = "CTA prohibido en buttons: '{$b}'";
                }
            }
        }

        if (isset($expect['reply_must_not_contain']) && is_array($expect['reply_must_not_contain'])) {
            foreach ($expect['reply_must_not_contain'] as $needle) {
                if (!is_string($needle) || trim($needle) === '') {
                    continue;
                }
                $n = mb_strtolower(trim($needle));
                if ($n !== '' && str_contains($reply, $n)) {
                    $failures[] = "respuesta contiene texto prohibido: '{$needle}'";
                }
            }
        }

        return $failures;
    }

    /**
     * @param array<string, mixed> $envelope
     * @return list<string>
     */
    private static function collectIntentRefs(array $envelope): array
    {
        $ids = [];
        $flowId = self::flowIntentId($envelope);
        if ($flowId !== '') {
            $ids[] = $flowId;
        }
        foreach (self::buttonIntentIds($envelope) as $bid) {
            $ids[] = $bid;
        }

        $actions = $envelope['actions'] ?? null;
        if (is_array($actions)) {
            foreach ($actions as $action) {
                if (!is_array($action)) {
                    continue;
                }
                $aid = AssistantDraftNormalizer::scalarString($action['id'] ?? ($action['intent_id'] ?? ''));
                if ($aid !== '') {
                    $ids[] = $aid;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function flowIntentId(array $envelope): string
    {
        $direct = AssistantDraftNormalizer::scalarString($envelope['intent_id'] ?? '');
        if ($direct !== '') {
            return $direct;
        }
        $flow = $envelope['flow'] ?? null;
        if (is_array($flow)) {
            return AssistantDraftNormalizer::scalarString($flow['intent_id'] ?? '');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $envelope
     * @return list<string>
     */
    private static function buttonIntentIds(array $envelope): array
    {
        $buttons = $envelope['buttons'] ?? null;
        if (!is_array($buttons)) {
            return [];
        }
        $ids = [];
        foreach ($buttons as $b) {
            if (!is_array($b)) {
                continue;
            }
            $iid = AssistantDraftNormalizer::scalarString($b['intent_id'] ?? '');
            if ($iid !== '') {
                $ids[] = $iid;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function defaultReportPath(): string
    {
        $dir = Yii::getAlias('@runtime/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'qa-asistente-consultas-' . date('Ymd-His') . '.json';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function writeReport(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('No se pudo serializar el reporte QA.');
        }
        if (file_put_contents($path, $json . "\n") === false) {
            throw new \RuntimeException('No se pudo escribir el reporte QA: ' . $path);
        }
    }
}
