<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC canónico singular `laboratory-result` (alineado a URL HTTP y ApiGhost).
 *
 * Copia asignaciones de rol desde rutas legacy plural `laboratory-results` si existen.
 */
class m260526_100001_api_laboratory_singular_rbac_canonical extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> ruta canónica => ruta legacy plural (si existe) */
    private const CANONICAL_FROM_LEGACY = [
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

        foreach (self::CANONICAL_FROM_LEGACY as $canonical => $legacy) {
            $this->ensureRoute($authItem, $canonical, $now);
            if ($hasChild && (new Query())->from($authItem)->where(['name' => $legacy])->exists($this->db)) {
                $this->inheritFrom($childTable, $legacy, $canonical);
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

        $routes = array_keys(self::CANONICAL_FROM_LEGACY);
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
            'description' => 'API laboratorio (ruta canónica singular)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $legacyRoute, string $canonicalRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $legacyRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => $parent, 'child' => $canonicalRoute])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $canonicalRoute,
            ])->execute();
        }
    }
}
