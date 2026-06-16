<?php

namespace common\components\Platform\Core\Permission\Validation;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Core\DataAccess\Validation\DataAccessCatalogCheckService;
use common\components\Platform\Core\Permission\IntentFamilyCatalog;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentManifestMetadata;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyRegistry;
use common\components\Platform\Core\Permission\PermissionCatalogService;
use common\components\Platform\Ui\OpenUiStaticTemplatePolicy;
use common\components\Platform\Ui\UiJsonDomain;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Integridad del catálogo de permisos: intents, atributos, pasos open_ui y alineación RBAC.
 */
final class CatalogIntegrityService
{
    /**
     * @return array{errors: list<string>, warnings: list<string>, summary: array<string, int>}
     */
    public function run(): array
    {
        IntentManifestIndex::resetCache();
        IntentSchemaPaths::resetIndexCache();
        IntentFamilyCatalog::resetCache();

        $errors = (new DataAccessCatalogCheckService())->run();
        $warnings = [];

        $errors = array_merge($errors, $this->checkDuplicateIntentIds());
        $errors = array_merge($errors, $this->checkIntentsHavePermissionOrRoute());
        $errors = array_merge($errors, $this->checkIntentExtendedMetadata());
        $warnings = array_merge($warnings, $this->checkIntentExtendedMetadataWarnings());
        $errors = array_merge($errors, $this->checkIntentFamilies());
        $warnings = array_merge($warnings, $this->checkIntentFamilyOrphans());
        $warnings = array_merge($warnings, $this->checkLegacyAttributeGrantsInAuthItem());
        $errors = array_merge($errors, $this->checkOpenUiActionIdsResolve());
        $warnings = array_merge($warnings, $this->checkOpenUiSeparateRbacDebt());
        $warnings = array_merge($warnings, $this->checkEditAttributesVsFlowOnly());
        $warnings = array_merge($warnings, $this->checkFlowOnlyAttributesInEdit());
        $warnings = array_merge($warnings, $this->checkIntentCategoryFolder());
        $warnings = array_merge($warnings, $this->checkAttributesPresentationGroups());
        $errors = array_merge($errors, $this->checkDomainOperationPolicyHandlers());
        $errors = array_merge($errors, $this->checkDomainOperationEmptyPolicies());
        $warnings = array_merge($warnings, $this->checkDomainOperationsCatalogCoverage());
        $warnings = array_merge($warnings, $this->checkLogicalPermissionRoutePollution());

        $errors = array_values(array_unique($errors));
        $warnings = array_values(array_unique($warnings));
        sort($errors);
        sort($warnings);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'errors' => count($errors),
                'warnings' => count($warnings),
                'intents' => count(IntentManifestIndex::all()),
                'intent_families' => count(IntentFamilyCatalog::all()),
                'extended_intents' => count(array_filter(
                    IntentManifestIndex::all(),
                    static fn (array $meta): bool => (bool) ($meta['uses_extended_contract'] ?? false)
                )),
                'attributes' => count((new PermissionCatalogService())->listAttributes()),
                'flow_steps' => count((new PermissionCatalogService())->listFlowStepDependencies()),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function checkDuplicateIntentIds(): array
    {
        $errors = [];
        $byId = [];
        foreach (IntentSchemaPaths::discoverYamlFiles() as $path) {
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($data)) {
                continue;
            }
            $intentId = trim((string) ($data['intent_id'] ?? IntentSchemaPaths::intentIdFromPath($path)));
            if ($intentId === '') {
                continue;
            }
            $byId[$intentId][] = $path;
        }

        foreach ($byId as $intentId => $paths) {
            if (count($paths) <= 1) {
                continue;
            }
            $errors[] = 'Intent duplicado «' . $intentId . '»: ' . implode(', ', array_map('basename', $paths));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkIntentsHavePermissionOrRoute(): array
    {
        $errors = [];
        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $rbac = trim((string) ($meta['rbac_route'] ?? ''));
            if ($rbac === '') {
                $errors[] = 'Intent «' . $intentId . '»: falta rbac_route';
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkOpenUiActionIdsResolve(): array
    {
        $errors = [];
        foreach ((new PermissionCatalogService())->listFlowStepDependencies() as $row) {
            $actionId = trim((string) ($row['action_id'] ?? ''));
            if ($actionId === '') {
                continue;
            }
            if (!OpenUiStaticTemplatePolicy::requiresStaticTemplateFile($actionId)) {
                continue;
            }
            $jsonPath = UiJsonDomain::resolveActionIdTemplatePath($actionId);
            if ($jsonPath === null || !is_file($jsonPath)) {
                $errors[] = 'open_ui «' . $actionId . '» (intent «' . ($row['intent_id'] ?? '') . '»): ui_json no encontrado';
            }
        }

        return $errors;
    }

    /**
     * Rutas con RBAC propio distinto al intent padre — deuda hasta migración completa a herencia.
     *
     * @return list<string>
     */
    private function checkOpenUiSeparateRbacDebt(): array
    {
        $warnings = [];
        foreach ((new PermissionCatalogService())->listFlowStepDependencies() as $row) {
            $route = trim((string) ($row['api_route'] ?? ''));
            $intentRoute = '';
            $meta = IntentManifestIndex::get((string) ($row['intent_id'] ?? ''));
            if ($meta !== null) {
                $intentRoute = trim((string) ($meta['rbac_route'] ?? ''));
            }
            if ($route === '' || $intentRoute === '') {
                continue;
            }
            if ($route === $intentRoute) {
                continue;
            }
            $warnings[] = 'Paso «' . ($row['action_id'] ?? '') . '» tiene ruta ' . $route
                . ' distinta al intent «' . ($row['intent_id'] ?? '') . '» (' . $intentRoute . '); herencia flow_step la cubre si el rol tiene el intent';
        }

        return $warnings;
    }

    /**
     * Atributos en edit disperso que también aparecen en flow_submit de algún intent.
     *
     * @return list<string>
     */
    private function checkEditAttributesVsFlowOnly(): array
    {
        $warnings = [];
        $flowFields = $this->collectFlowMutationFieldNames();
        $catalog = new PermissionCatalogService();
        foreach ($catalog->listAttributes() as $row) {
            if (($row['operation'] ?? '') !== 'edit') {
                continue;
            }
            if (($row['kind'] ?? '') === 'attribute_edit_flow') {
                continue;
            }
            $attr = trim((string) ($row['attribute'] ?? ''));
            $entity = trim((string) ($row['entity'] ?? ''));
            if ($attr === '' || $entity === '') {
                continue;
            }
            $key = strtolower($entity . '.' . $attr);
            if (isset($flowFields[$key])) {
                $warnings[] = 'Atributo edit «' . $entity . '.' . $attr . '» también mutado por intent «'
                    . $flowFields[$key] . '»; revisar que no deba ser solo flow';
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, string> entity.attribute (lower) => intent_id
     */
    private function collectFlowMutationFieldNames(): array
    {
        $out = [];
        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $flowSubmit = $meta['flow_submit'] ?? null;
            if (!is_array($flowSubmit)) {
                continue;
            }
            $params = $flowSubmit['params'] ?? null;
            if (!is_array($params)) {
                continue;
            }
            foreach (array_keys($params) as $paramName) {
                $name = trim((string) $paramName);
                if ($name === '' || $name === 'id') {
                    continue;
                }
                $normalized = preg_replace('/^draft\./', '', $name);
                $out[strtolower($normalized)] = $intentId;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function checkFlowOnlyAttributesInEdit(): array
    {
        $warnings = [];
        $flowOnly = $this->collectFlowOnlyAttributeKeys();
        $catalog = new PermissionCatalogService();
        foreach ($catalog->listAttributes() as $row) {
            if (($row['operation'] ?? '') !== 'edit') {
                continue;
            }
            $entity = trim((string) ($row['entity'] ?? ''));
            $attr = trim((string) ($row['attribute'] ?? ''));
            if ($entity === '' || $attr === '') {
                continue;
            }
            if (isset($flowOnly[strtolower($entity . '.' . $attr)])) {
                $warnings[] = 'Atributo edit «' . $entity . '.' . $attr . '» está en flow_only_attributes; debe mutarse solo vía intent';
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, true>
     */
    private function collectFlowOnlyAttributeKeys(): array
    {
        $out = [];
        $dir = realpath(dirname(__DIR__, 2) . '/DataAccess/schemas/data-access-config');
        if ($dir === false) {
            return $out;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $path) {
            if (basename($path) === 'manifest.yaml') {
                continue;
            }
            try {
                $chunk = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($chunk)) {
                continue;
            }
            $entity = trim((string) ($chunk['entity'] ?? ''));
            $list = $chunk['flow_only_attributes'] ?? null;
            if ($entity === '' || !is_array($list)) {
                continue;
            }
            foreach ($list as $attrName) {
                $name = trim((string) $attrName);
                if ($name !== '') {
                    $out[strtolower($entity . '.' . $name)] = true;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function checkIntentCategoryFolder(): array
    {
        $warnings = [];
        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $category = $meta['category'] ?? null;
            if ($category === null) {
                $warnings[] = 'Intent «' . $intentId . '» en raíz de schemas/intents; mover a create/read/update/delete';
            }
        }

        return $warnings;
    }

    /**
     * Grupos de presentación deben referenciar atributos declarados (cuando exista bloque attributes).
     *
     * @return list<string>
     */
    private function checkAttributesPresentationGroups(): array
    {
        $warnings = [];
        $dir = realpath(dirname(__DIR__, 2) . '/DataAccess/schemas/data-access-config');
        if ($dir === false) {
            return $warnings;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $path) {
            if (basename($path) === 'manifest.yaml') {
                continue;
            }
            try {
                $chunk = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($chunk)) {
                continue;
            }
            $entity = trim((string) ($chunk['entity'] ?? ''));
            $attributes = $chunk['attributes'] ?? null;
            $groups = $chunk['groups'] ?? null;
            if ($entity === '' || !is_array($attributes) || !is_array($groups)) {
                continue;
            }
            $known = array_fill_keys(array_keys($attributes), true);
            $flowOnly = $chunk['flow_only_attributes'] ?? null;
            if (is_array($flowOnly)) {
                foreach ($flowOnly as $attrName) {
                    $name = trim((string) $attrName);
                    if ($name !== '') {
                        $known[$name] = true;
                    }
                }
            }
            foreach ($groups as $groupKey => $def) {
                if (!is_array($def)) {
                    continue;
                }
                $attrNames = array_is_list($def)
                    ? $def
                    : (is_array($def['attributes'] ?? null) ? $def['attributes'] : []);
                foreach ($attrNames as $attrName) {
                    $name = trim((string) $attrName);
                    if ($name !== '' && !isset($known[$name])) {
                        $warnings[] = $entity . ': grupo «' . $groupKey . '» referencia atributo «' . $name . '» no declarado en attributes';
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function checkDomainOperationPolicyHandlers(): array
    {
        $errors = [];
        $known = array_flip(DomainOperationPolicyRegistry::knownHandlerIds());
        $configFile = ProductMetadataPaths::domainOperationPoliciesFile();
        if (!is_file($configFile)) {
            return ['domain-operation-policies.yaml no encontrado'];
        }

        try {
            $parsed = Yaml::parseFile($configFile);
        } catch (\Throwable $e) {
            return ['domain-operation-policies.yaml ilegible: ' . $e->getMessage()];
        }

        $operations = is_array($parsed['operations'] ?? null) ? $parsed['operations'] : [];
        foreach ($operations as $operationKey => $def) {
            if (!is_array($def)) {
                continue;
            }
            foreach (['policies', 'any_of'] as $listKey) {
                if (!isset($def[$listKey]) || !is_array($def[$listKey])) {
                    continue;
                }
                foreach ($def[$listKey] as $handlerId) {
                    $handlerId = trim((string) $handlerId);
                    if ($handlerId === '') {
                        continue;
                    }
                    if (!isset($known[$handlerId])) {
                        $errors[] = 'domain-operation-policies: «' . $operationKey . '» referencia handler «'
                            . $handlerId . '» no registrado en DomainOperationPolicyRegistry';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Operaciones declaradas sin políticas (any_of / policies vacíos).
     *
     * @return list<string>
     */
    private function checkDomainOperationEmptyPolicies(): array
    {
        $errors = [];
        $configFile = ProductMetadataPaths::domainOperationPoliciesFile();
        if (!is_file($configFile)) {
            return [];
        }

        try {
            $parsed = Yaml::parseFile($configFile);
        } catch (\Throwable $e) {
            return [];
        }

        $operations = is_array($parsed['operations'] ?? null) ? $parsed['operations'] : [];
        foreach ($operations as $operationKey => $def) {
            if (!is_array($def)) {
                continue;
            }
            $anyOf = is_array($def['any_of'] ?? null) ? $def['any_of'] : [];
            $policies = is_array($def['policies'] ?? null) ? $def['policies'] : [];
            if ($anyOf === [] && $policies === []) {
                $errors[] = 'domain-operation-policies: «' . $operationKey . '» no define policies ni any_of';
            }
        }

        return $errors;
    }

    /**
     * Operaciones de dominio que deberían existir en catálogo declarativo (intent/atributo).
     *
     * @return list<string>
     */
    private function checkDomainOperationsCatalogCoverage(): array
    {
        $warnings = [];
        $configFile = ProductMetadataPaths::domainOperationPoliciesFile();
        if (!is_file($configFile)) {
            return [];
        }

        try {
            $parsed = Yaml::parseFile($configFile);
        } catch (\Throwable $e) {
            return [];
        }

        $domainOnly = [];
        foreach ($parsed['domain_only_operations'] ?? [] as $item) {
            $key = trim((string) $item);
            if ($key !== '') {
                $domainOnly[$key] = true;
            }
        }

        $catalogKeys = [];
        $catalog = new PermissionCatalogService();
        foreach ($catalog->listIntents() as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $catalogKeys[$key] = true;
            }
        }
        foreach (IntentManifestIndex::all() as $meta) {
            $domainOp = trim((string) ($meta['domain_operation'] ?? ''));
            if ($domainOp !== '') {
                $catalogKeys[$domainOp] = true;
            }
        }
        foreach ($catalog->listAttributes() as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $catalogKeys[$key] = true;
            }
        }

        $operations = is_array($parsed['operations'] ?? null) ? $parsed['operations'] : [];
        foreach (array_keys($operations) as $operationKey) {
            $operationKey = trim((string) $operationKey);
            if ($operationKey === '' || isset($domainOnly[$operationKey]) || isset($catalogKeys[$operationKey])) {
                continue;
            }
            $warnings[] = 'domain-operation-policies: «' . $operationKey
                . '» no está en catálogo ni en domain_only_operations; agregar intent/permission o marcarla como solo dominio';
        }

        return $warnings;
    }

    /**
     * Permisos lógicos del catálogo no deben apuntar a rutas ajenas a su intent (contaminación ghost).
     *
     * @return list<string>
     */
    private function checkLogicalPermissionRoutePollution(): array
    {
        $warnings = [];
        $childTable = Yii::$app->db->schema->getTableSchema('{{%auth_item_child}}', true);
        if ($childTable === null) {
            return [];
        }

        /** @var array<string, list<string>> */
        $allowedRoutes = [
            'Internacion.discharge' => ['/api/clinical/episode-of-care/by-internacion'],
            'Internacion.change_bed' => ['/api/clinical/episode-of-care/by-internacion'],
        ];

        foreach ($allowedRoutes as $permission => $allowed) {
            $allowedFlip = array_flip($allowed);
            $children = (new \yii\db\Query())
                ->select('child')
                ->from('{{%auth_item_child}}')
                ->where(['parent' => $permission])
                ->column();
            foreach ($children as $route) {
                if (!is_string($route) || isset($allowedFlip[$route])) {
                    continue;
                }
                $warnings[] = 'RBAC: «' . $permission . '» enlazado a ruta «' . $route
                    . '» fuera del rbac_route del intent; revisar auth_item_child';
            }
        }

        return $warnings;
    }

    /**
     * Contrato extendido read/list/edit: domain_operation, fields, field_groups.
     *
     * @return list<string>
     */
    private function checkIntentExtendedMetadata(): array
    {
        $errors = [];
        foreach (IntentSchemaPaths::discoverYamlFiles() as $path) {
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($data)) {
                continue;
            }
            $intentId = trim((string) ($data['intent_id'] ?? IntentSchemaPaths::intentIdFromPath($path)));
            if ($intentId === '') {
                continue;
            }
            $category = IntentSchemaPaths::categoryFromPath($path);
            $result = IntentManifestMetadata::validate($intentId, $category, $data);
            $errors = array_merge($errors, $result['errors']);
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkIntentExtendedMetadataWarnings(): array
    {
        $warnings = [];
        foreach (IntentSchemaPaths::discoverYamlFiles() as $path) {
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($data)) {
                continue;
            }
            $intentId = trim((string) ($data['intent_id'] ?? IntentSchemaPaths::intentIdFromPath($path)));
            if ($intentId === '') {
                continue;
            }
            $category = IntentSchemaPaths::categoryFromPath($path);
            $result = IntentManifestMetadata::validate($intentId, $category, $data);
            $warnings = array_merge($warnings, $result['warnings']);
        }

        return $warnings;
    }

    /**
     * Familias declaradas en intent-families.yaml ↔ manifiestos en disco.
     *
     * @return list<string>
     */
    private function checkIntentFamilies(): array
    {
        $errors = [];
        foreach (IntentFamilyCatalog::all() as $familyId => $def) {
            $expectedOperation = trim((string) ($def['operation'] ?? ''));
            foreach ($def['members'] as $memberId) {
                $meta = IntentManifestIndex::get($memberId);
                if ($meta === null) {
                    $errors[] = 'Familia «' . $familyId . '»: miembro «' . $memberId . '» sin YAML de intent';

                    continue;
                }
                $memberFamily = trim((string) ($meta['intent_family'] ?? ''));
                if ($memberFamily !== $familyId) {
                    $errors[] = 'Familia «' . $familyId . '»: «' . $memberId . '» declara intent_family «'
                        . ($memberFamily !== '' ? $memberFamily : '(vacío)') . '»';
                }
                $memberOperation = trim((string) ($meta['operation'] ?? ''));
                if ($expectedOperation !== '' && $memberOperation !== '' && $memberOperation !== $expectedOperation) {
                    $errors[] = 'Familia «' . $familyId . '»: «' . $memberId . '» tiene operation «'
                        . $memberOperation . '»; se esperaba «' . $expectedOperation . '»';
                }
            }
        }

        return $errors;
    }

    /**
     * Intents con intent_family no listados en intent-families.yaml.
     *
     * @return list<string>
     */
    private function checkIntentFamilyOrphans(): array
    {
        $warnings = [];
        $knownFamilies = array_fill_keys(array_keys(IntentFamilyCatalog::all()), true);

        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $family = trim((string) ($meta['intent_family'] ?? ''));
            if ($family === '') {
                continue;
            }
            if (!isset($knownFamilies[$family])) {
                $warnings[] = 'Intent «' . $intentId . '»: intent_family «' . $family . '» no está en intent-families.yaml';
            }
        }

        return $warnings;
    }

    /**
     * Convivencia: grants atómicos Entidad.atributo.{read|info|edit} aún en auth_item.
     *
     * @return list<string>
     */
    private function checkLegacyAttributeGrantsInAuthItem(): array
    {
        $warnings = [];
        if (!Yii::$app->has('db')) {
            return $warnings;
        }

        $itemTable = Yii::$app->db->schema->getTableSchema('{{%auth_item}}', true);
        if ($itemTable === null) {
            return $warnings;
        }

        $catalogKeys = [];
        foreach ((new PermissionCatalogService())->listAttributes() as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $catalogKeys[$key] = true;
            }
        }

        $rows = (new \yii\db\Query())
            ->select(['name', 'type'])
            ->from('{{%auth_item}}')
            ->where(['type' => 2])
            ->all();

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || !isset($catalogKeys[$name])) {
                continue;
            }
            $warnings[] = 'RBAC legacy: grant atributo «' . $name . '» aún en auth_item (migrar a intents)';
        }

        return $warnings;
    }
}
