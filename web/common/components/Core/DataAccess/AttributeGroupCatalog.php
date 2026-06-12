<?php

namespace common\components\Core\DataAccess;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo de grupos de atributos, métricas y superficies de edición.
 * Fuente: SubIntentEngine/schemas/data-access-config/*.yaml
 */
final class AttributeGroupCatalog
{
  private const CONFIG_DIR = __DIR__ . '/../../Assistant/SubIntentEngine/schemas/data-access-config';

  private const MANIFEST_FILE = 'manifest.yaml';

  /** @var array<string, mixed>|null */
  private static $cache;

  public static function configDirectory(): string
  {
    $dir = realpath(self::CONFIG_DIR);

    return $dir !== false ? $dir : self::CONFIG_DIR;
  }

  /**
   * @return array<string, mixed>|null
   */
  public function getMetric(string $metricId): ?array
  {
    $metricId = trim($metricId);
    $metrics = self::load()['metrics'] ?? [];
    if (!is_array($metrics) || !isset($metrics[$metricId]) || !is_array($metrics[$metricId])) {
      return null;
    }

    return $metrics[$metricId];
  }

  /**
   * Plan de compilación SQL/Yii para una métrica.
   *
   * @return array<string, mixed>|null
   */
  public function getMetricQueryPlan(string $metricId): ?array
  {
    $metric = $this->getMetric($metricId);
    if ($metric === null) {
      return null;
    }
    $query = $metric['query'] ?? null;

    return is_array($query) ? $query : null;
  }

  /**
   * @return array<string, mixed>|null definición de un filtro allowlisted
   */
  public function getFilterDefinition(string $metricId, string $filterKey): ?array
  {
    $plan = $this->getMetricQueryPlan($metricId);
    if ($plan === null) {
      return null;
    }
    $filters = $plan['filters'] ?? null;
    if (!is_array($filters) || !isset($filters[$filterKey]) || !is_array($filters[$filterKey])) {
      return null;
    }

    return $filters[$filterKey];
  }

  /**
   * Filtros con entity_group → clave para autorización (filterKey => entity.group).
   *
   * @return array<string, string>
   */
  public function filterEntityGroupMap(string $metricId): array
  {
    $plan = $this->getMetricQueryPlan($metricId);
    if ($plan === null) {
      return [];
    }
    $filters = $plan['filters'] ?? null;
    if (!is_array($filters)) {
      return [];
    }

    $out = [];
    foreach ($filters as $filterKey => $def) {
      if (!is_array($def)) {
        continue;
      }
      $group = trim((string) ($def['entity_group'] ?? ''));
      if ($group !== '') {
        $out[trim((string) $filterKey)] = $group;
      }
    }

    return $out;
  }

  /**
   * @return array<string, mixed>|null bloque output del plan query
   */
  public function getMetricOutputPlan(string $metricId): ?array
  {
    $plan = $this->getMetricQueryPlan($metricId);
    if ($plan === null) {
      return null;
    }
    $output = $plan['output'] ?? null;

    return is_array($output) ? $output : null;
  }

  public function getPresentationHandler(string $metricId): ?string
  {
    $metric = $this->getMetric($metricId);
    if ($metric === null) {
      return null;
    }
    $handler = trim((string) ($metric['presentation_handler'] ?? ''));

    return $handler !== '' ? $handler : null;
  }

  /**
   * Resuelve sexo_biologico (1 F, 2 M) desde mención NL usando sinónimos del catálogo.
   */
  public function resolveSexoBiologicoFromMention(string $mention): ?int
  {
    $mention = mb_strtolower(trim($mention), 'UTF-8');
    if ($mention === '') {
      return null;
    }

    $synonyms = self::load()['filter_synonyms']['sexo_biologico'] ?? null;
    if (!is_array($synonyms)) {
      return null;
    }

    foreach ($synonyms as $code => $variants) {
      $intCode = (int) $code;
      if ($intCode <= 0) {
        continue;
      }
      if ((string) $code === $mention || (string) $intCode === $mention) {
        return $intCode;
      }
      if (!is_array($variants)) {
        continue;
      }
      foreach ($variants as $variant) {
        $v = mb_strtolower(trim((string) $variant), 'UTF-8');
        if ($v !== '' && ($v === $mention || mb_strpos($mention, $v) !== false)) {
          return $intCode;
        }
      }
    }

    return null;
  }

  /**
   * @return array<string, string> clave Entidad.grupo => etiqueta para formularios
   */
  public function listEntityGroupOptions(): array
  {
    $out = [];
    $entities = self::load()['entities'] ?? [];
    if (!is_array($entities)) {
      return $out;
    }
    foreach ($entities as $entityName => $groups) {
      if (!is_string($entityName) || !is_array($groups)) {
        continue;
      }
      foreach ($groups as $groupKey => $def) {
        if (!is_string($groupKey)) {
          continue;
        }
        $fullKey = $entityName . '.' . $groupKey;
        $attrs = is_array($def) ? ($def['attributes'] ?? []) : [];
        $attrList = is_array($attrs) ? implode(', ', array_map('strval', $attrs)) : '';
        $out[$fullKey] = $attrList !== '' ? ($fullKey . ' (' . $attrList . ')') : $fullKey;
      }
    }
    ksort($out);

    return $out;
  }

  public function entityGroupExists(string $entityGroupKey): bool
  {
    return isset($this->listEntityGroupOptions()[trim($entityGroupKey)]);
  }

  /**
   * @return list<string>
   */
  public function getEntityGroupAttributes(string $entityGroupKey): array
  {
    $entityGroupKey = trim($entityGroupKey);
    $dot = strpos($entityGroupKey, '.');
    if ($dot === false) {
      return [];
    }

    $entityName = substr($entityGroupKey, 0, $dot);
    $groupKey = substr($entityGroupKey, $dot + 1);
    $entities = self::load()['entities'] ?? [];
    if (!is_array($entities) || !isset($entities[$entityName]) || !is_array($entities[$entityName])) {
      return [];
    }

    $group = $entities[$entityName][$groupKey] ?? null;
    if (!is_array($group)) {
      return [];
    }

    $attrs = $group['attributes'] ?? [];
    if (!is_array($attrs)) {
      return [];
    }

    return array_values(array_filter(array_map(
      static fn ($a): string => trim((string) $a),
      $attrs
    ), static fn (string $a): bool => $a !== ''));
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public function listEntitiesForDisplay(): array
  {
    $entities = self::load()['entities'] ?? [];

    return is_array($entities) ? $entities : [];
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public function listMetricsForDisplay(): array
  {
    $metrics = self::load()['metrics'] ?? [];

    return is_array($metrics) ? $metrics : [];
  }

  /**
   * @return array<string, mixed>|null
   */
  public function getEditSurface(string $surfaceId): ?array
  {
    $surfaceId = trim($surfaceId);
    $surfaces = self::load()['edit_surfaces'] ?? [];
    if (!is_array($surfaces) || !isset($surfaces[$surfaceId]) || !is_array($surfaces[$surfaceId])) {
      return null;
    }

    return $surfaces[$surfaceId];
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public function listEditSurfacesForDisplay(): array
  {
    $surfaces = self::load()['edit_surfaces'] ?? [];

    return is_array($surfaces) ? $surfaces : [];
  }

  public static function resetCacheForTests(): void
  {
    self::$cache = null;
  }

  /**
   * @return array<string, mixed>
   */
  private static function load(): array
  {
    if (self::$cache !== null) {
      return self::$cache;
    }

    self::$cache = self::loadFromConfigDirectory();

    return self::$cache;
  }

  /**
   * @return array<string, mixed>
   */
  private static function loadFromConfigDirectory(): array
  {
    $dir = self::configDirectory();
    if (!is_dir($dir)) {
      throw new \RuntimeException('Catálogo DataAccess no encontrado: ' . $dir);
    }

    $manifestPath = $dir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
    if (!is_file($manifestPath)) {
      throw new \RuntimeException('Manifest DataAccess no encontrado: ' . $manifestPath);
    }

    $manifest = Yaml::parseFile($manifestPath);
    if (!is_array($manifest)) {
      throw new \RuntimeException('Manifest DataAccess inválido.');
    }

    $merged = [
      'version' => $manifest['version'] ?? 1,
      'entities' => [],
      'metrics' => [],
      'edit_surfaces' => [],
      'filter_synonyms' => is_array($manifest['filter_synonyms'] ?? null) ? $manifest['filter_synonyms'] : [],
    ];

    $files = glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
    sort($files);
    foreach ($files as $file) {
      if (basename($file) === self::MANIFEST_FILE) {
        continue;
      }
      $chunk = Yaml::parseFile($file);
      if (!is_array($chunk)) {
        throw new \RuntimeException('Fragmento DataAccess inválido: ' . $file);
      }
      self::mergeEntityConfigFile($merged, $chunk, $file);
    }

    return $merged;
  }

  /**
   * @param array<string, mixed> $merged
   * @param array<string, mixed> $chunk
   */
  private static function mergeEntityConfigFile(array &$merged, array $chunk, string $sourceFile): void
  {
    $entity = trim((string) ($chunk['entity'] ?? ''));
    $groups = $chunk['groups'] ?? null;
    if ($entity !== '' && is_array($groups)) {
      if (!isset($merged['entities'][$entity]) || !is_array($merged['entities'][$entity])) {
        $merged['entities'][$entity] = [];
      }
      foreach ($groups as $groupKey => $def) {
        if (!is_string($groupKey)) {
          continue;
        }
        if (isset($merged['entities'][$entity][$groupKey])) {
          throw new \RuntimeException(
            'Grupo duplicado ' . $entity . '.' . $groupKey . ' en ' . $sourceFile
          );
        }
        $merged['entities'][$entity][$groupKey] = $def;
      }
    }

    if (isset($chunk['metrics']) && is_array($chunk['metrics'])) {
      foreach ($chunk['metrics'] as $metricId => $def) {
        if (!is_string($metricId)) {
          continue;
        }
        if (isset($merged['metrics'][$metricId])) {
          throw new \RuntimeException('Métrica duplicada ' . $metricId . ' en ' . $sourceFile);
        }
        $merged['metrics'][$metricId] = $def;
      }
    }

    if (isset($chunk['edit_surfaces']) && is_array($chunk['edit_surfaces'])) {
      foreach ($chunk['edit_surfaces'] as $surfaceId => $def) {
        if (!is_string($surfaceId)) {
          continue;
        }
        if (isset($merged['edit_surfaces'][$surfaceId])) {
          throw new \RuntimeException('Superficie duplicada ' . $surfaceId . ' en ' . $sourceFile);
        }
        $merged['edit_surfaces'][$surfaceId] = $def;
      }
    }
  }
}
