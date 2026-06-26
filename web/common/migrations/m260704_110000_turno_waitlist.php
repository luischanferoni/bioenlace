<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\Scheduling\TurnoWaitlistEntry;
use common\models\Scheduling\TurnoWaitlistSlotOffer;
use yii\db\Migration;

/**
 * Lista de espera ambulatoria (agente A03 v1 FIFO).
 */
class m260704_110000_turno_waitlist extends Migration
{
    public function safeUp()
    {
        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $offer = '{{%turno_waitlist_slot_offer}}';
        if ($this->db->schema->getTableSchema($offer, true) === null) {
            $this->createTable($offer, [
                'id' => $this->primaryKey(),
                'id_cancelled_turno' => $this->integer()->null(),
                'id_efector' => $this->integer()->notNull(),
                'id_servicio' => $this->integer()->notNull(),
                'id_profesional_efector_servicio' => $this->integer()->notNull(),
                'fecha' => $this->date()->notNull(),
                'hora' => $this->time()->notNull(),
                'slot_json' => $this->text()->notNull(),
                'estado' => MigrationEnumColumn::mysqlEnum(
                    TurnoWaitlistSlotOffer::estadoValues(),
                    TurnoWaitlistSlotOffer::ESTADO_PENDING,
                    true,
                    'PENDING|FILLED|EXHAUSTED'
                ),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->null(),
            ], $opts);
            $this->createIndex('ix_tw_slot_offer_estado', $offer, ['estado', 'fecha', 'hora']);
        }

        $entry = '{{%turno_waitlist_entry}}';
        if ($this->db->schema->getTableSchema($entry, true) === null) {
            $this->createTable($entry, [
                'id' => $this->primaryKey(),
                'subject_persona_id' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->notNull(),
                'id_servicio' => $this->integer()->notNull(),
                'id_profesional_efector_servicio' => $this->integer()->null(),
                'urgency_band' => $this->string(8)->null(),
                'estado' => MigrationEnumColumn::mysqlEnum(
                    TurnoWaitlistEntry::estadoValues(),
                    TurnoWaitlistEntry::ESTADO_ACTIVE,
                    true,
                    'ACTIVE|OFFERED|FULFILLED|EXPIRED|CANCELLED'
                ),
                'enrolled_at' => $this->dateTime()->notNull(),
                'slot_offer_id' => $this->integer()->null(),
                'offer_token' => $this->string(64)->null(),
                'offer_expires_at' => $this->dateTime()->null(),
                'id_turno_fulfilled' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->null(),
            ], $opts);
            $this->createIndex(
                'uidx_tw_entry_persona_servicio_active',
                $entry,
                ['subject_persona_id', 'id_efector', 'id_servicio', 'estado']
            );
            $this->createIndex('uidx_tw_entry_offer_token', $entry, ['offer_token'], true);
            $this->createIndex('ix_tw_entry_slot_offer', $entry, ['slot_offer_id', 'estado']);
            $this->addForeignKey(
                'fk_tw_entry_slot_offer',
                $entry,
                'slot_offer_id',
                $offer,
                'id',
                'SET NULL',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%turno_waitlist_entry}}');
        $this->dropTable('{{%turno_waitlist_slot_offer}}');
    }
}
