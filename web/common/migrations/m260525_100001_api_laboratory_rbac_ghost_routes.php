<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC alineado a {@see \frontend\modules\api\v1\components\ApiGhostAccessControl}:
 * el controlador Yii es `clinical/laboratory-result/*` (singular), no `laboratory-results` del path HTTP.
 *
 * Copia asignaciones de rol desde las rutas plural ya registradas en migraciones anteriores.
 */
class m260525_100001_api_laboratory_rbac_ghost_routes extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> ruta ghost (singular) => ruta ya usada en permisos (plural) */
    private const GHOST_FROM_PLURAL = [
        '/api/clinical/laboratory-result/mis-resultados' => '/api/clinical/laboratory-results/mis-resultados',
        '/api/clinical/laboratory-result/sincronizar' => '/api/clinical/laboratory-results/sincronizar',
        '/api/clinical/laboratory-result/mis-resultados-como-paciente' => '/api/clinical/laboratory-results/mis-resultados-como-paciente',
        '/api/clinical/laboratory-result/sincronizar-como-paciente' => '/api/clinical/laboratory-results/sincronizar-como-paciente',
        '/api/clinical/laboratory-result/ver-informe-como-paciente' => '/api/clinical/laboratory-results/ver-informe-como-paciente',
        '/api/clinical/laboratory-result/descargar-pdf-como-paciente' => '/api/clinical/laboratory-results/descargar-pdf-como-paciente',
        '/api/clinical/laboratory-result/por-encounter' => '/api/clinical/encounter/laboratory-results',
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

        foreach (self::GHOST_FROM_PLURAL as $ghostRoute => $pluralRoute) {
            $this->ensureRoute($authItem, $ghostRoute, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, $pluralRoute, $ghostRoute);
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

        $routes = array_keys(self::GHOST_FROM_PLURAL);
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $routes])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => $routes])->execute();
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API laboratorio (ruta ApiGhost / controller)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $pluralRoute, string $ghostRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $pluralRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => $parent, 'child' => $ghostRoute])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $ghostRoute,
            ])->execute();
        }
    }
}
