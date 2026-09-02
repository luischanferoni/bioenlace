<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use Yii;

/**
 * Ensambla el prompt de la 2ª IA del canal guide.
 *
 * Adjuntos opcionales (HC, artículo): solo si preprocess/áreas y datos lo justifican.
 * CTA: no va en el prompt; se adjunta en la respuesta HTTP (GuideChannel::finalizeResponse).
 */
final class GuidePromptAssembler
{
  public static function build(
    string $content,
    int $userId,
    GuideFocusState $focus,
    ?string $formattedHistory = null,
    ?string $articleData = null
  ): string {
    $content = trim($content);

    $history = $formattedHistory ?? GuideHistoryWindow::formatForPrompt(
      $userId,
      $content,
      $focus->primaryArea
    );
    $activeAreas = self::resolvedActiveAreas($focus);

    $assembled = AssistantContextAssemblyService::assembleForChannel('guide', $userId);
    $catalog = UiActionCatalog::forUser($userId);

    $messageForPrompt = ChatPreprocessContext::normalizedText();
    if ($messageForPrompt === '') {
      $messageForPrompt = $content;
    }

    return GuideChannelConfig::assemblePrompt([
      'context_his_areas_lines' => self::formatContextHisAreasLines($activeAreas),
      'scoped_system_records' => trim($assembled->promptSection),
      'clinical_record_block' => GuideChannelConfig::formatOptionalAttachment(
        'clinical_record',
        self::formatClinicalRecordData($activeAreas)
      ),
      'intent_semantics' => GuideIntentSemanticsFilter::formatPromptSection($catalog, $activeAreas),
      'article_block' => GuideChannelConfig::formatOptionalAttachment(
        'article',
        trim((string) $articleData)
      ),
      'conversation_history' => trim($history),
      'current_message' => $messageForPrompt,
    ]);
  }

  /**
   * @return list<string>
   */
  private static function resolvedActiveAreas(GuideFocusState $focus): array
  {
    $areas = $focus->activeAreas;
    if ($areas === []) {
      $areas = ChatPreprocessContext::contextAreas();
    }

    return AssistantContextHISArea::sortByProductPriority($areas);
  }

  /**
   * @param list<string> $activeAreas
   */
  private static function formatContextHisAreasLines(array $activeAreas): string
  {
    if ($activeAreas === []) {
      return '';
    }

    $lines = [];
    foreach ($activeAreas as $area) {
      $desc = AssistantContextHISArea::description($area);
      $lines[] = $desc !== '' ? '- ' . $area . ' — ' . $desc : '- ' . $area;
    }

    return implode("\n", $lines);
  }

  /**
   * @param list<string> $activeAreas
   */
  private static function formatClinicalRecordData(array $activeAreas): string
  {
    if (!in_array(AssistantContextHISArea::CLINICAL_RECORD, $activeAreas, true)) {
      return '';
    }

    if (!Yii::$app->has('user', true)) {
      return '';
    }
    $idPersona = (int) Yii::$app->user->getIdPersona();
    if ($idPersona <= 0) {
      return '';
    }

    $clinicalBlock = (new PatientAiContextBuilder())->build(
      $idPersona,
      PatientAiContextBuilder::PROFILE_GUIDE
    );
    if ($clinicalBlock === '') {
      return '';
    }

    return '--- context:clinical_record ---'
      . "\n" . $clinicalBlock
      . "\n--- end context:clinical_record ---";
  }
}
