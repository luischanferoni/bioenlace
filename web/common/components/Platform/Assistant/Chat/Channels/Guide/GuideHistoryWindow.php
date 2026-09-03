<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Platform\Assistant\Chat\Channels\Conversational\ConversationalHistoryWindow;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadStateService;
use common\models\AsistenteConversacion;
use common\models\AsistenteInteraccion;
use Yii;

/**
 * Historial del canal guide filtrado por foco ({@see guide_focus} en metadata).
 */
final class GuideHistoryWindow
{
  private const BOT_SENDER = 'BOT';
  private const DEFAULT_MAX_TURNOS = 5;
  private const DEFAULT_MAX_CHARS = 3200;

  public static function formatForPrompt(int $userId, string $currentContent, string $primaryFocusArea = ''): string
  {
    $uidStr = (string) $userId;
    $conversacion = AsistenteConversacion::findOne([
      'usuario_id' => $uidStr,
      'bot_id' => self::BOT_SENDER,
    ]);
    if ($conversacion === null) {
      return '';
    }

    $maxTurnos = max(1, (int) (Yii::$app->params['asistente_guide_historial_max_turnos']
      ?? Yii::$app->params['asistente_conversacional_historial_max_turnos']
      ?? self::DEFAULT_MAX_TURNOS));
    $maxChars = max(200, (int) (Yii::$app->params['asistente_guide_historial_max_chars']
      ?? Yii::$app->params['asistente_conversacional_historial_max_chars']
      ?? self::DEFAULT_MAX_CHARS));

    $fetchLimit = min(80, $maxTurnos * 4 + 8);
    $rows = AsistenteInteraccion::find()
      ->where(['conversacion_id' => (int) $conversacion->id])
      ->orderBy(['id' => SORT_DESC])
      ->limit($fetchLimit)
      ->all();

    return self::buildFromInteractions(
      $rows,
      $uidStr,
      $currentContent,
      $maxTurnos,
      $maxChars,
      trim($primaryFocusArea)
    );
  }

  /**
   * @param AsistenteInteraccion[] $rowsNewestFirst
   */
  public static function buildFromInteractions(
    array $rowsNewestFirst,
    string $userId,
    string $currentContent,
    int $maxTurnos,
    int $maxChars,
    string $primaryFocusArea = ''
  ): string {
    $currentTrimmed = trim($currentContent);
    $primaryFocusArea = trim($primaryFocusArea);
    $lines = [];
    $skippedCurrentDuplicate = false;

    foreach ($rowsNewestFirst as $row) {
      if (!$row instanceof AsistenteInteraccion) {
        continue;
      }

      $text = trim((string) $row->texto);
      if ($text === '') {
        continue;
      }

      if (ConversationalHistoryWindow::isOperationalBoundary($text)) {
        break;
      }

      $metadata = self::decodeMetadata($row->metadata ?? null);
      if (!self::matchesFocus($metadata, $primaryFocusArea)) {
        $rowTag = AssistantThreadStateService::threadTagFromMetadata($metadata);
        if ($rowTag !== '') {
          break;
        }
        continue;
      }

      if (!ConversationalHistoryWindow::isEligibleLine($text)) {
        continue;
      }

      if (
        !$skippedCurrentDuplicate
        && (string) $row->sender_id === $userId
        && $text === $currentTrimmed
      ) {
        $skippedCurrentDuplicate = true;
        continue;
      }

      $role = (string) $row->sender_id === self::BOT_SENDER ? 'Asistente' : 'Paciente';
      array_unshift($lines, $role . ': ' . $text);
    }

    $lines = ConversationalHistoryWindow::trimToBudget($lines, $maxTurnos, $maxChars);

    return $lines === [] ? '' : implode("\n", $lines);
  }

  public static function extractPatientLines(string $formattedHistory): string
  {
    return ConversationalHistoryWindow::extractPatientLines($formattedHistory);
  }

  /**
   * @param array<string, mixed> $metadata
   */
  private static function matchesFocus(array $metadata, string $primaryFocusArea): bool
  {
    if ($primaryFocusArea === '') {
      return true;
    }

    $focus = GuideFocusState::fromMetadataArray($metadata['guide_focus'] ?? null);
    if ($focus !== null && $focus->primaryArea !== '') {
      return $focus->primaryArea === $primaryFocusArea;
    }

    $rowTag = AssistantThreadStateService::threadTagFromMetadata($metadata);
    if ($rowTag === '') {
      return true;
    }

    return $rowTag === 'guide:' . $primaryFocusArea || $rowTag === 'guide';
  }

  /**
   * @param mixed $raw
   * @return array<string, mixed>
   */
  private static function decodeMetadata($raw): array
  {
    if (is_array($raw)) {
      return $raw;
    }
    if (is_string($raw) && trim($raw) !== '') {
      $decoded = json_decode($raw, true);

      return is_array($decoded) ? $decoded : [];
    }

    return [];
  }
}
