<?php

use yii\db\Migration;

/**
 * Invitación de cuentas staff: activación por email o código presencial.
 */
class m260705_100000_user_account_invitation extends Migration
{
    public function safeUp()
    {
        $userTable = '{{%user}}';
        $schema = $this->db->schema->getTableSchema($userTable, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['password_set_at'])) {
            $this->addColumn($userTable, 'password_set_at', $this->integer()->null()->comment('Unix: primera contraseña elegida por el usuario'));
        }
        if (!isset($schema->columns['activation_code_hash'])) {
            $this->addColumn($userTable, 'activation_code_hash', $this->string(255)->null());
        }
        if (!isset($schema->columns['activation_code_expires_at'])) {
            $this->addColumn($userTable, 'activation_code_expires_at', $this->integer()->null());
        }

        $now = time();
        $this->db->createCommand(
            'UPDATE {{%user}} SET password_set_at = COALESCE(updated_at, created_at, :now) WHERE password_set_at IS NULL',
            [':now' => $now]
        )->execute();

        if ($this->db->schema->getTableSchema('{{%user_account_invitation_log}}', true) === null) {
            $tableOptions = null;
            if ($this->db->driverName === 'mysql') {
                $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
            }

            $this->createTable('{{%user_account_invitation_log}}', [
                'id' => $this->primaryKey(),
                'id_user' => $this->integer()->notNull(),
                'action' => $this->string(32)->notNull(),
                'id_actor_user' => $this->integer()->null(),
                'meta' => $this->text()->null(),
                'created_at' => $this->integer()->notNull(),
            ], $tableOptions);

            $this->createIndex('idx_user_invitation_log_user', '{{%user_account_invitation_log}}', 'id_user');
            $this->createIndex('idx_user_invitation_log_created', '{{%user_account_invitation_log}}', 'created_at');

            $this->addForeignKey(
                'fk_user_invitation_log_user',
                '{{%user_account_invitation_log}}',
                'id_user',
                '{{%user}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%user_account_invitation_log}}', true) !== null) {
            $this->dropTable('{{%user_account_invitation_log}}');
        }

        $userTable = '{{%user}}';
        $schema = $this->db->schema->getTableSchema($userTable, true);
        if ($schema === null) {
            return;
        }

        if (isset($schema->columns['activation_code_expires_at'])) {
            $this->dropColumn($userTable, 'activation_code_expires_at');
        }
        if (isset($schema->columns['activation_code_hash'])) {
            $this->dropColumn($userTable, 'activation_code_hash');
        }
        if (isset($schema->columns['password_set_at'])) {
            $this->dropColumn($userTable, 'password_set_at');
        }
    }
}
