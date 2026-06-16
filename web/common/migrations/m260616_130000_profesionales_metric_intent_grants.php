<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Fase 3: permisos intent profesionales (conteo/listado) + grants desde DataAccess.
 */
class m260616_130000_profesionales_metric_intent_grants extends Migration
{
    private const PERMISSION_TYPE = 2;

    /** @var array<string, list<string>> */
    private const SOURCE_TO_INTENTS = [
        'data-access.info' => ['profesionales.conteo-efector'],
        'DataAccess.info' => ['profesionales.conteo-efector'],
        'data-access.listar' => ['profesionales.listado-efector'],
        'DataAccess.list' => ['profesionales.listado-efector'],
    ];

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
        $intentIds = [];
        foreach (self::SOURCE_TO_INTENTS as $intents) {
            foreach ($intents as $intentId) {
                $intentIds[$intentId] = true;
            }
        }

        foreach (array_keys($intentIds) as $intentId) {
            $this->ensurePermission($authItem, $intentId, $now);
        }

        foreach (self::SOURCE_TO_INTENTS as $source => $intents) {
            foreach ($intents as $intentId) {
                $this->migrateRoleGrants($childTable, $source, $intentId);
            }
        }
    }

    public function safeDown()
    {
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

    private function migrateRoleGrants(string $childTable, string $source, string $intentId): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $source])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $intentId])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $intentId,
            ])->execute();
        }
    }
}
