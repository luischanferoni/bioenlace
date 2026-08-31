<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use Yii;

/**
 * Resuelve foco de guía desde preprocess + estado previo.
 */
final class GuideFocusResolver
{
  /**
   * @param list<string> $contextAreas
   * @param array{primary_area?: string, active_areas?: list<string>}|null $previousFocus
   */
  public static function resolve(array $contextAreas, ?array $previousFocus, bool $carryFocus = true): GuideFocusState
  {
    $sorted = AssistantContextHISArea::sortByProductPriority($contextAreas);
    if ($sorted !== []) {
      return new GuideFocusState($sorted[0], $sorted);
    }

    if ($carryFocus && $previousFocus !== null) {
      $state = GuideFocusState::fromMetadataArray($previousFocus);
      if ($state !== null) {
        return $state;
      }
    }

    return new GuideFocusState();
  }

  public static function carryFocusEnabled(): bool
  {
    return (bool) (Yii::$app->params['asistente_guide_carry_focus'] ?? true);
  }
}
