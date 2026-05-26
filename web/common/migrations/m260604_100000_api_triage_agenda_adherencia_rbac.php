<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: triage UI guardia, métricas agenda y adherencia planes (staff).
 */
class m260604_100000_api_triage_agenda_adherencia_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> ruta hija => ruta padre para heredar permisos */
    private const INHERIT = [
        '/api/clinical/emergency-guardia/elegir-paciente-triage' => '/api/clinical/emergency-guardia/tablero',
        '/api/clinical/emergency-guardia/registrar-triage-formulario' => '/api/clinical/emergency-guardia/registrar-triage',
        '/api/turnos/indicadores-agenda' => '/api/pacientes/listar',
        '/api/clinical/care-plans/adherencia-resumen-staff' => '/api/clinical/care-plan/active',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        foreach (self::INHERIT as $ghost => $parent) {
            if (!(new Query())->from($authItem)->where(['name' => $ghost])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $ghost,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API triage / agenda / adherencia (staff)',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }

            $parents = (new Query())
                ->select('parent')
                ->from($childTable)
                ->where(['child' => $parent])
                ->column($this->db);

            foreach ($parents as $role) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $role,
                    'child' => $ghost,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $role,
                    'child' => $ghost,
                ])->execute();
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $routes = array_keys(self::INHERIT);
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $routes])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => $routes])->execute();
        }
    }
}
