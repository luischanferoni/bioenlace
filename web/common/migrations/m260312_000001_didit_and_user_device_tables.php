<?php

use yii\db\Migration;

/**
 * Tablas para integrar Didit (verificación de identidad y autenticación biométrica)
 * y para gestionar múltiples dispositivos por usuario/persona.
 */
class m260312_000001_didit_and_user_device_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // Tabla para registrar verificaciones de Didit (registro inicial, biometric login, etc.)
        if ($this->db->schema->getTableSchema('{{%didit_verification}}', true) === null) {
            $this->createTable('{{%didit_verification}}', [
                'id' => $this->primaryKey(),
                'id_persona' => $this->integer()->null()->comment('Persona asociada cuando se conoce'),
                'didit_verification_id' => $this->string(191)->notNull()->comment('ID de verificación devuelto por Didit'),
                'tipo' => $this->string(50)->notNull()->comment('registro | biometric_login | otro'),
                'status' => $this->string(50)->notNull()->comment('approved | rejected | pending | unknown'),
                'message' => $this->string(255)->null()->comment('Mensaje resumido'),
                'raw_response' => $this->text()->null()->comment('Respuesta cruda de Didit (JSON serializado)'),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ], $tableOptions);

            $this->createIndex('idx_didit_verification_verification_id', '{{%didit_verification}}', 'didit_verification_id', true);
            $this->createIndex('idx_didit_verification_tipo', '{{%didit_verification}}', 'tipo');
            $this->createIndex('idx_didit_verification_status', '{{%didit_verification}}', 'status');

            $this->addForeignKey(
                'fk_didit_verification_persona',
                '{{%didit_verification}}',
                'id_persona',
                '{{%personas}}',
                'id_persona',
                'SET NULL',
                'CASCADE'
            );
        }

        // Tabla para asociar múltiples dispositivos a una persona/usuario
        if ($this->db->schema->getTableSchema('{{%user_device}}', true) === null) {
            $this->createTable('{{%user_device}}', [
                'id' => $this->primaryKey(),
                'id_persona' => $this->integer()->null()->comment('Persona asociada al dispositivo'),
                'id_user' => $this->integer()->null()->comment('Usuario asociado al dispositivo'),
                'device_id' => $this->string(191)->notNull()->comment('Identificador único del dispositivo (UUID, token, fingerprint)'),
                'platform' => $this->string(50)->null()->comment('android | ios | otro'),
                'descripcion' => $this->string(255)->null()->comment('Descripción opcional del dispositivo'),
                'didit_last_verification_id' => $this->string(191)->null()->comment('Última verificación Didit aprobada para este dispositivo'),
                'is_active' => $this->boolean()->notNull()->defaultValue(true)->comment('Si el dispositivo está activo'),
                'last_login_at' => $this->timestamp()->null()->comment('Último login exitoso'),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ], $tableOptions);

            $this->createIndex('idx_user_device_device_id', '{{%user_device}}', 'device_id');
            $this->createIndex('idx_user_device_persona', '{{%user_device}}', 'id_persona');
            $this->createIndex('idx_user_device_user', '{{%user_device}}', 'id_user');
            $this->createIndex('idx_user_device_active', '{{%user_device}}', 'is_active');

            $this->addForeignKey(
                'fk_user_device_persona',
                '{{%user_device}}',
                'id_persona',
                '{{%personas}}',
                'id_persona',
                'SET NULL',
                'CASCADE'
            );

            $this->addForeignKey(
                'fk_user_device_user',
                '{{%user_device}}',
                'id_user',
                '{{%user}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        // Campos opcionales en personas para vincular con Didit
        $personas = $this->db->schema->getTableSchema('{{%personas}}', true);
        if ($personas !== null) {
            if (!isset($personas->columns['didit_reference_id'])) {
                $this->addColumn('{{%personas}}', 'didit_reference_id', $this->string(191)->null()->comment('Referencia de usuario/persona en Didit'));
                $this->createIndex('idx_personas_didit_reference_id', '{{%personas}}', 'didit_reference_id', true);
            }
            if (!isset($personas->columns['didit_last_kyc_verification_id'])) {
                $this->addColumn('{{%personas}}', 'didit_last_kyc_verification_id', $this->string(191)->null()->comment('Última verificación KYC completa (documento + liveness) en Didit'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Revertir cambios en personas
        $personas = $this->db->schema->getTableSchema('{{%personas}}', true);
        if ($personas !== null) {
            if (isset($personas->columns['didit_last_kyc_verification_id'])) {
                $this->dropColumn('{{%personas}}', 'didit_last_kyc_verification_id');
            }
            if (isset($personas->columns['didit_reference_id'])) {
                $this->dropIndex('idx_personas_didit_reference_id', '{{%personas}}');
                $this->dropColumn('{{%personas}}', 'didit_reference_id');
            }
        }

        // Eliminar tabla user_device
        if ($this->db->schema->getTableSchema('{{%user_device}}', true) !== null) {
            $this->dropForeignKey('fk_user_device_user', '{{%user_device}}');
            $this->dropForeignKey('fk_user_device_persona', '{{%user_device}}');
            $this->dropTable('{{%user_device}}');
        }

        // Eliminar tabla didit_verification
        if ($this->db->schema->getTableSchema('{{%didit_verification}}', true) !== null) {
            $this->dropForeignKey('fk_didit_verification_persona', '{{%didit_verification}}');
            $this->dropTable('{{%didit_verification}}');
        }
    }
}

