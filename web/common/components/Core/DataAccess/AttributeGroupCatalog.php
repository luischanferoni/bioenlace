<?php

namespace common\components\Core\DataAccess;

use common\components\Core\DataAccess\Attribute\DatabaseAttributeDefinitionSource;
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
    $out = DatabaseAttributeDefinitionSource::listGroupOptions();
    $entities = self::load()['entities'] ?? [];
    if (is_array($entities)) {
      foreach ($entities as $entityName => $groups) {
        if (!is_string($entityName) || !is_array($groups)) {
          continue;
        }
        foreach ($groups as $groupKey => $def) {
          if (!is_string($groupKey)) {
            continue;
          }
          $fullKey = $entityName . '.' . $groupKey;
          if (isset($out[$fullKey])) {
            continue;
          }
          $attrList = implode(', ', $this->getEntityGroupAttributes($fullKey));
          $out[$fullKey] = $attrList !== '' ? ($fullKey . ' (' . $attrList . ')') : $fullKey;
        }
      }
    }
    ksort($out);

    return $out;
  }

  public function entityGroupExists(string $entityGroupKey): bool
  {
    $entityGroupKey = trim($entityGroupKey);
    if ($entityGroupKey === '') {
      return false;
    }
    if (DatabaseAttributeDefinitionSource::groupExists($entityGroupKey)) {
      return true;
    }

    return isset($this->listYamlEntityGroupKeys()[$entityGroupKey]);
  }

  /**
   * @return array<string, true>
   */
  private function listYamlEntityGroupKeys(): array
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
        if (is_string($groupKey)) {
          $out[$entityName . '.' . $groupKey] = true;
        }
      }
    }

    return $out;
  }

  /**
   * Scope ABAC declarado para un grupo (YAML groups.scope_checker o edit.scope_checker del surface).
   */
  public function getEntityGroupScopeChecker(string $entityGroupKey): ?string
  {
    $entityGroupKey = trim($entityGroupKey);
    $dot = strpos($entityGroupKey, '.');
    if ($dot === false) {
      return null;
    }

    $entityName = substr($entityGroupKey, 0, $dot);
    $groupKey = substr($entityGroupKey, $dot + 1);
    $entities = self::load()['entities'] ?? [];
    if (is_array($entities) && isset($entities[$entityName][$groupKey]) && is_array($entities[$entityName][$groupKey])) {
      $scope = trim((string) ($entities[$entityName][$groupKey]['scope_checker'] ?? ''));
      if ($scope !== '') {
        return $scope;
      }
    }

    $editFlows = self::load()['edit_flows'] ?? [];
    if (!is_array($editFlows)) {
      return null;
    }

    foreach ($editFlows as $surfaceEntity => $edit) {
      if (!is_array($edit)) {
        continue;
      }
      $editScope = trim((string) ($edit['scope_checker'] ?? ''));
      if ($editScope === '') {
        continue;
      }
      $aspects = $edit['aspects'] ?? $edit['attributes'] ?? null;
      if (!is_array($aspects)) {
        continue;
      }
      foreach ($aspects as $def) {
        if (!is_array($def)) {
          continue;
        }
        if (trim((string) ($def['attribute_group'] ?? '')) === $entityGroupKey) {
          return $editScope;
        }
      }
      if ($surfaceEntity === $entityName && isset($entities[$entityName][$groupKey])) {
        return $editScope;
      }
    }

    return null;
  }

  /**
   * @return list<string>
   */
  public function getEntityGroupAttributes(string $entityGroupKey): array
  {
    return array_keys($this->getEntityGroupFieldDefinitions($entityGroupKey));
  }

  /**
   * Definiciones de campos del grupo (tipo, label, options, widget, etc.).
   *
   * @return array<string, array<string, mixed>>
   */
  public function getEntityGroupFieldDefinitions(string $entityGroupKey): array
  {
    $entityGroupKey = trim($entityGroupKey);
    if ($entityGroupKey === '') {
      return [];
    }

    $fromDb = DatabaseAttributeDefinitionSource::getFieldDefinitions($entityGroupKey);
    if ($fromDb !== []) {
      return $fromDb;
    }

    return $this->getYamlEntityGroupFieldDefinitions($entityGroupKey);
  }

  /**
   * Fallback legacy: grupos con lista simple de nombres en YAML (p. ej. asignacion).
   *
   * @return array<string, array<string, mixed>>
   */
  private function getYamlEntityGroupFieldDefinitions(string $entityGroupKey): array
  {
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

    $attrs = $group;
    if (isset($group['attributes']) && is_array($group['attributes'])) {
      $attrs = $group['attributes'];
    } elseif (array_is_list($group)) {
      $attrs = $group;
    }

    if (!is_array($attrs) || $attrs === []) {
      return [];
    }

    if (array_is_list($attrs)) {
      $out = [];
      foreach ($attrs as $name) {
        $key = trim((string) $name);
        if ($key !== '') {
          $out[$key] = ['type' => 'text'];
        }
      }

      return $out;
    }

    $out = [];
    foreach ($attrs as $name => $def) {
      if (!is_string($name)) {
        continue;
      }
      $key = trim($name);
      if ($key === '') {
        continue;
      }
      $out[$key] = is_array($def) ? $def : ['type' => 'text'];
    }

    return $out;
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
    if ($surfaceId === '') {
      return null;
    }

    $data = self::load();
    $flows = $data['edit_flows'] ?? [];
    if (is_array($flows) && isset($flows[$surfaceId]) && is_array($flows[$surfaceId])) {
      return $flows[$surfaceId];
    }

    $surfaces = $data['edit_surfaces'] ?? [];
    if (is_array($surfaces) && isset($surfaces[$surfaceId]) && is_array($surfaces[$surfaceId])) {
      return $surfaces[$surfaceId];
    }

    return null;
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public function listEditSurfacesForDisplay(): array
  {
    $data = self::load();
    $flows = is_array($data['edit_flows'] ?? null) ? $data['edit_flows'] : [];
    $legacy = is_array($data['edit_surfaces'] ?? null) ? $data['edit_surfaces'] : [];

    return array_merge($legacy, $flows);
  }

  public static function resetCacheForTests(): void
  {
    self::$cache = null;
    DatabaseAttributeDefinitionSource::clearCache();
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
      'entity_sources' => [],
      'metrics' => [],
      'edit_flows' => [],
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

    $infoList = $chunk['info_list'] ?? $chunk['metrics'] ?? null;
    if (is_array($infoList)) {
      foreach ($infoList as $metricId => $def) {
        if (!is_string($metricId)) {
          continue;
        }
        if (isset($merged['metrics'][$metricId])) {
          throw new \RuntimeException('Consulta info/list duplicada ' . $metricId . ' en ' . $sourceFile);
        }
        $merged['metrics'][$metricId] = $def;
      }
    }

    $uiSource = $chunk['ui_json_source'] ?? null;
    if ($entity !== '' && is_array($uiSource)) {
      $merged['entity_sources'][$entity] = $uiSource;
    }

    if (isset($chunk['edit']) && is_array($chunk['edit'])) {
      if ($entity === '') {
        throw new \RuntimeException('edit requiere entity en ' . $sourceFile);
      }
      if (isset($merged['edit_flows'][$entity])) {
        throw new \RuntimeException('edit duplicado para entity ' . $entity . ' en ' . $sourceFile);
      }
      $merged['edit_flows'][$entity] = self::normalizeEditBlock($chunk['edit']);
    }

    if (isset($chunk['edit_surfaces']) && is_array($chunk['edit_surfaces'])) {
      foreach ($chunk['edit_surfaces'] as $surfaceId => $def) {
        if (!is_string($surfaceId)) {
          continue;
        }
        if (isset($merged['edit_surfaces'][$surfaceId]) || isset($merged['edit_flows'][$surfaceId])) {
          throw new \RuntimeException('Superficie duplicada ' . $surfaceId . ' en ' . $sourceFile);
        }
        $merged['edit_surfaces'][$surfaceId] = is_array($def) ? self::normalizeEditBlock($def) : $def;
      }
    }
  }

  /**
   * Convierte edit.attributes (declarativo) en aspects internos (1 atributo = 1 aspecto).
   *
   * @param array<string, mixed> $edit
   * @return array<string, mixed>
   */
  private static function normalizeEditBlock(array $edit): array
  {
    if (isset($edit['aspects']) && is_array($edit['aspects']) && !isset($edit['attributes'])) {
      return $edit;
    }

    $attributes = $edit['attributes'] ?? null;
    if (!is_array($attributes)) {
      return $edit;
    }

    $aspects = [];
    foreach ($attributes as $attrName => $attrDef) {
      if (!is_string($attrName) || !is_array($attrDef)) {
        continue;
      }
      $aspects[$attrName] = self::attributeDefToAspect($attrName, $attrDef);
    }

    $out = $edit;
    unset($out['attributes']);
    $out['aspects'] = $aspects;

    return $out;
  }

  /**
   * @param array<string, mixed> $def
   * @return array<string, mixed>
   */
  private static function attributeDefToAspect(string $attrName, array $def): array
  {
    $uiAction = trim((string) ($def['ui_action'] ?? ''));
    $kind = $uiAction !== '' ? 'open_ui' : trim((string) ($def['kind'] ?? 'field_group'));
    if ($kind === '') {
      $kind = 'field_group';
    }

    $aspect = $def;
    $aspect['kind'] = $kind;
    $aspect['fields'] = [$attrName];
    if ($uiAction !== '') {
      $aspect['ui_action'] = $uiAction;
    }

    return $aspect;
  }
}
