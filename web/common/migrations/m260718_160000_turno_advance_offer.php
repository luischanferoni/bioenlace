<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Reemplaza lista de espera FIFO por campañas de adelantamiento de turnos.
 */
class m260718_160000_turno_advance_offer extends Migration
{
    private const ACCEPT_ROUTE = '/api/turnos/adelantar-oferta-como-paciente';
    private const CREATE_ROUTE = '/api/turnos/crear-como-paciente';

    /** @var list<string> */
    private const WAITLIST_ROUTES = [
        '/api/turnos/lista-espera-inscribir-como-paciente',
        '/api/turnos/lista-espera-cancelar-como-paciente',
        '/api/turnos/lista-espera-estado-como-paciente',
        '/api/turnos/lista-espera-aceptar-oferta-como-paciente',
    ];

    public function safeUp()
    {
        $this->dropWaitlist();
        $this->createAdvanceTables();
        $this->createSlotClaimTable();
        $this->replaceRbac();
    }

    public function safeDown()
    {
        $this->delete('{{%auth_item_child}}', ['child' => self::ACCEPT_ROUTE]);
        $this->delete('{{%auth_item}}', ['name' => self::ACCEPT_ROUTE]);
        if ($this->db->schema->getTableSchema('{{%turno_slot_claim}}', true) !== null) {
            $this->dropTable('{{%turno_slot_claim}}');
        }
        if ($this->db->schema->getTableSchema('{{%turno_advance_offer}}', true) !== null) {
            $this->dropTable('{{%turno_advance_offer}}');
        }
        if ($this->db->schema->getTableSchema('{{%turno_advance_campaign}}', true) !== null) {
            $this->dropTable('{{%turno_advance_campaign}}');
        }
    }

    private function dropWaitlist(): void
    {
        if ($this->db->schema->getTableSchema('{{%auth_item}}', true) !== null) {
            foreach (self::WAITLIST_ROUTES as $route) {
                $this->delete('{{%auth_item_child}}', ['child' => $route]);
                $this->delete('{{%auth_item}}', ['name' => $route]);
            }
        }
        if ($this->db->schema->getTableSchema('{{%turno_waitlist_entry}}', true) !== null) {
            $this->dropTable('{{%turno_waitlist_entry}}');
        }
        if ($this->db->schema->getTableSchema('{{%turno_waitlist_slot_offer}}', true) !== null) {
            $this->dropTable('{{%turno_waitlist_slot_offer}}');
        }
    }

    private function createAdvanceTables(): void
    {
        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $campaign = '{{%turno_advance_campaign}}';
        if ($this->db->schema->getTableSchema($campaign, true) === null) {
            $this->createTable($campaign, [
                'id' => $this->primaryKey(),
                'id_cancelled_turno' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->notNull(),
                'id_servicio' => $this->integer()->notNull(),
                'id_profesional_efector_servicio' => $this->integer()->notNull(),
                'fecha' => $this->date()->notNull(),
                'hora' => $this->string(8)->notNull(),
                'modalidad' => $this->string(32)->notNull()->defaultValue('presencial'),
                'estado' => $this->string(16)->notNull()->defaultValue('ACTIVE'),
                'current_sequence' => $this->integer()->notNull()->defaultValue(0),
                'next_run_at' => $this->dateTime()->null(),
                'id_turno_filled' => $this->integer()->null(),
                'stop_reason' => $this->string(64)->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->null(),
            ], $opts);
            $this->createIndex('uq_turno_advance_campaign_cancelled', $campaign, 'id_cancelled_turno', true);
            $this->createIndex('ix_turno_advance_campaign_run', $campaign, ['estado', 'next_run_at']);
            $this->createIndex(
                'ix_turno_advance_campaign_slot',
                $campaign,
                ['id_profesional_efector_servicio', 'fecha', 'hora', 'estado']
            );
        }

        $offer = '{{%turno_advance_offer}}';
        if ($this->db->schema->getTableSchema($offer, true) === null) {
            $this->createTable($offer, [
                'id' => $this->primaryKey(),
                'id_campaign' => $this->integer()->notNull(),
                'sequence' => $this->integer()->notNull(),
                'id_turno_candidate' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'offer_token' => $this->string(64)->notNull(),
                'estado' => $this->string(16)->notNull()->defaultValue('PENDING'),
                'notification_ref' => $this->string(64)->null(),
                'offered_at' => $this->dateTime()->notNull(),
                'expires_at' => $this->dateTime()->notNull(),
                'decided_at' => $this->dateTime()->null(),
                'result_detail' => $this->string(128)->null(),
                'created_at' => $this->dateTime()->notNull(),
            ], $opts);
            $this->createIndex('uq_turno_advance_offer_token', $offer, 'offer_token', true);
            $this->createIndex('uq_turno_advance_offer_seq', $offer, ['id_campaign', 'sequence'], true);
            $this->createIndex('ix_turno_advance_offer_campaign_estado', $offer, ['id_campaign', 'estado']);
            $this->createIndex('ix_turno_advance_offer_candidate', $offer, ['id_turno_candidate', 'estado']);
            $this->addForeignKey(
                'fk_turno_advance_offer_campaign',
                $offer,
                'id_campaign',
                $campaign,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    private function createSlotClaimTable(): void
    {
        $table = '{{%turno_slot_claim}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }
        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;
        $this->createTable($table, [
            'id_profesional_efector_servicio' => $this->integer()->notNull(),
            'fecha' => $this->date()->notNull(),
            'hora' => $this->string(8)->notNull(),
            'id_turno' => $this->integer()->notNull(),
            'claimed_at' => $this->dateTime()->notNull(),
        ], $opts);
        $this->addPrimaryKey(
            'pk_turno_slot_claim',
            $table,
            ['id_profesional_efector_servicio', 'fecha', 'hora']
        );
        $this->createIndex('ix_turno_slot_claim_turno', $table, 'id_turno');
        $this->backfillPendingClaims();
    }

    private function backfillPendingClaims(): void
    {
        $turnos = '{{%turnos}}';
        $claims = '{{%turno_slot_claim}}';
        if ($this->db->schema->getTableSchema($turnos, true) === null) {
            return;
        }
        $this->db->createCommand("
            INSERT IGNORE INTO {$claims}
                (id_profesional_efector_servicio, fecha, hora, id_turno, claimed_at)
            SELECT
                t.id_profesional_efector_servicio,
                t.fecha,
                LEFT(TIME_FORMAT(t.hora, '%H:%i'), 5),
                t.id_turnos,
                NOW()
            FROM {$turnos} t
            WHERE t.deleted_at IS NULL
              AND t.estado IN ('PENDIENTE', 'EN_RESOLUCION')
              AND t.id_profesional_efector_servicio IS NOT NULL
              AND t.id_profesional_efector_servicio > 0
              AND t.fecha IS NOT NULL
              AND t.hora IS NOT NULL
              AND t.fecha >= CURDATE()
        ")->execute();
    }

    private function replaceRbac(): void
    {
        $items = '{{%auth_item}}';
        $children = '{{%auth_item_child}}';
        if ($this->db->schema->getTableSchema($items, true) === null
            || $this->db->schema->getTableSchema($children, true) === null) {
            return;
        }
        if (!(new Query())->from($items)->where(['name' => self::ACCEPT_ROUTE])->exists($this->db)) {
            $now = time();
            $this->insert($items, [
                'name' => self::ACCEPT_ROUTE,
                'type' => 3,
                'description' => 'Aceptar oferta de adelantamiento de turno',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $parents = (new Query())->select('parent')->from($children)
            ->where(['child' => self::CREATE_ROUTE])->column($this->db);
        foreach ($parents as $parent) {
            if (!(new Query())->from($children)->where([
                'parent' => $parent,
                'child' => self::ACCEPT_ROUTE,
            ])->exists($this->db)) {
                $this->insert($children, ['parent' => $parent, 'child' => self::ACCEPT_ROUTE]);
            }
        }
    }
}
