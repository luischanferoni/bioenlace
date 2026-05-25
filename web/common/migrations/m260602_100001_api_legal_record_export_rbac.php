<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: expediente legal staff (ApiGhost: clinical/legal-record-export/*).
 */
class m260602_100001_api_legal_record_export_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_ROUTES = [
        '/api/clinical/legal-record-export/solicitar',
        '/api/clinical/legal-record-export/listar-mis-solicitudes',
        '/api/clinical/legal-record-export/ver-estado',
        '/api/clinical/legal-record-export/descargar',
    ];

    /** Permiso de negocio (asignar a roles médico/admin). */
    private const PERMISSION_GENERAR = 'ExpedienteLegalGenerar';

    /** Hereda desde historia clínica staff si existe. */
    private const INHERIT_FROM = [
        '/api/pacientes/historia-clinica',
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
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        $this->ensurePermission($authItem, self::PERMISSION_GENERAR, $now);

        foreach (self::GHOST_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }

        if ($hasChild) {
            foreach (self::GHOST_ROUTES as $route) {
                $this->inheritFrom($childTable, self::PERMISSION_GENERAR, $route);
            }
            foreach (self::INHERIT_FROM as $parentRoute) {
                if (!(new Query())->from($authItem)->where(['name' => $parentRoute])->exists($this->db)) {
                    continue;
                }
                foreach (self::GHOST_ROUTES as $route) {
                    $this->inheritFrom($childTable, $parentRoute, $route);
                }
                $this->inheritFrom($childTable, $parentRoute, self::PERMISSION_GENERAR);
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $routes = self::GHOST_ROUTES;
        $names = array_merge($routes, [self::PERMISSION_GENERAR]);
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $names])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => $names])->execute();
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API expediente legal staff',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensurePermission(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => 2,
            'description' => 'Generar y descargar expediente legal (staff)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $childRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $childRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $childRoute,
            ])->execute();
        }
    }
}
