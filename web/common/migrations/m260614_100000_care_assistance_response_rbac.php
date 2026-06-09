<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Respuestas de asistencia pre-consulta + RBAC API care-packs.
 */
class m260614_100000_care_assistance_response_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PARENT_ROUTE = '/api/motivos-consulta/listar-mensajes';

    /** @var list<string> */
    private const NEW_ROUTES = [
        '/api/care-packs/assistance',
    ];

    public function safeUp()
    {
        $response = '{{%care_assistance_response}}';
        if ($this->db->schema->getTableSchema($response, true) === null) {
            $this->createTable($response, [
                'encounter_id' => $this->primaryKey(),
                'subject_persona_id' => $this->integer()->notNull(),
                'pack_id' => $this->integer()->notNull(),
                'answers_json' => $this->text()->notNull(),
                'delta_requested' => $this->boolean()->notNull()->defaultValue(false),
                'submitted_at' => $this->dateTime()->notNull(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_care_assistance_response_persona', $response, 'subject_persona_id');
            $this->addForeignKey(
                'fk_care_assistance_response_encounter',
                $response,
                'encounter_id',
                '{{%encounter}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_care_assistance_response_pack',
                $response,
                'pack_id',
                '{{%care_cohort_pack}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260614_100000: tablas OK; RBAC omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260614_100000: sin auth_item, RBAC omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::NEW_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, self::PARENT_ROUTE, $route);
            }
        }
    }

    public function safeDown()
    {
        if (in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
            if ($this->db->schema->getTableSchema($authItem, true) !== null) {
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

        $response = '{{%care_assistance_response}}';
        if ($this->db->schema->getTableSchema($response, true) !== null) {
            $this->dropForeignKey('fk_care_assistance_response_pack', $response);
            $this->dropForeignKey('fk_care_assistance_response_encounter', $response);
            $this->dropTable($response);
        }
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API care-packs: asistencia pre-consulta paciente',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => $parent, 'child' => $newRoute])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $newRoute,
            ])->execute();
        }
    }
}
