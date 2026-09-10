<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;
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
      'necesidad_usuario' => self::resolveNecesidadUsuario($messageForPrompt),
      'context_his_areas_lines' => self::formatContextHisAreasLines($activeAreas),
      'scoped_system_records' => trim($assembled->promptSection),
      'clinical_record_block' => GuideChannelConfig::formatOptionalAttachment(
        'clinical_record',
        self::formatClinicalRecordData()
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
   * 2ª IA incompletas: usa el prompt guide con volcado del plan declarativo
   * (no re-arma context vía assembleForChannel genérico).
   *
   * @param array<string, mixed> $firstIa
   */
  public static function buildForIncomplete(
    array $firstIa,
    string $content,
    int $userId,
    string $scopedSystemRecords,
    string $articleBlock,
    ?SmartCatalogRoutingEvaluation $evaluation = null,
    ?string $formattedHistory = null
  ): string {
    $content = trim($content);
    $areas = is_array($firstIa['context_areas'] ?? null)
      ? AssistantContextHISArea::sortByProductPriority($firstIa['context_areas'])
      : ChatPreprocessContext::contextAreas();
    $focus = GuideFocusResolver::resolve($areas, null, false);

    $history = $formattedHistory ?? GuideHistoryWindow::formatForPrompt(
      $userId,
      $content,
      $focus->primaryArea
    );

    $messageForPrompt = ChatPreprocessContext::normalizedText();
    if ($messageForPrompt === '') {
      $messageForPrompt = $content;
    }

    $catalog = UiActionCatalog::forUser($userId);
    $intentSemantics = self::formatIncompleteIntentSemantics($evaluation);
    if ($intentSemantics === '') {
      $intentSemantics = GuideIntentSemanticsFilter::formatPromptSection($catalog, $areas);
    }

    return GuideChannelConfig::assemblePrompt([
      'necesidad_usuario' => self::resolveNecesidadUsuario(
        $messageForPrompt,
        is_array($firstIa) ? $firstIa : null
      ),
      'context_his_areas_lines' => self::formatContextHisAreasLines($areas),
      'scoped_system_records' => trim($scopedSystemRecords),
      'clinical_record_block' => GuideChannelConfig::formatOptionalAttachment(
        'clinical_record',
        self::formatClinicalRecordData()
      ),
      'intent_semantics' => $intentSemantics,
      'article_block' => trim($articleBlock),
      'conversation_history' => trim($history),
      'current_message' => $messageForPrompt,
    ]);
  }

  /**
   * @param array<string, mixed>|null $firstIa
   */
  private static function resolveNecesidadUsuario(string $fallbackMessage, ?array $firstIa = null): string
  {
    $fromIa = '';
    if ($firstIa !== null) {
      $fromIa = trim((string) ($firstIa['necesidad_usuario'] ?? ''));
    }
    if ($fromIa === '') {
      $fromIa = trim(ChatPreprocessContext::necesidadUsuario());
    }
    if ($fromIa !== '') {
      return $fromIa;
    }

    return trim($fallbackMessage);
  }

  private static function formatIncompleteIntentSemantics(
    ?SmartCatalogRoutingEvaluation $evaluation
  ): string {
    if ($evaluation === null) {
      return '';
    }

    $ids = [];
    $entry = $evaluation->decision->catalogEntry;
    if ($entry !== null) {
      foreach ($entry->ctaIntentIds as $id) {
        $ids[] = $id;
      }
    }
    foreach ($evaluation->match->ranked as $row) {
      $catalogId = trim((string) ($row['catalog_id'] ?? ''));
      if ($catalogId === '') {
        continue;
      }
      $rankedEntry = SmartCatalogRegistry::findById($catalogId);
      if ($rankedEntry === null) {
        continue;
      }
      foreach ($rankedEntry->ctaIntentIds as $id) {
        $ids[] = $id;
      }
      if ($rankedEntry->toolType === 'intent' && $rankedEntry->toolRef !== '') {
        $ids[] = $rankedEntry->toolRef;
      }
    }

    return IntentSemanticsPromptFormatter::formatForIntentIds($ids, 4);
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
      $desc = trim(AssistantContextHISArea::description($area));
      if ($desc === '') {
        continue;
      }
      $lines[] = '- ' . $desc;
    }

    return implode("\n", $lines);
  }

  /**
   * Resumen clínico del sujeto en sesión. Siempre se intenta adjuntar en Guide
   * (el placeholder del prompt queda vacío solo si no hay persona o no hay datos).
   */
  private static function formatClinicalRecordData(): string
  {
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
