<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Platform\Ai\IAManager;
use common\components\Domain\Content\Application\Service\InfoContentResolverService;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannel;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusResolver;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideHistoryWindow;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuidePromptAssembler;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadContext;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\IntentEngine\IntentEngine;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\CatalogCtaResolver;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutionResult;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;
use Yii;

/**
 * Canal guide: 2ª IA unificada (salud, producto, datos HIS).
 */
final class GuideChannel
{
  /**
   * @return array<string, mixed>
   */
  public static function handle(string $content, int $userId, ?string $formattedHistory = null): array
  {
    $content = trim($content);
    if (IntentEngine::isListAllQueryPublic($content)) {
      return self::finalizeMotor(IntentEngine::processQuery($content, $userId, null));
    }

    if (ChatChannelPolicy::isCapabilityMenuQuery($content)) {
      return AmbiguousChannel::handle();
    }

    if ($content === '') {
      return AmbiguousChannel::handle();
    }

    return self::handleWithGuideIa($content, $userId, $formattedHistory);
  }

  /**
   * 2ª IA para routing incompletas (plan declarativo + CTAs de catálogo).
   *
   * @param array<string, mixed> $firstIa
   * @return array<string, mixed>|null
   */
  public static function handleIncomplete(
    array $firstIa,
    DeclarativePlanExecutionResult $execution,
    SmartCatalogRoutingEvaluation $evaluation,
    string $content,
    int $userId
  ): ?array {
    $prompt = GuidePromptAssembler::buildForIncomplete(
      $firstIa,
      $content,
      $userId,
      $execution->scopedSystemRecords,
      $execution->articleBlock,
      $evaluation
    );

    $allowedButtons = CatalogCtaResolver::resolveAll($evaluation, $userId);
    $interpreted = self::interpretGuideIa(self::consultGuideIaRaw($prompt), $allowedButtons);
    $text = $interpreted['text'];
    $ctaButtons = $interpreted['buttons'];

    if ($text === '' && $ctaButtons === []) {
      return null;
    }
    if ($text === '') {
      $text = self::ctaFallbackText($ctaButtons);
    }

    if ($ctaButtons === []) {
      return AssistantContextAssemblyService::attachDebugIfEnabled(
        AssistantEnvelope::message($text)
      );
    }

    return AssistantContextAssemblyService::attachDebugIfEnabled(
      AssistantEnvelope::interactive($text, $ctaButtons)
    );
  }

  /**
   * @param list<array{label: string, intent_id: string}> $ctaButtons
   */
  private static function ctaFallbackText(array $ctaButtons): string
  {
    if (count($ctaButtons) === 1) {
      $label = $ctaButtons[0]['label'];

      return 'Para continuar, podés usar la opción «' . $label . '».';
    }

    $parts = [];
    foreach ($ctaButtons as $b) {
      $parts[] = '«' . $b['label'] . '»';
    }

    return 'Para continuar, elegí una de estas opciones: ' . implode(' o ', $parts) . '.';
  }

  private static function consultGuideIaRaw(string $prompt): ?string
  {
    AssistantPlanningLogService::setGuidePrompt($prompt);

    try {
      $raw = IAManager::consultarIA($prompt, 'asistente-guide', 'text-generation');
      if (is_string($raw) && trim($raw) !== '') {
        return trim($raw);
      }
      if (is_array($raw) && isset($raw['text'])) {
        $text = trim((string) $raw['text']);

        return $text !== '' ? $text : null;
      }
    } catch (\Throwable $e) {
      Yii::warning('GuideChannel: ' . $e->getMessage(), 'asistente');
    }

    return null;
  }

  /**
   * Interpreta la salida Guide: JSON con mensaje/botones, o texto plano (fallback).
   *
   * Con JSON válido, los botones los elige la IA (intersección con los ofrecidos).
   * Sin JSON, se conservan todos los CTA ofrecidos (compat).
   *
   * @param list<array{label: string, intent_id: string}> $allowedButtons
   * @return array{text: string, buttons: list<array{label: string, intent_id: string, params?: array<string, mixed>}>, parsed: bool}
   */
  public static function interpretGuideIa(?string $raw, array $allowedButtons): array
  {
    $parsed = GuideIaResponseParser::parse($raw);
    if ($parsed === null) {
      if ($raw !== null && trim($raw) !== '') {
        Yii::info(['guide_ia_json_parse_failed' => true], 'asistente-planning');
      }
      $text = $raw !== null && $raw !== '' ? self::plainTextFromIa($raw) : '';

      return [
        'text' => $text,
        'buttons' => $allowedButtons,
        'parsed' => false,
      ];
    }

    $text = self::plainTextFromIa($parsed['mensaje']);

    return [
      'text' => $text,
      'buttons' => self::filterSelectedButtons($parsed['botones'], $allowedButtons),
      'parsed' => true,
    ];
  }

  /**
   * @param list<array{intent_id: string, params: array<string, mixed>}> $selected
   * @param list<array{label: string, intent_id: string}> $allowed
   * @return list<array{label: string, intent_id: string, params?: array<string, mixed>}>
   */
  private static function filterSelectedButtons(array $selected, array $allowed): array
  {
    $byId = [];
    foreach ($allowed as $row) {
      $intentId = trim((string) ($row['intent_id'] ?? ''));
      if ($intentId === '') {
        continue;
      }
      $byId[$intentId] = $row;
    }

    $out = [];
    $seen = [];
    foreach ($selected as $row) {
      $intentId = trim((string) ($row['intent_id'] ?? ''));
      if ($intentId === '' || !isset($byId[$intentId]) || isset($seen[$intentId])) {
        continue;
      }
      $seen[$intentId] = true;
      $button = $byId[$intentId];
      $params = is_array($row['params'] ?? null) ? $row['params'] : [];
      if ($params !== []) {
        $button['params'] = $params;
      }
      $out[] = $button;
    }

    return $out;
  }

  /**
   * Saca marcas de markdown que la 2ª IA a veces agrega. Deja el texto.
   */
  public static function plainTextFromIa(string $text): string
  {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\*\*(.+?)\*\*/su', '$1', $text) ?? $text;
    $text = preg_replace('/__(.+?)__/su', '$1', $text) ?? $text;
    $text = preg_replace('/(?<!\w)\*([^*\n]+)\*(?!\w)/u', '$1', $text) ?? $text;
    $text = preg_replace('/`([^`]+)`/u', '$1', $text) ?? $text;
    $text = str_replace(['*', '#', '`'], '', $text);

    $lines = preg_split("/\n/u", $text) ?: [];
    $out = [];
    foreach ($lines as $line) {
      $line = preg_replace('/^\s*(?:[-•]|\d+[.)])\s+/u', '', (string) $line) ?? (string) $line;
      $line = trim($line);
      if ($line !== '') {
        $out[] = $line;
      }
    }

    return trim(implode("\n", $out));
  }

  public static function buildPrompt(
    string $content,
    int $userId,
    ?string $formattedHistory = null,
    ?string $articleData = null
  ): string {
    return GuidePromptAssembler::build(
      $content,
      $userId,
      self::focusState(),
      $formattedHistory,
      $articleData
    );
  }

  private static function focusState(): GuideFocusState
  {
    $raw = AssistantThreadContext::guideFocus();
    if ($raw !== null) {
      $fromContext = GuideFocusState::fromMetadataArray($raw);
      if ($fromContext !== null) {
        return $fromContext;
      }
    }

    return GuideFocusResolver::resolve(
      ChatPreprocessContext::contextAreas(),
      null,
      false
    );
  }

  public static function formatPreprocessFactsLines(): string
  {
    $normalized = ChatPreprocessContext::normalizedText();
    $actionText = ChatPreprocessContext::actionText();
    $extractions = ChatPreprocessContext::extractions();

    $lines = [];
    if ($actionText !== '' && $actionText !== $normalized) {
      $lines[] = '- Acción mencionada: ' . $actionText;
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

    return $lines === [] ? '' : implode("\n", $lines);
  }

  /**
   * @param array{label?: string, intent_id?: string, summary?: string}|null $offer
   */
  public static function formatCtaDetailsForPrompt(?array $offer, bool $continuingConversation = false): string
  {
    if ($offer === null) {
      return '';
    }

    $label = trim((string) ($offer['label'] ?? ''));
    $summary = trim((string) ($offer['summary'] ?? ''));

    $lines = [];
    if ($label !== '') {
      $lines[] = '- Botón: "' . $label . '"';
    }
    if ($summary !== '') {
      $lines[] = '- Qué hace: ' . $summary;
    }

    if ($continuingConversation) {
      $lines[] = '- Conversación en curso: mención breve al botón si ya se ofreció.';
    }

    return $lines === [] ? '' : implode("\n", $lines);
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
   * @return array<string, mixed>
   */
  private static function handleWithGuideIa(string $content, int $userId, ?string $formattedHistory = null): array
  {
    $history = $formattedHistory ?? GuideHistoryWindow::formatForPrompt(
      $userId,
      $content,
      self::focusState()->primaryArea
    );
    $patientHistory = GuideHistoryWindow::extractPatientLines($history);
    $offer = ChatChannelPolicy::shouldOfferBookingButton(
      $content,
      $patientHistory,
      \common\components\Platform\Assistant\Chat\Thread\AssistantThreadContext::offerCta()
    )
      ? self::resolveBookingOffer($userId)
      : null;
    $origin = self::bookingOfferOriginContent($content, $patientHistory);

    $articleData = self::resolveArticlePromptData($content, $userId);
    $prompt = self::buildPrompt($content, $userId, $history, $articleData);
    $allowedButtons = $offer !== null
      ? [['label' => $offer['label'], 'intent_id' => $offer['intent_id']]]
      : [];
    $interpreted = self::interpretGuideIa(self::consultGuideIaRaw($prompt), $allowedButtons);
    $text = $interpreted['text'];
    if ($text === '') {
      return self::iaFailureEnvelope();
    }

    $selectedOffer = null;
    foreach ($interpreted['buttons'] as $button) {
      if (($button['intent_id'] ?? '') === ($offer['intent_id'] ?? '')) {
        $selectedOffer = $offer;
        break;
      }
    }
    // JSON vacío de botones: no forzar CTA. Sin JSON (fallback): conservar offer.
    if (!$interpreted['parsed']) {
      $selectedOffer = $offer;
    }

    return self::finalizeResponse($text, $selectedOffer, $origin);
  }

  /**
   * @return array{success: false, error: string}
   */
  public static function iaFailureEnvelope(): array
  {
    return [
      'success' => false,
      'error' => 'No pudimos generar una respuesta en este momento. Probá de nuevo en unos segundos.',
    ];
  }

  /**
   * @param array{label: string, intent_id: string, summary: string}|null $offer
   * @return array<string, mixed>
   */
  private static function finalizeResponse(string $text, ?array $offer, string $originContent = ''): array
  {
    if ($offer === null) {
      return AssistantContextAssemblyService::attachDebugIfEnabled(
        AssistantEnvelope::message($text)
      );
    }

    $button = [
      'label' => $offer['label'],
      'intent_id' => $offer['intent_id'],
    ];
    $origin = trim($originContent);
    if ($origin !== '') {
      $button['content'] = $origin;
    }

    return AssistantContextAssemblyService::attachDebugIfEnabled(
      AssistantEnvelope::interactive($text, [$button])
    );
  }

  /**
   * @param array<string, mixed> $motor
   * @return array<string, mixed>
   */
  private static function finalizeMotor(array $motor): array
  {
    if (empty($motor['success'])) {
      return $motor;
    }

    return AssistantEnvelope::fromMotorResponse($motor);
  }

  private static function currentIdEfector(): ?int
  {
    try {
      $id = Yii::$app->user->getIdEfector();

      return $id > 0 ? (int) $id : null;
    } catch (\Throwable $e) {
      return null;
    }
  }

  private static function resolveArticlePromptData(string $content, int $userId): string
  {
    if ($userId <= 0) {
      return '';
    }

    $article = InfoContentResolverService::resolveByText(
      $content,
      self::currentIdEfector(),
      null,
      $userId
    );
    if ($article === null || !InfoContentResolverService::isVisibleToUser($article, $userId)) {
      return '';
    }

    return GuideChannelConfig::formatArticleContent(
      (string) $article->title,
      (string) $article->body
    );
  }

  /**
   * @return array{label: string, intent_id: string, summary: string}|null
   */
  private static function resolveBookingOffer(int $userId): ?array
  {
    $catalog = UiActionCatalog::forUser($userId);
    foreach (GuideChannelConfig::bookingOfferIntentPriority() as $intentId) {
      $item = $catalog->byActionId[$intentId] ?? null;
      if ($item instanceof UiActionCatalogItem) {
        return self::offerFromCatalogItem($item);
      }
    }

    $prefix = GuideChannelConfig::bookingOfferIntentPrefixFallback();
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
   * @return array{label: string, intent_id: string, summary: string}
   */
  private static function offerFromCatalogItem(UiActionCatalogItem $item): array
  {
    $label = $item->display_name !== '' ? $item->display_name : $item->action_id;
    $sem = is_array($item->intent_semantics) ? $item->intent_semantics : [];

    return [
      'label' => $label,
      'intent_id' => $item->action_id,
      'summary' => trim((string) ($sem['objective'] ?? '')),
    ];
  }
}
