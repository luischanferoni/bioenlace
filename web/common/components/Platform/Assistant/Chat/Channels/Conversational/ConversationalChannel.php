<?php

namespace common\components\Platform\Assistant\Chat\Channels\Conversational;

use common\components\Ai\IAManager;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Assistant\Chat\Conversational\ConversationalChannelProviderRegistry;
use Yii;

/**
 * Canal conversacional: copy en {@see ChatConversationalConfig}; cuándo ofrecer botón en {@see ChatChannelPolicy}.
 */
final class ConversationalChannel
{
    public static function stablePromptPrefix(): string
    {
        return ChatConversationalConfig::stablePrompt();
    }

    /**
     * @param array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null $offer
     */
    public static function buildPrompt(string $content, int $userId, ?array $offer = null, ?string $formattedHistory = null): string
    {
        $content = trim($content);
        $parts = [rtrim(self::stablePromptPrefix())];

        $idPersona = (int) Yii::$app->user->getIdPersona();
        ConversationalChannelProviderRegistry::appendPatientContext($idPersona, $parts);

        $facts = self::formatPreprocessFacts();
        if ($facts !== '') {
            $parts[] = '';
            $parts[] = $facts;
        }

        $history = $formattedHistory ?? ConversationalHistoryWindow::formatForPrompt($userId, $content);
        $continuing = $history !== '';
        if ($continuing) {
            $parts[] = '';
            $parts[] = ChatConversationalConfig::promptFragment(
                'conversation_header',
                'Conversación previa (más antigua → más reciente):'
            );
            $parts[] = $history;
            $parts[] = ChatConversationalConfig::promptFragment(
                'continuation_hint',
                'Continuación: respondé al mensaje actual.'
            );
        }

        $messageForPrompt = ChatPreprocessContext::normalizedText();
        if ($messageForPrompt === '') {
            $messageForPrompt = $content;
        }

        $parts[] = '';
        $parts[] = ChatConversationalConfig::promptFragment(
            'current_message_header',
            'Mensaje actual del paciente:'
        );
        $parts[] = $messageForPrompt;

        $offerBlock = self::formatOfferForPrompt($offer, $continuing);
        if ($offerBlock !== '') {
            $parts[] = '';
            $parts[] = $offerBlock;
        }

        return implode("\n", $parts);
    }

    /**
     * Hechos ya interpretados (sin narrar enrutamiento). Vacío si no hay preprocess útil.
     * El texto normalizado va en «Mensaje actual»; acá solo acción/menciones adicionales.
     */
    public static function formatPreprocessFacts(): string
    {
        $normalized = ChatPreprocessContext::normalizedText();
        $actionText = ChatPreprocessContext::actionText();
        $extractions = ChatPreprocessContext::extractions();

        $lines = [];
        if ($actionText !== '' && $actionText !== $normalized) {
            $line = ChatConversationalConfig::formatPromptFragment(
                'facts_action_line',
                ['action' => $actionText],
                'Acción mencionada: {action}'
            );
            if ($line !== '') {
                $lines[] = '- ' . $line;
            }
        }
        foreach ($extractions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $span = isset($row['span']) ? trim((string) $row['span']) : '';
            $category = isset($row['category']) ? trim((string) $row['category']) : '';
            if ($span === '') {
                continue;
            }
            $lines[] = $category !== ''
                ? '- ' . $category . ': ' . $span
                : '- ' . $span;
        }

        if ($lines === []) {
            return '';
        }

        $header = ChatConversationalConfig::promptFragment('facts_header', 'Hechos:');

        return $header . "\n" . implode("\n", $lines);
    }

    /**
     * @param array{label?: string, intent_id?: string, summary?: string, capabilities?: list<string>}|null $offer
     */
    public static function formatOfferForPrompt(?array $offer, bool $continuingConversation = false): string
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

        $lines = [
            ChatConversationalConfig::promptFragment(
                'offer.header',
                'Oferta disponible (botón en la respuesta; alineá el texto con esto):'
            ),
        ];
        if ($label !== '') {
            $buttonLine = ChatConversationalConfig::formatPromptFragment(
                'offer.button_line',
                ['label' => $label],
                'Botón: "{label}"'
            );
            if ($buttonLine !== '') {
                $lines[] = '- ' . $buttonLine;
            }
        }
        if ($intentId !== '') {
            $intentLine = ChatConversationalConfig::formatPromptFragment(
                'offer.intent_id_line',
                ['intent_id' => $intentId],
                'intent_id: {intent_id}'
            );
            if ($intentLine !== '') {
                $lines[] = '- ' . $intentLine;
            }
        }
        if ($summary !== '') {
            $summaryLine = ChatConversationalConfig::formatPromptFragment(
                'offer.summary_line',
                ['summary' => $summary],
                'Qué hace: {summary}'
            );
            if ($summaryLine !== '') {
                $lines[] = '- ' . $summaryLine;
            }
        }

        $capLines = self::formatCapabilityLines($capabilities);
        if ($capLines !== []) {
            $capHeader = ChatConversationalConfig::promptFragment(
                'offer.capabilities_header',
                'Capacidades (solo podés mencionar estas):'
            );
            $lines[] = '- ' . $capHeader;
            foreach ($capLines as $capLine) {
                $lines[] = '  - ' . $capLine;
            }
        } elseif ($summary === '') {
            $lines[] = '- ' . ChatConversationalConfig::promptFragment(
                'offer.capabilities_missing',
                'Capacidades: no declaradas; no prometas pasos del flow.'
            );
        }

        if ($continuingConversation) {
            $lines[] = '- ' . ChatConversationalConfig::promptFragment(
                'offer.continuing_line',
                'Conversación en curso: mención breve al botón si ya se ofreció.'
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public static function handle(string $content, int $userId, ?string $formattedHistory = null): array
    {
        $content = trim($content);
        if ($content === '') {
            return AssistantEnvelope::message('');
        }

        $history = $formattedHistory ?? ConversationalHistoryWindow::formatForPrompt($userId, $content);
        $patientHistory = ConversationalHistoryWindow::extractPatientLines($history);
        // CTA: certeza del hilo clinical (fase 04) o síntoma en mensaje / hilo activo.
        $offer = ChatChannelPolicy::shouldOfferBookingButton(
            $content,
            $patientHistory,
            \common\components\Platform\Assistant\Chat\Thread\AssistantThreadContext::offerCta()
        )
            ? self::resolveBookingOffer($userId)
            : null;
        $origin = self::bookingOfferOriginContent($content, $patientHistory);

        $prompt = self::buildPrompt($content, $userId, $offer, $history);

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
            $text = ChatConversationalConfig::emptyResponseFallback();
        }

        return self::finalizeResponse($text, $offer, $origin);
    }

    public static function bookingOfferOriginContent(string $content, string $patientHistory = ''): string
    {
        $content = trim($content);
        if (ChatChannelPolicy::isClinicalSymptomContent($content)) {
            return $content;
        }
        // Solo historial del hilo clinical activo (ya filtrado por ConversationalHistoryWindow).
        $fromHistory = ChatChannelPolicy::lastLineMatchingClinicalSymptom($patientHistory);

        return $fromHistory !== '' ? $fromHistory : $content;
    }

    /**
     * @param array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null $offer
     * @return array<string, mixed>
     */
    private static function finalizeResponse(string $text, ?array $offer, string $originContent = ''): array
    {
        if ($offer === null) {
            return AssistantEnvelope::message($text);
        }

        $button = [
            'label' => $offer['label'],
            'intent_id' => $offer['intent_id'],
        ];
        $origin = trim($originContent);
        if ($origin !== '') {
            $button['content'] = $origin;
        }

        return AssistantEnvelope::interactive($text, [$button]);
    }

    /**
     * @return array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null
     */
    private static function resolveBookingOffer(int $userId): ?array
    {
        $catalog = UiActionCatalog::forUser($userId);
        foreach (ChatConversationalConfig::bookingOfferIntentPriority() as $intentId) {
            $item = $catalog->byActionId[$intentId] ?? null;
            if ($item instanceof UiActionCatalogItem) {
                return self::offerFromCatalogItem($item);
            }
        }

        $prefix = ChatConversationalConfig::bookingOfferIntentPrefixFallback();
        if ($prefix === '') {
            return null;
        }

        foreach ($catalog->items as $item) {
            if (str_starts_with($item->action_id, $prefix)) {
                return self::offerFromCatalogItem($item);
            }
        }

        return null;
    }

    /**
     * @return array{label: string, intent_id: string, summary: string, capabilities: list<string>}
     */
    private static function offerFromCatalogItem(UiActionCatalogItem $item): array
    {
        $label = $item->display_name !== '' ? $item->display_name : $item->action_id;
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
        $labelMap = ChatConversationalConfig::capabilityLabels();
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
