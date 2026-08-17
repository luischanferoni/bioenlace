<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Retira el intent legado profesional-agenda.configurar-propio.
 * El atajo/NL canónico es profesional-horarios.gestionar-propio (AMB | EMER | IMP).
 */
class m260817_120000_drop_profesional_agenda_configurar_propio_intent_rbac extends Migration
{
    private const LEGACY_INTENT = 'profesional-agenda.configurar-propio';
    private const CANONICAL_INTENT = 'profesional-horarios.gestionar-propio';

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->migrateRoleGrants($childTable, self::LEGACY_INTENT, self::CANONICAL_INTENT);
            $this->db->createCommand()->delete($childTable, [
                'or',
                ['child' => self::LEGACY_INTENT],
                ['parent' => self::LEGACY_INTENT],
            ])->execute();
        }

        $this->db->createCommand()->delete($authItem, ['name' => self::LEGACY_INTENT])->execute();

        $this->flushIntentCatalogCache();
        $this->bumpRbacRevision();
    }

    public function safeDown(): void
    {
        // No recrear el intent: el manifiesto YAML ya no existe.
    }

    private function migrateRoleGrants(string $childTable, string $source, string $intentId): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $source])
            ->column($this->db);
        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '' || $parent === $intentId) {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $intentId,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $intentId,
            ])->execute();
        }
    }

    private function flushIntentCatalogCache(): void
    {
        try {
            if (!class_exists(\Yii::class, false) || !\Yii::$app || !\Yii::$app->has('cache')) {
                return;
            }
            $cache = \Yii::$app->cache;
            if ($cache === null) {
                return;
            }
            $cache->delete('yaml_intents_catalog_v6');
            $cache->delete('yaml_intents_catalog_v7');
            $cache->delete('yaml_intents_catalog_v8');
        } catch (\Throwable $e) {
        }
    }

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
        }
    }
}
