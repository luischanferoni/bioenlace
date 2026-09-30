<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Domain\Clinical\Encounter\Application\Service\PatientAiContextService;
use common\components\Platform\Assistant\Catalog\DiscoveryIndex;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Thread\ThreadNeedList;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;
use Yii;

/**
 * Ensambla el prompt de la 2ª IA del canal guide.
 *
 * Adjuntos opcionales (HC, artículo): discovery + datos de sesión.
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

    $assembled = AssistantContextAssemblyService::assembleForChannel('guide', $userId);

    $messageForPrompt = ChatPreprocessContext::normalizedText();
    if ($messageForPrompt === '') {
      $messageForPrompt = $content;
    }

    $intentSemantics = self::formatDiscoveryIntentSemantics([
      'tags' => ChatPreprocessContext::tags(),
      'normalized_text' => $messageForPrompt,
    ], $messageForPrompt, $userId);

    return GuideChannelConfig::assemblePrompt([
      'necesidad_usuario' => self::resolveNecesidadUsuario($messageForPrompt),
      'scoped_system_records' => GuideChannelConfig::formatOptionalAttachment(
        'scoped_system_records',
        trim($assembled->promptSection)
      ),
      'clinical_record_block' => GuideChannelConfig::formatOptionalAttachment(
        'clinical_record',
        self::formatClinicalRecordData()
      ),
      'intent_semantics' => $intentSemantics,
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
    $focus = GuideFocusResolver::resolve([], null, false);

    $history = $formattedHistory ?? GuideHistoryWindow::formatForPrompt(
      $userId,
      $content,
      $focus->primaryArea
    );

    $messageForPrompt = ChatPreprocessContext::normalizedText();
    if ($messageForPrompt === '') {
      $messageForPrompt = $content;
    }

    $intentSemantics = self::formatIncompleteIntentSemantics($firstIa, $evaluation);
    if ($intentSemantics === '') {
      $intentSemantics = self::formatDiscoveryIntentSemantics($firstIa, $messageForPrompt, $userId);
    }
    $articleBlock = trim($articleBlock);
    if ($articleBlock === '') {
      $articleBlock = DiscoveryIndex::formatTopArticleBlock($firstIa, $content, $userId);
    }

    return GuideChannelConfig::assemblePrompt([
      'necesidad_usuario' => self::resolveNecesidadUsuario(
        $messageForPrompt,
        is_array($firstIa) ? $firstIa : null
      ),
      'scoped_system_records' => GuideChannelConfig::formatOptionalAttachment(
        'scoped_system_records',
        trim($scopedSystemRecords)
      ),
      'clinical_record_block' => GuideChannelConfig::formatOptionalAttachment(
        'clinical_record',
        self::formatClinicalRecordData()
      ),
      'intent_semantics' => $intentSemantics,
      'article_block' => $articleBlock,
      'conversation_history' => trim($history),
      'current_message' => $messageForPrompt,
    ]);
  }

  /**
   * @param array<string, mixed>|null $firstIa
   */
  private static function resolveNecesidadUsuario(string $fallbackMessage, ?array $firstIa = null): string
  {
    $needs = null;
    if ($firstIa !== null && isset($firstIa['necesidades_usuario']) && is_array($firstIa['necesidades_usuario'])) {
      $needs = $firstIa['necesidades_usuario'];
    }
    if ($needs === null) {
      $fromContext = ChatPreprocessContext::necesidadesUsuario();
      if ($fromContext !== []) {
        $needs = $fromContext;
      }
    }
    if (is_array($needs) && $needs !== []) {
      return ThreadNeedList::activeText(ThreadNeedList::normalize($needs));
    }

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
    array $firstIa,
    ?SmartCatalogRoutingEvaluation $evaluation
  ): string {
    $fromEval = ($evaluation !== null && is_array($evaluation->firstIa)) ? $evaluation->firstIa : [];
    $payload = $fromEval !== [] ? $fromEval : $firstIa;
    $discovery = DiscoveryIndex::match($payload, '', 0);
    if ($discovery->intentHits === []) {
      return '';
    }

    return IntentSemanticsPromptFormatter::formatStateHits($discovery->intentHits);
  }

  /**
   * @param array<string, mixed> $firstIa
   */
  private static function formatDiscoveryIntentSemantics(array $firstIa, string $message, int $userId): string
  {
    $discovery = DiscoveryIndex::match($firstIa, $message, $userId);
    if ($discovery->intentHits === []) {
      return '';
    }

    return IntentSemanticsPromptFormatter::formatStateHits($discovery->intentHits);
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

    $clinicalBlock = (new PatientAiContextService())->build(
      $idPersona,
      PatientAiContextService::PROFILE_GUIDE
    );
    if ($clinicalBlock === '') {
      return '';
    }

    return '--- context:clinical_record ---'
      . "\n" . $clinicalBlock
      . "\n--- end context:clinical_record ---";
  }
}
