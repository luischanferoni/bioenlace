<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Fase 3: permiso intent distribución profesionales por servicio + grants desde DataAccess.info.
 */
class m260616_150000_profesionales_distribucion_metric_intent_grants extends Migration
{
    private const PERMISSION_TYPE = 2;

    private const INTENT_ID = 'profesionales.distribucion-servicio-efector';

    /** @var list<string> */
    private const SOURCE_PERMISSIONS = [
        'data-access.info',
        'DataAccess.info',
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
        $this->ensurePermission($authItem, self::INTENT_ID, $now);

        foreach (self::SOURCE_PERMISSIONS as $source) {
            $this->migrateRoleGrants($childTable, $source, self::INTENT_ID);
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
