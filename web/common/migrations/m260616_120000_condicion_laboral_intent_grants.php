<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Piloto fase 3: permisos intent condicion-laboral + grants desde flujos licencia.
 */
class m260616_120000_condicion_laboral_intent_grants extends Migration
{
    private const PERMISSION_TYPE = 2;

    /** @var array<string, list<string>> */
    private const SOURCE_TO_INTENTS = [
        'licencia.cargar-como-profesional-flow' => ['condicion-laboral.editar-propio'],
        'licencia.cargar-para-profesional-flow' => ['condicion-laboral.editar-staff'],
        'Licencia.create' => [
            'condicion-laboral.editar-propio',
            'condicion-laboral.editar-staff',
        ],
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
