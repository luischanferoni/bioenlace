<?php

namespace common\components\Platform\Assistant\Chat\Channels\Conversational;

use common\components\Ai\IAManager;
use common\components\Domain\Content\Service\InfoContentAssistantService;
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

        $history = $formattedHistory ?? ConversationalHistoryWindow::formatForPrompt($userId, $content);
        if ($history !== '') {
            $parts[] = '';
            $parts[] = 'Historial reciente (del más antiguo al más reciente):';
            $parts[] = $history;
        }

        $parts[] = '';
        $parts[] = 'Mensaje actual del paciente:';
        $parts[] = $content;

        $offerBlock = self::formatOfferForPrompt($offer, $history !== '');
        if ($offerBlock !== '') {
            $parts[] = '';
            $parts[] = $offerBlock;
        }

        return implode("\n", $parts);
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

        if ($continuingConversation) {
            $lines[] = 'Hay historial: respondé primero la pregunta o duda del mensaje actual. No reinicies empatía ni reexpliques todo el botón; una mención breve alcanza.';
        } else {
            $lines[] = 'Si el paciente pide algo que no esté en capacidades ni en el resumen, aclará que esa opción no está disponible por ese camino y sugerí describir la necesidad con otras palabras.';
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

        $idEfector = null;
        try {
            $id = Yii::$app->user->getIdEfector();
            $idEfector = $id > 0 ? (int) $id : null;
        } catch (\Throwable $e) {
        }

        $infoArticle = InfoContentAssistantService::tryResolveFromText($content, $userId, $idEfector);
        if ($infoArticle !== null) {
            return $infoArticle;
        }

        $history = $formattedHistory ?? ConversationalHistoryWindow::formatForPrompt($userId, $content);
        $patientHistory = ConversationalHistoryWindow::extractPatientLines($history);
        $offer = ChatChannelPolicy::shouldOfferBookingButton($content, $patientHistory)
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
