<?php

use yii\db\Migration;

/**
 * Cierre legacy DataAccess: grants migrados a auth_item (m260622); tabla histórica ya inactiva.
 */
class m260627_100000_drop_data_access_role_grant_table extends Migration
{
    private const TABLE = '{{%data_access_role_grant}}';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            echo "m260627_100000: tabla ya eliminada.\n";

            return;
        }

        $this->dropTable(self::TABLE);
        echo "m260627_100000: data_access_role_grant eliminada.\n";
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            echo "m260627_100000: safeDown omitido (tabla existe).\n";

            return;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->unsigned(),
            'role_name' => $this->string(64)->notNull(),
            'entity_group_key' => $this->string(128)->notNull(),
            'operations_csv' => $this->string(255)->notNull(),
            'scope_checker' => $this->string(64)->null(),
            'active' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'notas' => $this->text()->null(),
        ]);

        $this->createIndex(
            'uq_data_access_role_grant_role_group',
            self::TABLE,
            ['role_name', 'entity_group_key'],
            true
        );

        echo "m260627_100000: tabla recreada vacía (sin re-seed histórico).\n";
    }
}
