<?php

namespace common\components\Platform\Assistant\Chat\Channels\Conversational;

use common\components\Platform\Ai\IAManager;
use common\components\Platform\Assistant\Chat\Conversational\ConversationalChannelProviderRegistry;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use Yii;

/**
 * Canal conversacional: preprocess + respuesta automática con ventana acotada de historial.
 *
 * Prompt y reglas de booking: metadata ({@see IntentClassificationRulesService::conversationalChannelConfig()}).
 * Contexto de paciente: providers en {@see ConversationalChannelProviderRegistry}.
 */
final class ConversationalChannel
{
    public static function stablePromptPrefix(): string
    {
        $cfg = IntentClassificationRulesService::conversationalChannelConfig();
        $prompt = trim((string) ($cfg['stable_prompt'] ?? ''));

        return $prompt !== '' ? $prompt : 'Respondé en español, breve y amable.';
    }

    public static function buildPrompt(string $content, int $userId): string
    {
        $content = trim($content);
        $parts = [rtrim(self::stablePromptPrefix())];

        $idPersona = (int) Yii::$app->user->getIdPersona();
        ConversationalChannelProviderRegistry::appendPatientContext($idPersona, $parts);

        $history = ConversationalHistoryWindow::formatForPrompt($userId, $content);
        if ($history !== '') {
            $parts[] = '';
            $parts[] = 'Historial reciente (del más antiguo al más reciente):';
            $parts[] = $history;
        }

        $parts[] = '';
        $parts[] = 'Mensaje actual del paciente:';
        $parts[] = $content;

        return implode("\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public static function handle(string $content, int $userId): array
    {
        $content = trim($content);
        if ($content === '') {
            return AssistantEnvelope::message('');
        }

        $prompt = self::buildPrompt($content, $userId);

        $text = null;
        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-conversational', 'text-generation');
            if (is_string($raw) && trim($raw) !== '') {
                $text = trim($raw);
            } elseif (is_array($raw) && isset($raw['text'])) {
                $text = trim((string) $raw['text']);
            }
        } catch (\Throwable $e) {
            Yii::warning('ConversationalChannel: ' . $e->getMessage(), 'asistente');
        }

        if ($text === null || $text === '') {
            $cfg = IntentClassificationRulesService::conversationalChannelConfig();
            $text = trim((string) ($cfg['empty_response_fallback'] ?? ''));
            if ($text === '') {
                $text = 'Entiendo tu consulta.';
            }
        }

        return self::finalizeResponse($content, $userId, $text);
    }

    /**
     * @return array<string, mixed>
     */
    private static function finalizeResponse(string $content, int $userId, string $text): array
    {
        if (!self::shouldOfferBookingButton($content)) {
            return AssistantEnvelope::message($text);
        }

        $button = self::resolveBookingButton($userId);
        if ($button === null) {
            return AssistantEnvelope::message($text);
        }

        return AssistantEnvelope::interactive($text, [$button]);
    }

    private static function shouldOfferBookingButton(string $content): bool
    {
        $cfg = IntentClassificationRulesService::conversationalChannelConfig();
        $buttonCfg = $cfg['booking_button'] ?? [];
        if (!is_array($buttonCfg)) {
            return false;
        }
        $whenRule = trim((string) ($buttonCfg['when_rule'] ?? ''));

        return $whenRule !== '' && IntentClassificationRulesService::ruleMatches($whenRule, $content);
    }

    /**
     * @return array{label: string, intent_id: string}|null
     */
    private static function resolveBookingButton(int $userId): ?array
    {
        $cfg = IntentClassificationRulesService::conversationalChannelConfig();
        $buttonCfg = $cfg['booking_button'] ?? [];
        if (!is_array($buttonCfg)) {
            return null;
        }

        $labels = $buttonCfg['labels'] ?? [];
        if (!is_array($labels)) {
            $labels = [];
        }

        $catalog = UiActionCatalog::forUser($userId);
        foreach ($buttonCfg['intent_priority'] ?? [] as $intentId) {
            if (!is_string($intentId) || trim($intentId) === '') {
                continue;
            }
            $intentId = trim($intentId);
            $item = $catalog->byActionId[$intentId] ?? null;
            if ($item === null) {
                continue;
            }

            $fallbackLabel = trim((string) ($labels[$intentId] ?? ''));

            return [
                'label' => $item->display_name !== '' ? $item->display_name : ($fallbackLabel !== '' ? $fallbackLabel : $intentId),
                'intent_id' => $intentId,
            ];
        }

        $prefix = trim((string) ($buttonCfg['intent_prefix_fallback'] ?? ''));
        if ($prefix === '') {
            return null;
        }

        foreach ($catalog->items as $item) {
            if (str_starts_with($item->action_id, $prefix)) {
                return [
                    'label' => $item->display_name !== '' ? $item->display_name : $item->action_id,
                    'intent_id' => $item->action_id,
                ];
            }
        }

        return null;
    }
}
