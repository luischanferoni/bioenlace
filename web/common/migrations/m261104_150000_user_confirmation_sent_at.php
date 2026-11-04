<?php

use yii\db\Migration;

/**
 * Registra cuándo se envió por última vez el mail de confirmación, para
 * aplicar un cooldown de reenvío desde el alta comercial.
 */
class m261104_150000_user_confirmation_sent_at extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%user}}', true);
        if ($schema === null || $schema->getColumn('confirmation_sent_at') !== null) {
            return;
        }

        $this->addColumn('{{%user}}', 'confirmation_sent_at', $this->dateTime()->null());
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%user}}', true);
        if ($schema === null || $schema->getColumn('confirmation_sent_at') === null) {
            return;
        }

        $this->dropColumn('{{%user}}', 'confirmation_sent_at');
    }
}
