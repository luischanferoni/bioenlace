<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: CUIL profesional y catálogo códigos servicio FHIR.
 */
class m260706_130001_api_fhir_scheduling_trust_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> child => parent */
    private const ROUTES = [
        '/api/profesional-efector-servicio/cargar-cuil-profesional' => '/api/profesional-efector-servicio/crear-flow',
        '/api/profesional-efector-servicio/listar-codigos-servicio-fhir' => '/api/profesional-efector-servicio/crear-flow',
        '/api/profesional-efector-servicio/guardar-codigo-servicio-fhir' => '/api/profesional-efector-servicio/crear-flow',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260706_130001: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260706_130001: sin tabla auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260706_130001: sin tabla auth_item_child, omitido.\n";

            return;
        }

        $now = time();
        foreach (self::ROUTES as $route => $parent) {
            $this->ensureRoute($authItem, $route, $now);
            $this->inheritFrom($childTable, $parent, $route);
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            foreach (array_keys(self::ROUTES) as $route) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            foreach (array_keys(self::ROUTES) as $route) {
                $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
            }
        }
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $row = [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API agendamiento FHIR entrante — confianza PES',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($this->columnExists($authItem, 'group_code')) {
            $row['group_code'] = 'recursos_humanos';
        }

        $this->db->createCommand()->insert($authItem, $row)->execute();
    }

    private function inheritFrom(string $childTable, string $parent, string $child): void
    {
        if (!(new Query())->from($childTable)->where(['parent' => $parent, 'child' => $child])->exists($this->db)) {
            $this->db->createCommand()->insert($childTable, ['parent' => $parent, 'child' => $child])->execute();
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
