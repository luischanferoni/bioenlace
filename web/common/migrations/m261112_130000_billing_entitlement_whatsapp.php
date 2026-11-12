<?php

use yii\db\Migration;

/**
 * Entitlement del add-on WhatsApp por clase de atención.
 */
class m261112_130000_billing_entitlement_whatsapp extends Migration
{
    private const TABLE = '{{%billing_account_encounter_entitlement}}';
    private const COLUMN = 'whatsapp_permitido';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        if ($schema === null || isset($schema->columns[self::COLUMN])) {
            return;
        }

        $this->addColumn(
            self::TABLE,
            self::COLUMN,
            $this->tinyInteger(1)->notNull()->defaultValue(0)
        );
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        if ($schema === null || !isset($schema->columns[self::COLUMN])) {
            return;
        }

        $this->dropColumn(self::TABLE, self::COLUMN);
    }
}
