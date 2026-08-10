<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC web: fragmento HTML del registro de episodio en HC.
 * Hereda grants de /paciente/historia.
 */
class m260810_143000_web_paciente_episodio_timeline_html_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTE = '/paciente/episodio-timeline-html';

    private const INHERIT_FROM = '/paciente/historia';

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260810_143000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260810_143000: sin auth_item, omitido.\n";

            return;
        }

        $now = time();
        if (!(new Query())->from($authItem)->where(['name' => self::ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'Web fragmento registro episodio HC',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => self::ROUTE,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => self::ROUTE,
            ])->execute();
        }
    }

    public function safeDown(): void
    {
        // No revierte grants productivos.
    }
}
