<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC intent: turnos.ver-turnos-anteriores-como-paciente (historial corto en asistente).
 */
class m260722_150000_turnos_ver_anteriores_paciente_intent_rbac extends Migration
{
    private const PERMISSION_TYPE = 2;

    private const INTENT_ID = 'turnos.ver-turnos-anteriores-como-paciente';

    private const INTENT_PARENT = 'turnos.ver-mis-turnos-como-paciente';

    private const LISTAR_ROUTE = '/api/turnos/listar-como-paciente';

    private const ROLE_PACIENTE = 'paciente';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        $this->ensurePermission($authItem, self::INTENT_ID, $now);
        $this->inheritFrom($childTable, self::INTENT_PARENT, self::INTENT_ID);
        $this->ensureChild($childTable, self::ROLE_PACIENTE, self::INTENT_ID);
        $this->ensureChild($childTable, self::INTENT_ID, self::LISTAR_ROUTE);

        $this->flushIntentCatalogCache();
        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $this->db->createCommand()->delete($childTable, [
            'or',
            ['child' => self::INTENT_ID],
            ['parent' => self::INTENT_ID, 'child' => self::LISTAR_ROUTE],
        ])->execute();
        $this->db->createCommand()->delete($authItem, ['name' => self::INTENT_ID])->execute();
        $this->flushIntentCatalogCache();
        $this->bumpRbacRevision();
    }

    private function ensurePermission(string $authItem, string $intentId, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $intentId,
            'type' => self::PERMISSION_TYPE,
            'description' => 'Intent ' . $intentId,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentChild, string $newChild): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentChild])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            $this->ensureChild($childTable, $parent, $newChild);
        }
    }

    private function ensureChild(string $childTable, string $parent, string $child): void
    {
        if ((new Query())->from($childTable)->where([
            'parent' => $parent,
            'child' => $child,
        ])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();
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
        } catch (\Throwable $e) {
            // Migración no debe fallar por cache.
        }
    }

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class, false)
                || class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
            // Migración no debe fallar por cache de revisión.
        }
    }
}
