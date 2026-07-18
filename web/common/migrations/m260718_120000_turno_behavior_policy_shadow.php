<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\Platform\AgentRun;
use yii\db\Migration;
use yii\db\Query;

/**
 * Evidencia reproducible de decisiones que consumen perfiles de turnos.
 */
class m260718_120000_turno_behavior_policy_shadow extends Migration
{
    private const PROFILE_ROUTE = '/api/turnos-perfil/historial-propio-como-paciente';
    private const LIST_ROUTE = '/api/turnos/listar-como-paciente';

    public function safeUp()
    {
        $table = '{{%agent_run}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        $columns = [
            'profile_id' => $this->integer()->null(),
            'profile_contract_version' => $this->string(64)->null(),
            'policy_id' => $this->string(64)->null(),
            'policy_version' => $this->string(64)->null(),
            'policy_hash' => $this->string(64)->null(),
            'execution_mode' => MigrationEnumColumn::mysqlEnum(
                AgentRun::executionModeValues(),
                AgentRun::EXECUTION_SHADOW,
                true,
                'SHADOW|LOW_IMPACT|ENFORCE'
            ),
            'evidence_json' => $this->text()->null(),
            'action_json' => $this->text()->null(),
            'result_json' => $this->text()->null(),
        ];
        foreach ($columns as $name => $definition) {
            if (!isset($schema->columns[$name])) {
                $this->addColumn($table, $name, $definition);
            }
        }
        $this->createIndex('ix_agent_run_profile', $table, ['profile_id']);
        $this->createIndex('ix_agent_run_policy', $table, ['policy_id', 'policy_version']);
        $this->ensureOwnProfileRoute();
    }

    public function safeDown()
    {
        $this->removeOwnProfileRoute();
        $table = '{{%agent_run}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }
        $this->dropIndex('ix_agent_run_policy', $table);
        $this->dropIndex('ix_agent_run_profile', $table);
        foreach ([
            'result_json',
            'action_json',
            'evidence_json',
            'execution_mode',
            'policy_hash',
            'policy_version',
            'policy_id',
            'profile_contract_version',
            'profile_id',
        ] as $column) {
            if (isset($schema->columns[$column])) {
                $this->dropColumn($table, $column);
            }
        }
    }

    private function ensureOwnProfileRoute(): void
    {
        $items = '{{%auth_item}}';
        $children = '{{%auth_item_child}}';
        if ($this->db->schema->getTableSchema($items, true) === null
            || $this->db->schema->getTableSchema($children, true) === null) {
            return;
        }
        if (!(new Query())->from($items)->where(['name' => self::PROFILE_ROUTE])->exists($this->db)) {
            $now = time();
            $this->insert($items, [
                'name' => self::PROFILE_ROUTE,
                'type' => 3,
                'description' => 'Consultar perfil factual propio de turnos',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $parents = (new Query())->select('parent')->from($children)
            ->where(['child' => self::LIST_ROUTE])->column($this->db);
        foreach ($parents as $parent) {
            if (!(new Query())->from($children)->where([
                'parent' => $parent,
                'child' => self::PROFILE_ROUTE,
            ])->exists($this->db)) {
                $this->insert($children, ['parent' => $parent, 'child' => self::PROFILE_ROUTE]);
            }
        }
    }

    private function removeOwnProfileRoute(): void
    {
        if ($this->db->schema->getTableSchema('{{%auth_item}}', true) === null
            || $this->db->schema->getTableSchema('{{%auth_item_child}}', true) === null) {
            return;
        }
        $this->delete('{{%auth_item_child}}', ['child' => self::PROFILE_ROUTE]);
        $this->delete('{{%auth_item}}', ['name' => self::PROFILE_ROUTE]);
    }
}
