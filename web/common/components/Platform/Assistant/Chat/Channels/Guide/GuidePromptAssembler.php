<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use Yii;

/**
 * Ensambla el prompt de la 2ª IA del canal guide (orden cache-friendly).
 */
final class GuidePromptAssembler
{
  /**
   * @param array{label: string, intent_id: string, summary: string, capabilities: list<string>}|null $offer
   */
  public static function build(
    string $content,
    int $userId,
    GuideFocusState $focus,
    ?array $offer = null,
    ?string $formattedHistory = null,
    ?string $articleBlock = null
  ): string {
    $content = trim($content);
    $parts = [rtrim(GuideChannelConfig::stablePrompt())];

    $focusLine = self::formatFocusAreasLine($focus);
    if ($focusLine !== '') {
      $parts[] = '';
      $parts[] = $focusLine;
    }

    $clinicalBlock = self::formatClinicalRecordBlock();
    if ($clinicalBlock !== '') {
      $parts[] = '';
      $parts[] = GuideChannelConfig::promptFragment(
        'block_clinical_record',
        'Historia clínica resumida del paciente autenticado (referencia; no inventar):'
      );
      $parts[] = '--- context:clinical_record ---';
      $parts[] = $clinicalBlock;
      $parts[] = '--- end context:clinical_record ---';
    }

    $hisContext = AssistantContextAssemblyService::assembleForChannel('guide', $userId);
    if (!$hisContext->isEmpty()) {
      $parts[] = '';
      $parts[] = GuideChannelConfig::promptFragment(
        'block_his',
        'Datos del sistema sobre el paciente y el centro para el ámbito indicado:'
      );
      $parts[] = $hisContext->promptSection;
    }

    $catalog = UiActionCatalog::forUser($userId);
    $semantics = GuideIntentSemanticsFilter::formatPromptSection($catalog, $focus->activeAreas);
    if ($semantics !== '') {
      $parts[] = '';
      $parts[] = GuideChannelConfig::promptFragment(
        'block_intent_semantics',
        'Funcionalidades que este usuario puede ejecutar en la app:'
      );
      $parts[] = $semantics;
    }

    if ($articleBlock !== null && trim($articleBlock) !== '') {
      $parts[] = '';
      $parts[] = trim($articleBlock);
    }

    $history = $formattedHistory ?? GuideHistoryWindow::formatForPrompt(
      $userId,
      $content,
      $focus->primaryArea
    );
    $continuing = $history !== '';
    if ($continuing) {
      $parts[] = '';
      $parts[] = GuideChannelConfig::promptFragment(
        'conversation_header',
        'Conversación previa (más antigua → más reciente):'
      );
      $parts[] = $history;
      $parts[] = GuideChannelConfig::promptFragment(
        'continuation_hint',
        'Continuación: respondé al mensaje actual.'
      );
    }

    $facts = GuideChannel::formatPreprocessFacts();
    if ($facts !== '') {
      $parts[] = '';
      $parts[] = $facts;
    }

    $messageForPrompt = ChatPreprocessContext::normalizedText();
    if ($messageForPrompt === '') {
      $messageForPrompt = $content;
    }

    $parts[] = '';
    $parts[] = GuideChannelConfig::promptFragment(
      'current_message_header',
      'Mensaje actual del paciente:'
    );
    $parts[] = $messageForPrompt;

    $offerBlock = GuideChannel::formatOfferForPrompt($offer, $continuing);
    if ($offerBlock !== '') {
      $parts[] = '';
      $parts[] = $offerBlock;
    }

    return implode("\n", $parts);
  }

  private static function formatClinicalRecordBlock(): string
  {
    if (!Yii::$app->has('user', true)) {
      return '';
    }
    $idPersona = (int) Yii::$app->user->getIdPersona();
    if ($idPersona <= 0) {
      return '';
    }

    return (new PatientAiContextBuilder())->build(
      $idPersona,
      PatientAiContextBuilder::PROFILE_GUIDE
    );
  }

  private static function formatFocusAreasLine(GuideFocusState $focus): string
  {
    $areas = $focus->activeAreas;
    if ($areas === []) {
      $areas = ChatPreprocessContext::contextAreas();
    }
    if ($areas === []) {
      return '';
    }

    $sorted = AssistantContextHISArea::sortByProductPriority($areas);
    $header = GuideChannelConfig::promptFragment(
      'focus_areas_header',
      'Ámbito de la consulta (según preprocess):'
    );
    $lines = [$header];
    foreach ($sorted as $area) {
      $desc = AssistantContextHISArea::description($area);
      $lines[] = $desc !== '' ? '- ' . $area . ' — ' . $desc : '- ' . $area;
    }

    return implode("\n", $lines);
  }
}
