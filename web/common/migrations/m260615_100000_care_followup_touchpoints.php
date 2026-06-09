<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Cola de touchpoints post-consulta + respuestas de evolución.
 */
class m260615_100000_care_followup_touchpoints extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PARENT_ROUTE = '/api/care-packs/assistance';

    /** @var list<string> */
    private const NEW_ROUTES = [
        '/api/care-packs/followup',
    ];

    public function safeUp()
    {
        $binding = '{{%care_encounter_pack}}';
        if ($this->db->schema->getTableSchema($binding, true) !== null) {
            $schema = $this->db->schema->getTableSchema($binding, true);
            if ($schema !== null && !isset($schema->columns['followup_scheduled_at'])) {
                $this->addColumn($binding, 'followup_scheduled_at', $this->dateTime()->null());
            }
        }

        $queue = '{{%care_followup_touchpoint_queue}}';
        if ($this->db->schema->getTableSchema($queue, true) === null) {
            $this->createTable($queue, [
                'id' => $this->primaryKey(),
                'encounter_id' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'touchpoint_key' => $this->string(32)->notNull(),
                'run_at' => $this->dateTime()->notNull(),
                'estado' => $this->string(20)->notNull()->defaultValue('PENDIENTE'),
                'title' => $this->string(255)->notNull(),
                'purpose' => $this->string(32)->notNull()->defaultValue('evolution'),
                'form_kind' => $this->string(32)->notNull()->defaultValue('evolution_short'),
                'education_refs' => $this->text()->null(),
                'followup_pack_id' => $this->integer()->null(),
                'education_pack_id' => $this->integer()->null(),
                'intentos' => $this->integer()->notNull()->defaultValue(0),
                'ultimo_error' => $this->text()->null(),
                'notified_at' => $this->dateTime()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_care_followup_tp_enc_key', $queue, ['encounter_id', 'touchpoint_key'], true);
            $this->createIndex('ix_care_followup_tp_run', $queue, ['estado', 'run_at']);
            $this->addForeignKey(
                'fk_care_followup_tp_encounter',
                $queue,
                'encounter_id',
                '{{%encounter}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $response = '{{%care_followup_response}}';
        if ($this->db->schema->getTableSchema($response, true) === null) {
            $this->createTable($response, [
                'id' => $this->primaryKey(),
                'touchpoint_queue_id' => $this->integer()->notNull(),
                'encounter_id' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'touchpoint_key' => $this->string(32)->notNull(),
                'answers_json' => $this->text()->notNull(),
                'submitted_at' => $this->dateTime()->notNull(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_care_followup_resp_tp', $response, 'touchpoint_queue_id', true);
            $this->createIndex('ix_care_followup_resp_enc', $response, 'encounter_id');
            $this->addForeignKey(
                'fk_care_followup_resp_queue',
                $response,
                'touchpoint_queue_id',
                $queue,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::NEW_ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API care-packs: seguimiento post-consulta paciente',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
            if ($hasChild) {
                $parents = (new Query())
                    ->select('parent')
                    ->from($childTable)
                    ->where(['child' => self::PARENT_ROUTE])
                    ->column($this->db);
                foreach ($parents as $parent) {
                    if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $route])->exists($this->db)) {
                        continue;
                    }
                    $this->db->createCommand()->insert($childTable, [
                        'parent' => $parent,
                        'child' => $route,
                    ])->execute();
                }
            }
        }
    }

    public function safeDown()
    {
        $response = '{{%care_followup_response}}';
        if ($this->db->schema->getTableSchema($response, true) !== null) {
            $this->dropForeignKey('fk_care_followup_resp_queue', $response);
            $this->dropTable($response);
        }

        $queue = '{{%care_followup_touchpoint_queue}}';
        if ($this->db->schema->getTableSchema($queue, true) !== null) {
            $this->dropForeignKey('fk_care_followup_tp_encounter', $queue);
            $this->dropTable($queue);
        }

        $binding = '{{%care_encounter_pack}}';
        if ($this->db->schema->getTableSchema($binding, true) !== null) {
            $schema = $this->db->schema->getTableSchema($binding, true);
            if ($schema !== null && isset($schema->columns['followup_scheduled_at'])) {
                $this->dropColumn($binding, 'followup_scheduled_at');
            }
        }

        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            foreach (self::NEW_ROUTES as $route) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
        }
        foreach (self::NEW_ROUTES as $route) {
            $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
        }
    }
}
