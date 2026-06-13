<?php

namespace common\components\Assistant\EntryPoints\Chat\Channels\Operational;

use common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext;
use common\components\Assistant\EntryPoints\Chat\Preprocess\ChatPreprocessService;
use common\components\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Assistant\IntentEngine\IntentClassifier;
use common\components\Assistant\IntentEngine\IntentEngine;
use common\components\Assistant\IntentEngine\UiActionCatalog;
use common\components\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Assistant\EntryPoints\Chat\Envelope\AssistantEnvelope;
use common\components\Assistant\SubIntentEngine\SubIntentEngine;

/**
 * Canal operativo: preprocess → match por reglas → listado, formulario o flujo guiado (SubIntentEngine).
 */
final class OperationalChannel
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(string $content, ?string $actionId, int $userId): array
    {
        $normalized = ChatPreprocessContext::normalizedText();
        $queryText = $normalized !== '' ? $normalized : trim($content);

        if ($actionId !== null && $actionId !== '') {
            return self::finalize(IntentEngine::processQuery($queryText, $userId, $actionId));
        }

        $catalog = UiActionCatalog::forUser($userId);
        if ($catalog->items === []) {
            return [
                'success' => false,
                'error' => 'No hay UIs disponibles para este usuario.',
                'actions' => [],
            ];
        }

        if ($queryText === '') {
            return [
                'success' => false,
                'error' => 'Se requiere content o action_id.',
                'actions' => [],
            ];
        }

        if (IntentEngine::isListAllQueryPublic($queryText)) {
            return self::finalize(IntentEngine::processQuery($queryText, $userId, null));
        }

        // Reglas declarativas (p. ej. editar agenda staff) antes de top-K e IA.
        if (ChatPreprocessService::isStaffDataAccessOperationalQuery($queryText)) {
            $declarative = IntentClassificationRulesService::resolveOperationalFallback($queryText, $catalog);
            if ($declarative !== null) {
                return self::buildFromClassification($declarative, $queryText, $userId);
            }
        }

        $top = IntentRetrievalIndex::topK($queryText, $catalog, 8);
        $classification = IntentClassifier::classifyAmongItems($queryText, $top, $catalog);

        if ($classification === null && ChatPreprocessService::isStaffDataAccessOperationalQuery($queryText)) {
            $classification = IntentClassifier::classify($queryText, $catalog);
        }

        if ($classification === null) {
            return self::finalize(IntentEngine::processQueryNoMatch($queryText, $catalog));
        }

        return self::buildFromClassification($classification, $queryText, $userId);
    }

    /**
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    private static function finalize(array $motor): array
    {
        if (AssistantEnvelope::isPublicEnvelope($motor)) {
            return $motor;
        }

        if (empty($motor['success'])) {
            return $motor;
        }

        return AssistantEnvelope::fromMotorResponse($motor);
    }

    /**
     * @param array<string, mixed> $classification
     * @return array<string, mixed>
     */
    private static function buildFromClassification(array $classification, string $content, int $userId): array
    {
        $item = $classification['item'];
        if (!$item instanceof UiActionCatalogItem) {
            return self::finalize(IntentEngine::processQuery($content, $userId, null));
        }

        if (isset($classification['disambiguation']) && is_array($classification['disambiguation'])) {
            $d = $classification['disambiguation'];
            $text = isset($d['text']) ? trim((string) $d['text']) : '';
            $rem = isset($d['remediation']) && is_array($d['remediation']) ? $d['remediation'] : [];
            if ($text !== '' && $rem !== []) {
                return self::finalize([
                    'success' => true,
                    'text' => $text,
                    'candidate_intent_id' => $item->action_id,
                    'rule_id' => 'ai_disambiguation',
                    'remediation' => $rem,
                    'match' => self::matchMeta($classification),
                ]);
            }
        }

        return self::finalize(IntentEngine::buildSingleActionResponsePublic(
            $item,
            (string) ($classification['method'] ?? 'unknown'),
            (float) ($classification['confidence'] ?? 0.0),
            $content,
            $userId
        ));
    }

    /**
     * @param array<string, mixed> $classification
     * @return array<string, mixed>
     */
    private static function matchMeta(array $classification): array
    {
        $item = $classification['item'];
        $actionId = $item instanceof UiActionCatalogItem ? $item->action_id : '';

        return [
            'action_id' => $actionId,
            'confidence' => (float) ($classification['confidence'] ?? 0.0),
            'method' => (string) ($classification['method'] ?? 'unknown'),
        ];
    }

}
