<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

/**
 * Resuelve foco de guía desde preprocess + estado previo.
 * Sin catálogo de áreas HIS: solo reutiliza foco previo del hilo si hay carry.
 */
final class GuideFocusResolver
{
  /**
   * @param list<string> $contextAreas legado ignorado (siempre vacío)
   * @param array{primary_area?: string, active_areas?: list<string>}|null $previousFocus
   */
  public static function resolve(array $contextAreas, ?array $previousFocus, bool $carryFocus = true): GuideFocusState
  {
    unset($contextAreas);

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
    return (bool) (\Yii::$app->params['asistente_guide_carry_focus'] ?? true);
  }
}
