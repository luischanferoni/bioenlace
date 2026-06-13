<?php

namespace common\components\Core\DataAccess\Validation;

use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\models\DataAccess\DataAccessAttributeField;
use Symfony\Component\Yaml\Yaml;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * Valida coherencia entre data-access-config YAML, modelos AR, ui_json y campos en BD.
 */
final class DataAccessCatalogCheckService
{
    private const CONFIG_DIR = __DIR__ . '/../../../Assistant/SubIntentEngine/schemas/data-access-config';

    private const MANIFEST_FILE = 'manifest.yaml';

    /**
     * @return list<string> errores (vacío = OK)
     */
    public function run(): array
    {
        $errors = [];
        $dir = realpath(self::CONFIG_DIR);
        if ($dir === false || !is_dir($dir)) {
            return ['Catálogo DataAccess no encontrado: ' . self::CONFIG_DIR];
        }

        $entityFiles = [];
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
        sort($files);
        foreach ($files as $file) {
            if (basename($file) === self::MANIFEST_FILE) {
                continue;
            }
            try {
                $chunk = Yaml::parseFile($file);
            } catch (\Throwable $e) {
                $errors[] = 'YAML inválido ' . basename($file) . ': ' . $e->getMessage();
                continue;
            }
            if (!is_array($chunk)) {
                $errors[] = 'Fragmento inválido: ' . basename($file);
                continue;
            }
            $entityFiles[] = ['path' => $file, 'chunk' => $chunk];
            $errors = array_merge($errors, $this->checkEntityFile($file, $chunk));
        }

        $errors = array_merge($errors, $this->checkAspectsReferenceKnownGroups($entityFiles));
        $errors = array_merge($errors, $this->checkAttributeFieldsInDatabase($entityFiles));

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string, mixed> $chunk
     * @return list<string>
     */
    private function checkEntityFile(string $path, array $chunk): array
    {
        $errors = [];
        $basename = basename($path);
        $entity = trim((string) ($chunk['entity'] ?? ''));
        if ($entity === '') {
            $errors[] = $basename . ': falta entity';

            return $errors;
        }

        $modelClass = trim((string) ($chunk['model'] ?? ''));
        if ($modelClass === '') {
            $errors[] = $basename . ': falta model para ' . $entity;
        } else {
            $errors = array_merge($errors, $this->checkModelClass($modelClass, $basename));
        }

        if (isset($chunk['metrics']) && !isset($chunk['info_list'])) {
            $errors[] = $basename . ': usar info_list en lugar de metrics';
        }

        if (isset($chunk['edit_surfaces'])) {
            $errors[] = $basename . ': usar edit (flow id = entity) en lugar de edit_surfaces';
        }

        $entityUiSource = $chunk['ui_json_source'] ?? null;
        if (is_array($entityUiSource)) {
            $uiGroupKey = trim((string) ($entityUiSource['attribute_group'] ?? ''));
            $errors = array_merge(
                $errors,
                $this->checkUiJsonSource($uiGroupKey !== '' ? $uiGroupKey : $entity, $entityUiSource)
            );
        }

        $edit = $chunk['edit'] ?? null;
        if (is_array($edit)) {
            $attributes = $edit['attributes'] ?? null;
            if (!is_array($attributes) || $attributes === []) {
                $errors[] = $basename . ': edit requiere attributes no vacío';
            } else {
                foreach ($attributes as $attrName => $attrDef) {
                    if (!is_string($attrName) || !is_array($attrDef)) {
                        continue;
                    }
                    $errors = array_merge(
                        $errors,
                        $this->checkAspectDefinition($basename, $entity, $attrName, $attrDef)
                    );
                }
            }
        }

        $groups = $chunk['groups'] ?? null;
        if (!is_array($groups)) {
            return $errors;
        }

        $modelAttrs = $modelClass !== '' ? $this->activeRecordAttributes($modelClass) : [];
        $versionClass = trim((string) ($chunk['version_model'] ?? ''));
        $versionAttrs = $versionClass !== '' ? $this->activeRecordAttributes($versionClass) : [];
        if ($versionClass !== '') {
            $errors = array_merge($errors, $this->checkModelClass($versionClass, $basename . ' (version_model)'));
        }

        foreach ($groups as $groupKey => $def) {
            if (!is_string($groupKey) || !is_array($def)) {
                continue;
            }
            $fullKey = $entity . '.' . $groupKey;
            $attrNames = $this->normalizeGroupAttributeNames($def);
            foreach ($attrNames as $attrName) {
                $name = trim((string) $attrName);
                if ($name === '') {
                    continue;
                }
                if ($modelAttrs !== [] && !in_array($name, $modelAttrs, true)) {
                    $errors[] = $fullKey . ': atributo «' . $name . '» no existe en ' . $modelClass;
                }
            }

            $versionOnly = is_array($def) && !array_is_list($def) ? ($def['version_attributes'] ?? []) : [];
            if (is_array($versionOnly)) {
                foreach ($versionOnly as $attrName) {
                    $name = trim((string) $attrName);
                    if ($name === '') {
                        continue;
                    }
                    if ($versionAttrs !== [] && !in_array($name, $versionAttrs, true)) {
                        $errors[] = $fullKey . ': version_attribute «' . $name . '» no existe en ' . $versionClass;
                    }
                }
            }

            $uiSource = is_array($def) && !array_is_list($def) ? ($def['ui_json_source'] ?? null) : null;
            if (is_array($uiSource)) {
                $errors = array_merge($errors, $this->checkUiJsonSource($fullKey, $uiSource));
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $uiSource
     * @return list<string>
     */
    private function checkUiJsonSource(string $groupKey, array $uiSource): array
    {
        $errors = [];
        $entity = trim((string) ($uiSource['entity'] ?? ''));
        $action = trim((string) ($uiSource['action'] ?? ''));
        if ($entity === '' || $action === '') {
            $errors[] = $groupKey . ': ui_json_source incompleto';

            return $errors;
        }

        $jsonPath = \Yii::getAlias('@frontend/modules/api/v1/views/json')
            . '/' . str_replace('.', '/', $entity) . '/' . $action . '.json';
        if (!is_file($jsonPath)) {
            $errors[] = $groupKey . ': ui_json no encontrado ' . $jsonPath;

            return $errors;
        }

        try {
            $decoded = Json::decode((string) file_get_contents($jsonPath));
        } catch (\Throwable $e) {
            $errors[] = $groupKey . ': JSON inválido ' . $jsonPath;

            return $errors;
        }

        $jsonFields = $this->extractUiJsonFieldNames($decoded);
        $dbFields = array_keys((new AttributeGroupCatalog())->getEntityGroupFieldDefinitions($groupKey));
        if ($dbFields === []) {
            $errors[] = $groupKey . ': sin filas en data_access_attribute_field (ejecutá migraciones o admin)';

            return $errors;
        }

        foreach ($jsonFields as $fieldName) {
            if (!in_array($fieldName, $dbFields, true)
                && !in_array($fieldName, ['ui_step', 'id_efector', 'id_profesional_efector_servicio', 'id_servicio'], true)) {
                $errors[] = $groupKey . ': campo ui_json «' . $fieldName . '» sin fila en data_access_attribute_field';
            }
        }

        $meta = $decoded['ui_meta']['field_meta'] ?? null;
        if (is_array($meta)) {
            foreach (array_keys($meta) as $metaField) {
                if (!is_string($metaField) || $metaField === 'weekly_scheduler_widget') {
                    continue;
                }
                if (!in_array($metaField, $dbFields, true) && !in_array($metaField, $jsonFields, true)) {
                    $errors[] = $groupKey . ': field_meta «' . $metaField . '» sin campo en BD ni bloque fields';
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @return list<string>
     */
    private function checkAspectDefinition(string $file, string $surfaceId, string $aspectId, array $aspectDef): array
    {
        $errors = [];
        $kind = trim((string) ($aspectDef['kind'] ?? 'field_group'));
        $uiAction = trim((string) ($aspectDef['ui_action'] ?? ''));
        if ($kind === 'field_group' && $uiAction !== '') {
            $kind = 'open_ui';
        }
        $groupKey = trim((string) ($aspectDef['attribute_group'] ?? ''));

        if ($kind === 'open_ui') {
            if ($uiAction === '') {
                $errors[] = $file . ' ' . $surfaceId . '.' . $aspectId . ': open_ui sin ui_action';
            } else {
                [$entity, $action] = $this->splitUiActionId($uiAction);
                $jsonPath = \Yii::getAlias('@frontend/modules/api/v1/views/json')
                    . '/' . str_replace('.', '/', $entity) . '/' . $action . '.json';
                if (!is_file($jsonPath)) {
                    $errors[] = $file . ' ' . $aspectId . ': ui_action sin JSON ' . $jsonPath;
                }
            }
        }

        $fields = $aspectDef['fields'] ?? null;
        if ((!is_array($fields) || $fields === []) && $kind === 'open_ui') {
            $fields = [$aspectId];
        }
        if (is_array($fields) && $fields !== [] && $groupKey !== '') {
            $catalog = new AttributeGroupCatalog();
            $definitions = $catalog->getEntityGroupFieldDefinitions($groupKey);
            foreach ($fields as $fieldName) {
                $name = trim((string) $fieldName);
                if ($name === '') {
                    continue;
                }
                if ($name === 'weekly_scheduler_widget') {
                    continue;
                }
                if (!isset($definitions[$name])) {
                    $errors[] = $file . ' ' . $aspectId . ': field «' . $name . '» no definido en ' . $groupKey;
                }
            }
        }

        $uiFlow = $aspectDef['ui_flow'] ?? null;
        if (is_array($uiFlow)) {
            $policy = trim((string) ($uiFlow['impact_preview_policy'] ?? ''));
            if ($policy !== '' && !in_array($policy, ['never', 'always', 'when_existing_agenda'], true)) {
                $errors[] = $file . ' ' . $aspectId . ': impact_preview_policy inválida «' . $policy . '»';
            }
        }

        return $errors;
    }

    /**
     * @param list<array{path: string, chunk: array<string, mixed>}> $entityFiles
     * @return list<string>
     */
    private function checkAspectsReferenceKnownGroups(array $entityFiles): array
    {
        $known = [];
        foreach ($entityFiles as $row) {
            $entity = trim((string) ($row['chunk']['entity'] ?? ''));
            $groups = $row['chunk']['groups'] ?? [];
            if ($entity === '' || !is_array($groups)) {
                continue;
            }
            foreach ($groups as $groupKey => $def) {
                if (is_string($groupKey)) {
                    $known[$entity . '.' . $groupKey] = true;
                }
            }
        }
        $known['Persona.identidad_basica'] = true;
        $known['ProfesionalEfectorServicioAgenda.configuracion'] = true;

        foreach ($entityFiles as $row) {
            $attributes = $row['chunk']['edit']['attributes'] ?? null;
            if (is_array($attributes)) {
                foreach ($attributes as $attrDef) {
                    if (!is_array($attrDef)) {
                        continue;
                    }
                    $group = trim((string) ($attrDef['attribute_group'] ?? ''));
                    if ($group !== '') {
                        $known[$group] = true;
                    }
                }
            }
        }

        $errors = [];
        foreach ($entityFiles as $row) {
            foreach ($this->iterEditAttributeDefs($row['chunk']) as $attrDef) {
                $group = trim((string) ($attrDef['attribute_group'] ?? ''));
                if ($group !== '' && !isset($known[$group])) {
                    $errors[] = basename($row['path']) . ': attribute_group desconocido «' . $group . '»';
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<array{path: string, chunk: array<string, mixed>}> $entityFiles
     * @return list<string>
     */
    private function checkAttributeFieldsInDatabase(array $entityFiles): array
    {
        $errors = [];
        foreach ($entityFiles as $row) {
            $entity = trim((string) ($row['chunk']['entity'] ?? ''));
            $groups = $row['chunk']['groups'] ?? [];
            if ($entity === '' || !is_array($groups)) {
                continue;
            }
            foreach ($groups as $groupKey => $def) {
                if (!is_string($groupKey) || !is_array($def)) {
                    continue;
                }
                $fullKey = $entity . '.' . $groupKey;
                $hasUiSource = is_array($def['ui_json_source'] ?? null);
                $hasFieldGroupAspect = $this->groupUsedByEditAttribute($entityFiles, $fullKey);
                if (!$hasUiSource && !$hasFieldGroupAspect) {
                    continue;
                }
                if (!DataAccessAttributeField::find()->where(['entity_group_key' => $fullKey, 'active' => 1])->exists()) {
                    $errors[] = 'BD: sin campos activos para «' . $fullKey . '» (data_access_attribute_field)';
                }
            }

            $entityUiSource = $row['chunk']['ui_json_source'] ?? null;
            if (is_array($entityUiSource)) {
                $groupFromSource = trim((string) ($entityUiSource['attribute_group'] ?? ''));
                if ($groupFromSource !== ''
                    && !DataAccessAttributeField::find()->where(['entity_group_key' => $groupFromSource, 'active' => 1])->exists()) {
                    $errors[] = 'BD: sin campos activos para «' . $groupFromSource . '» (data_access_attribute_field)';
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<array{path: string, chunk: array<string, mixed>}> $entityFiles
     */
    private function groupUsedByEditAttribute(array $entityFiles, string $groupKey): bool
    {
        foreach ($entityFiles as $row) {
            foreach ($this->iterEditAttributeDefs($row['chunk']) as $attrDef) {
                if (trim((string) ($attrDef['attribute_group'] ?? '')) !== $groupKey) {
                    continue;
                }
                $uiAction = trim((string) ($attrDef['ui_action'] ?? ''));
                if ($uiAction === '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $chunk
     * @return \Generator<int, array<string, mixed>>
     */
    private function iterEditAttributeDefs(array $chunk): \Generator
    {
        $attributes = $chunk['edit']['attributes'] ?? null;
        if (!is_array($attributes)) {
            return;
        }
        foreach ($attributes as $attrDef) {
            if (is_array($attrDef)) {
                yield $attrDef;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function activeRecordAttributes(string $modelClass): array
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, ActiveRecord::class)) {
            return [];
        }
        /** @var ActiveRecord $model */
        $model = new $modelClass();
        $table = $model::getTableSchema();
        if ($table === null) {
            return [];
        }

        return array_keys($table->columns);
    }

    /**
     * @return list<string>
     */
    private function checkModelClass(string $modelClass, string $context): array
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, ActiveRecord::class)) {
            return [$context . ': model inválido «' . $modelClass . '»'];
        }

        return [];
    }

    /**
     * @param array<string, mixed>|list<mixed> $def
     * @return list<string>
     */
    private function normalizeGroupAttributeNames(array $def): array
    {
        if (array_is_list($def)) {
            return array_map(static fn ($v): string => trim((string) $v), $def);
        }
        $attrs = $def['attributes'] ?? [];
        if (!is_array($attrs)) {
            return [];
        }
        if (array_is_list($attrs)) {
            return array_map(static fn ($v): string => trim((string) $v), $attrs);
        }

        return array_map(static fn ($k): string => trim((string) $k), array_keys($attrs));
    }

    /**
     * @param array<string, mixed> $decoded
     * @return list<string>
     */
    private function extractUiJsonFieldNames(array $decoded): array
    {
        $names = [];
        foreach ($decoded['blocks'] ?? [] as $block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'fields') {
                continue;
            }
            foreach ($block['fields'] ?? [] as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = trim((string) ($field['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
                if (isset($field['value_fields']) && is_array($field['value_fields'])) {
                    foreach ($field['value_fields'] as $vf) {
                        $vfName = trim((string) $vf);
                        if ($vfName !== '') {
                            $names[] = $vfName;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUiActionId(string $actionId): array
    {
        $dot = strpos($actionId, '.');
        if ($dot === false) {
            return [$actionId, 'index'];
        }

        return [
            substr($actionId, 0, $dot),
            substr($actionId, $dot + 1),
        ];
    }
}
