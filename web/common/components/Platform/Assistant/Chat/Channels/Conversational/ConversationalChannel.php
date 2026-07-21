<?php

namespace common\components\Platform\Assistant\Chat\Channels\Conversational;

use common\components\Ai\IAManager;
use common\components\Platform\Assistant\Chat\Conversational\ConversationalChannelProviderRegistry;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use Yii;

/**
 * Canal conversacional: preprocess + respuesta automática con ventana acotada de historial.
 *
 * Prompt y reglas de booking: metadata ({@see IntentClassificationRulesService::conversationalChannelConfig()}).
 * Contexto de paciente: providers en {@see ConversationalChannelProviderRegistry}.
 * Oferta de botón: mismo intent resuelto para el prompt (`summary`/`capabilities`) y el envelope.
 */
final class ConversationalChannel
{
    public static function stablePromptPrefix(): string
    {
        $cfg = IntentClassificationRulesService::conversationalChannelConfig();
        $prompt = trim((string) ($cfg['stable_prompt'] ?? ''));

        return $prompt !== '' ? $prompt : 'Respondé en español, breve y amable.';
    }

    /**
     * @param array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null $offer
     */
    public static function buildPrompt(string $content, int $userId, ?array $offer = null): string
    {
        $content = trim($content);
        $parts = [rtrim(self::stablePromptPrefix())];

        $idPersona = (int) Yii::$app->user->getIdPersona();
        ConversationalChannelProviderRegistry::appendPatientContext($idPersona, $parts);

        $offerBlock = self::formatOfferForPrompt($offer);
        if ($offerBlock !== '') {
            $parts[] = '';
            $parts[] = $offerBlock;
        }

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
     * Bloque inyectado al prompt cuando hay oferta de botón (testable sin catálogo).
     *
     * @param array{label?: string, intent_id?: string, summary?: string, capabilities?: list<string>}|null $offer
     */
    public static function formatOfferForPrompt(?array $offer): string
    {
        if ($offer === null) {
            return '';
        }

        $label = trim((string) ($offer['label'] ?? ''));
        $intentId = trim((string) ($offer['intent_id'] ?? ''));
        $summary = trim((string) ($offer['summary'] ?? ''));
        $capabilities = $offer['capabilities'] ?? [];
        if (!is_array($capabilities)) {
            $capabilities = [];
        }

        $lines = ['Oferta disponible en esta respuesta (se mostrará un botón; alineá el texto con esto):'];
        if ($label !== '') {
            $lines[] = '- Botón: "' . $label . '"';
        }
        if ($intentId !== '') {
            $lines[] = '- intent_id: ' . $intentId;
        }
        if ($summary !== '') {
            $lines[] = '- Qué hace: ' . $summary;
        }

        $capLines = self::formatCapabilityLines($capabilities);
        if ($capLines !== []) {
            $lines[] = '- Capacidades (solo podés mencionar estas; no inventes otras):';
            foreach ($capLines as $capLine) {
                $lines[] = '  - ' . $capLine;
            }
        } elseif ($summary === '') {
            $lines[] = '- Capacidades: no declaradas; no prometas mapa, cercanía, servicios concretos ni pasos del flow.';
        }

        $lines[] = 'Si el paciente pide algo que no esté en capacidades ni en el resumen, aclará que esa opción no está disponible por ese camino y sugerí describir la necesidad con otras palabras.';

        return implode("\n", $lines);
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

        $offer = self::shouldOfferBookingButton($content)
            ? self::resolveBookingOffer($userId)
            : null;

        $prompt = self::buildPrompt($content, $userId, $offer);

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

        return self::finalizeResponse($text, $offer);
    }

    /**
     * @param array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null $offer
     * @return array<string, mixed>
     */
    private static function finalizeResponse(string $text, ?array $offer): array
    {
        if ($offer === null) {
            return AssistantEnvelope::message($text);
        }

        return AssistantEnvelope::interactive($text, [
            [
                'label' => $offer['label'],
                'intent_id' => $offer['intent_id'],
            ],
        ]);
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
     * @return array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null
     */
    private static function resolveBookingOffer(int $userId): ?array
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

            return self::offerFromCatalogItem($item, $labels);
        }

        $prefix = trim((string) ($buttonCfg['intent_prefix_fallback'] ?? ''));
        if ($prefix === '') {
            return null;
        }

        foreach ($catalog->items as $item) {
            if (str_starts_with($item->action_id, $prefix)) {
                return self::offerFromCatalogItem($item, $labels);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $labels
     * @return array{label: string, intent_id: string, summary: string, capabilities: list<string>}
     */
    private static function offerFromCatalogItem(UiActionCatalogItem $item, array $labels): array
    {
        $fallbackLabel = trim((string) ($labels[$item->action_id] ?? ''));
        $label = $item->display_name !== ''
            ? $item->display_name
            : ($fallbackLabel !== '' ? $fallbackLabel : $item->action_id);

        $sem = is_array($item->intent_semantics) ? $item->intent_semantics : [];
        $summary = trim((string) ($sem['summary'] ?? ''));
        $capabilities = [];
        foreach ($sem['capabilities'] ?? [] as $cap) {
            if (is_string($cap) && trim($cap) !== '') {
                $capabilities[] = trim($cap);
            }
        }

        return [
            'label' => $label,
            'intent_id' => $item->action_id,
            'summary' => $summary,
            'capabilities' => array_values(array_unique($capabilities)),
        ];
    }

    /**
     * @param list<mixed> $capabilities
     * @return list<string>
     */
    private static function formatCapabilityLines(array $capabilities): array
    {
        $cfg = IntentClassificationRulesService::conversationalChannelConfig();
        $labelMap = $cfg['capability_labels'] ?? [];
        if (!is_array($labelMap)) {
            $labelMap = [];
        }

        $lines = [];
        foreach ($capabilities as $cap) {
            if (!is_string($cap) || trim($cap) === '') {
                continue;
            }
            $id = trim($cap);
            $human = trim((string) ($labelMap[$id] ?? ''));
            $lines[] = $human !== '' ? $id . ': ' . $human : $id;
        }

        return $lines;
    }
}
