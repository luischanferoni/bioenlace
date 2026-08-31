<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Ai\IAManager;
use common\components\Domain\Content\Service\InfoContentAssistantService;
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

    $infoArticle = InfoContentAssistantService::tryResolveFromText(
      $content,
      $userId,
      self::currentIdEfector(),
      null
    );
    if ($infoArticle !== null) {
      return $infoArticle;
    }

    if (ChatChannelPolicy::isCapabilityMenuQuery($content)) {
      return self::capabilityMenu($userId);
    }

    if ($content === '') {
      return AmbiguousChannel::handle();
    }

    if (
      ChatChannelPolicy::isGreetingOnly($content)
      && ChatPreprocessContext::contextAreas() === []
    ) {
      return AssistantEnvelope::message(
        GuideChannelConfig::message(
          'intro',
          'Soy el asistente de Bioenlace. Contame qué necesitás.'
        )
      );
    }

    return self::handleWithGuideIa($content, $userId, $formattedHistory);
  }

  /**
   * @param array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null $offer
   */
  public static function buildPrompt(
    string $content,
    int $userId,
    ?array $offer = null,
    ?string $formattedHistory = null,
    ?string $articleBlock = null
  ): string {
    return GuidePromptAssembler::build(
      $content,
      $userId,
      self::focusState(),
      $offer,
      $formattedHistory,
      $articleBlock
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

  public static function formatPreprocessFacts(): string
  {
    $normalized = ChatPreprocessContext::normalizedText();
    $actionText = ChatPreprocessContext::actionText();
    $extractions = ChatPreprocessContext::extractions();

    $lines = [];
    if ($actionText !== '' && $actionText !== $normalized) {
      $line = GuideChannelConfig::formatPromptFragment(
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

    $header = GuideChannelConfig::promptFragment('facts_header', 'Hechos:');

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
    $summary = trim((string) ($offer['summary'] ?? ''));
    $capabilities = $offer['capabilities'] ?? [];
    if (!is_array($capabilities)) {
      $capabilities = [];
    }
    if (count($capabilities) > 4) {
      $capabilities = array_slice($capabilities, 0, 4);
    }

    $lines = [
      GuideChannelConfig::promptFragment(
        'offer.header',
        'Oferta disponible (botón en la respuesta; alineá el texto con esto):'
      ),
    ];
    if ($label !== '') {
      $buttonLine = GuideChannelConfig::formatPromptFragment(
        'offer.button_line',
        ['label' => $label],
        'Botón: "{label}"'
      );
      if ($buttonLine !== '') {
        $lines[] = '- ' . $buttonLine;
      }
    }
    if ($summary !== '') {
      $summaryLine = GuideChannelConfig::formatPromptFragment(
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
      $capHeader = GuideChannelConfig::promptFragment(
        'offer.capabilities_header',
        'Capacidades (solo podés mencionar estas):'
      );
      $lines[] = '- ' . $capHeader;
      foreach ($capLines as $capLine) {
        $lines[] = '  - ' . $capLine;
      }
    } elseif ($summary === '') {
      $lines[] = '- ' . GuideChannelConfig::promptFragment(
        'offer.capabilities_missing',
        'Capacidades: no declaradas; no prometas pasos del flow.'
      );
    }

    if ($continuingConversation) {
      $lines[] = '- ' . GuideChannelConfig::promptFragment(
        'offer.continuing_line',
        'Conversación en curso: mención breve al botón si ya se ofreció.'
      );
    }

    return implode("\n", $lines);
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

    $prompt = self::buildPrompt($content, $userId, $offer, $history);

    $text = null;
    try {
      $raw = IAManager::consultarIA($prompt, 'asistente-guide', 'text-generation');
      if (is_string($raw) && trim($raw) !== '') {
        $text = trim($raw);
      } elseif (is_array($raw) && isset($raw['text'])) {
        $text = trim((string) $raw['text']);
      }
    } catch (\Throwable $e) {
      Yii::warning('GuideChannel: ' . $e->getMessage(), 'asistente');

      return self::iaFailureEnvelope();
    }

    if ($text === null || $text === '') {
      return self::iaFailureEnvelope();
    }

    return self::finalizeResponse($text, $offer, $origin);
  }

  /**
   * @return array{success: false, error: string}
   */
  public static function iaFailureEnvelope(): array
  {
    return [
      'success' => false,
      'error' => GuideChannelConfig::message(
        'ia_failed',
        'No pudimos generar una respuesta en este momento. Probá de nuevo en unos segundos.'
      ),
    ];
  }

  /**
   * @param array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null $offer
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
   * @return array<string, mixed>
   */
  private static function capabilityMenu(int $userId): array
  {
    $catalog = UiActionCatalog::forUser($userId);
    $buttons = [];
    foreach (array_slice($catalog->items, 0, 8) as $it) {
      $buttons[] = [
        'label' => $it->display_name !== '' ? $it->display_name : $it->action_id,
        'intent_id' => $it->action_id,
      ];
    }

    return AssistantEnvelope::interactive(
      GuideChannelConfig::message(
        'capability_menu_intro',
        'Estas son algunas cosas que podés hacer. Elegí una opción o contame qué necesitás.'
      ),
      $buttons
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

  /**
   * @return array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null
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
    $labelMap = GuideChannelConfig::capabilityLabels();
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
