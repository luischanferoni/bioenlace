<?php

use common\components\Shared\Infrastructure\Migration\MigrationEnumColumn;
use common\models\Organization\SignupPairing;
use yii\db\Migration;

/**
 * Vínculo de un solo uso entre la computadora que inicia el alta web y el teléfono
 * que verifica la identidad (QR + código de emparejamiento).
 *
 * Guarda sólo hashes de token y código. Estados en ENUM con las constantes del AR
 * como única fuente de verdad.
 */
class m261111_150000_signup_pairing extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%signup_pairing}}', true) !== null) {
            return;
        }

        $tableOptions = null;
        if (in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%signup_pairing}}', [
            'id' => $this->char(32)->notNull(),
            'token_hash' => $this->char(64)->notNull(),
            'code_hash' => $this->char(64)->notNull(),
            'code_attempts' => $this->tinyInteger()->notNull()->defaultValue(0),
            'id_efector' => $this->integer()->null(),
            'web_session_hash' => $this->char(64)->notNull(),
            'device_session_hash' => $this->char(64)->null(),
            'state' => MigrationEnumColumn::mysqlEnum(
                SignupPairing::stateValues(),
                SignupPairing::STATE_PENDING,
                true,
                'PENDING|CLAIMED|AUTHORIZED|VERIFICATION_STARTED|VERIFIED|ACCOUNT_CREATED|CANCELLED|EXPIRED|FAILED'
            ),
            'didit_session_id' => $this->string(128)->null(),
            'verification_id' => $this->string(128)->null(),
            'id_persona' => $this->integer()->null(),
            'id_user' => $this->integer()->null(),
            'expires_at' => $this->integer()->notNull(),
            'claimed_at' => $this->integer()->null(),
            'authorized_at' => $this->integer()->null(),
            'verified_at' => $this->integer()->null(),
            'completed_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY ([[id]])',
        ], $tableOptions);

        $this->createIndex('uq_signup_pairing_token', '{{%signup_pairing}}', 'token_hash', true);
        $this->createIndex('idx_signup_pairing_state_expiry', '{{%signup_pairing}}', ['state', 'expires_at']);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%signup_pairing}}', true) !== null) {
            $this->dropTable('{{%signup_pairing}}');
        }
    }
}
