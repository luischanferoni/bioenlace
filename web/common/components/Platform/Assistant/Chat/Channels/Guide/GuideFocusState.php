<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

/**
 * Foco de guía persistido (área HIS primaria + áreas activas del turno).
 */
final class GuideFocusState
{
    public string $primaryArea = '';

    /** @var list<string> */
    public array $activeAreas = [];

  /**
   * @param list<string> $activeAreas
   */
  public function __construct(string $primaryArea = '', array $activeAreas = [])
  {
    $this->primaryArea = trim($primaryArea);
    $this->activeAreas = self::normalizeAreas($activeAreas);
    if ($this->primaryArea === '' && $this->activeAreas !== []) {
      $this->primaryArea = $this->activeAreas[0];
    }
  }

  public function threadTag(): string
  {
    if ($this->primaryArea !== '') {
      return 'guide:' . $this->primaryArea;
    }

    return 'guide';
  }

  public function isEmpty(): bool
  {
    return $this->primaryArea === '' && $this->activeAreas === [];
  }

  /**
   * @return array{primary_area: string, active_areas: list<string>}
   */
  public function toMetadataArray(): array
  {
    return [
      'primary_area' => $this->primaryArea,
      'active_areas' => $this->activeAreas,
    ];
  }

  /**
   * @param array<string, mixed>|null $raw
   */
  public static function fromMetadataArray(?array $raw): ?self
  {
    if ($raw === null || $raw === []) {
      return null;
    }
    $primary = trim((string) ($raw['primary_area'] ?? ''));
    $active = $raw['active_areas'] ?? [];
    if (!is_array($active)) {
      $active = [];
    }

    if ($primary === '' && $active === []) {
      return null;
    }

    return new self($primary, $active);
  }

  /**
   * @param list<string> $areas
   * @return list<string>
   */
  private static function normalizeAreas(array $areas): array
  {
    $out = [];
    foreach ($areas as $area) {
      if (!is_string($area)) {
        continue;
      }
      $area = trim($area);
      if ($area === '' || in_array($area, $out, true)) {
        continue;
      }
      $out[] = $area;
    }

    return $out;
  }
}
