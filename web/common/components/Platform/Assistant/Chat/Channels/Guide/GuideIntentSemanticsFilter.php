<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use Yii;

/**
 * Subset de semántica de intents para el prompt guide según áreas HIS activas.
 */
final class GuideIntentSemanticsFilter
{
  /**
   * @param list<string> $activeAreas
   */
  public static function formatPromptSection(UiActionCatalog $catalog, array $activeAreas): string
  {
    $max = max(1, (int) (Yii::$app->params['asistente_guide_max_intent_semantics'] ?? 6));
    $items = self::forFocus($activeAreas, $catalog, $max);
    if ($items === []) {
      return '';
    }

    $lines = ['--- context:intent_semantics ---'];
    foreach ($items as $item) {
      $sem = is_array($item->intent_semantics) ? $item->intent_semantics : [];
      $summary = trim((string) ($sem['summary'] ?? $item->description));
      $label = $item->display_name !== '' ? $item->display_name : $item->action_id;
      $line = $item->action_id;
      if ($summary !== '') {
        $line .= ': ' . $summary;
      } elseif ($label !== $item->action_id) {
        $line .= ': ' . $label;
      }
      $lines[] = '- ' . $line;

      $capabilities = $sem['capabilities'] ?? [];
      if (is_array($capabilities) && $capabilities !== []) {
        $capSlice = [];
        foreach (array_slice($capabilities, 0, 6) as $cap) {
          if (is_string($cap) && trim($cap) !== '') {
            $capSlice[] = trim($cap);
          }
        }
        if ($capSlice !== []) {
          $lines[] = '  capabilities: ' . implode(', ', $capSlice);
        }
      }
    }
    $lines[] = '--- end context:intent_semantics ---';

    return implode("\n", $lines);
  }

  /**
   * @param list<string> $activeAreas
   * @return list<UiActionCatalogItem>
   */
  public static function forFocus(array $activeAreas, UiActionCatalog $catalog, int $max): array
  {
    if ($activeAreas === []) {
      return [];
    }

    $areaSet = [];
    foreach ($activeAreas as $area) {
      if (is_string($area) && trim($area) !== '') {
        $areaSet[trim($area)] = true;
      }
    }
    if ($areaSet === []) {
      return [];
    }

    $out = [];
    foreach ($catalog->items as $item) {
      if ($item->his_areas === []) {
        continue;
      }
      if (!self::intersectsAreas($areaSet, $item->his_areas)) {
        continue;
      }
      $out[] = $item;
      if (count($out) >= $max) {
        break;
      }
    }

    return $out;
  }

  /**
   * @param array<string, true> $activeSet
   * @param list<string> $hisAreas
   */
  private static function intersectsAreas(array $activeSet, array $hisAreas): bool
  {
    foreach ($hisAreas as $area) {
      if (!is_string($area)) {
        continue;
      }
      $area = trim($area);
      if ($area !== '' && isset($activeSet[$area])) {
        return true;
      }
    }

    return false;
  }
}
