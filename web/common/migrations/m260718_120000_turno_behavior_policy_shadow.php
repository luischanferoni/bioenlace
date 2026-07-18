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
    private const REPRESENTED_ROUTE = '/api/turnos-perfil/historial-representado-como-paciente';
    private const EXPLAIN_ROUTE = '/api/turnos-perfil/explicacion-accion-propia-como-paciente';
    private const AGGREGATE_ROUTE = '/api/turnos-perfil/agregado-efector-para-staff';
    private const LIST_ROUTE = '/api/turnos/listar-como-paciente';
    private const INDICADORES_ROUTE = '/api/turnos/indicadores-agenda';

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
        if (!$this->indexExists($table, 'ix_agent_run_profile')) {
            $this->createIndex('ix_agent_run_profile', $table, ['profile_id']);
        }
        if (!$this->indexExists($table, 'ix_agent_run_policy')) {
            $this->createIndex('ix_agent_run_policy', $table, ['policy_id', 'policy_version']);
        }
        $this->ensureProfileRoutes();
    }

    public function safeDown()
    {
        $this->removeProfileRoutes();
        $table = '{{%agent_run}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }
        if ($this->indexExists($table, 'ix_agent_run_policy')) {
            $this->dropIndex('ix_agent_run_policy', $table);
        }
        if ($this->indexExists($table, 'ix_agent_run_profile')) {
            $this->dropIndex('ix_agent_run_profile', $table);
        }
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

    private function ensureProfileRoutes(): void
    {
        $items = '{{%auth_item}}';
        $children = '{{%auth_item_child}}';
        if ($this->db->schema->getTableSchema($items, true) === null
            || $this->db->schema->getTableSchema($children, true) === null) {
            return;
        }

        $this->ensureRoute($items, self::PROFILE_ROUTE, 'Consultar perfil factual propio de turnos');
        $this->ensureRoute($items, self::REPRESENTED_ROUTE, 'Consultar perfil factual representado de turnos');
        $this->ensureRoute($items, self::EXPLAIN_ROUTE, 'Explicar acción anti no-show propia');
        $this->ensureRoute($items, self::AGGREGATE_ROUTE, 'Agregado factual de turnos por efector');

        $this->inheritParents($children, self::LIST_ROUTE, [
            self::PROFILE_ROUTE,
            self::REPRESENTED_ROUTE,
            self::EXPLAIN_ROUTE,
        ]);
        $this->inheritParents($children, self::INDICADORES_ROUTE, [self::AGGREGATE_ROUTE]);
    }

    private function ensureRoute(string $items, string $route, string $description): void
    {
        if ((new Query())->from($items)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $now = time();
        $this->insert($items, [
            'name' => $route,
            'type' => 3,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param list<string> $newChildren
     */
    private function inheritParents(string $children, string $fromChild, array $newChildren): void
    {
        $parents = (new Query())->select('parent')->from($children)
            ->where(['child' => $fromChild])->column($this->db);
        foreach ($parents as $parent) {
            foreach ($newChildren as $child) {
                if (!(new Query())->from($children)->where([
                    'parent' => $parent,
                    'child' => $child,
                ])->exists($this->db)) {
                    $this->insert($children, ['parent' => $parent, 'child' => $child]);
                }
            }
        }
    }

    private function removeProfileRoutes(): void
    {
        if ($this->db->schema->getTableSchema('{{%auth_item}}', true) === null
            || $this->db->schema->getTableSchema('{{%auth_item_child}}', true) === null) {
            return;
        }
        foreach ([
            self::PROFILE_ROUTE,
            self::REPRESENTED_ROUTE,
            self::EXPLAIN_ROUTE,
            self::AGGREGATE_ROUTE,
        ] as $route) {
            $this->delete('{{%auth_item_child}}', ['child' => $route]);
            $this->delete('{{%auth_item}}', ['name' => $route]);
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->schema->getTableIndexes($raw, true);

        return isset($indexes[$name]);
    }
}
