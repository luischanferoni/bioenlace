<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: renombra rutas legacy de flujos agenda/PES y agrega rutas nuevas (estado base u257309594_bioenlace).
 *
 * - Renombra auth_item + auth_item_child (no duplica permisos).
 * - Rutas nuevas heredan asignaciones de rol desde una ruta fuente cuando aplica.
 */
class m260608_190000_agenda_flow_routes_rbac_canonical extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> canónica => legacy en BD actual */
    private const ROUTE_RENAMES = [
        '/api/profesional-efector-servicio/crear-flow' => '/api/profesional-agenda/crear-agenda-flow',
        '/api/profesional-agenda/editar-flow' => '/api/profesional-agenda/editar-agenda-flow',
        '/api/profesional-agenda/editar-mi-flow' => '/api/profesional-agenda/editar-mi-agenda-flow',
        '/api/profesional-agenda/resolver-conflictos-flow' => '/api/profesional-agenda/resolver-conflicto-agenda-para-paciente',
    ];

    /** @var array<string, string> ruta nueva => ruta existente de la que copiar auth_item_child */
    private const NEW_ROUTES_INHERIT_FROM = [
        '/api/profesional-agenda/preview-impacto-agenda' => '/api/profesional-agenda/preview-configurar-agenda',
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

        foreach (self::ROUTE_RENAMES as $canonical => $legacy) {
            $this->renameRoute($authItem, $hasChild ? $childTable : null, $legacy, $canonical, $now);
        }

        foreach (self::NEW_ROUTES_INHERIT_FROM as $newRoute => $sourceRoute) {
            $this->ensureRoute($authItem, $newRoute, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, $sourceRoute, $newRoute);
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

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;

        foreach (array_keys(self::NEW_ROUTES_INHERIT_FROM) as $route) {
            if ($hasChild) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
            $this->db->createCommand()->delete($authItem, ['name' => $route, 'type' => self::ROUTE_TYPE])->execute();
        }

        foreach (self::ROUTE_RENAMES as $canonical => $legacy) {
            $this->renameRoute($authItem, $hasChild ? $childTable : null, $canonical, $legacy, time());
        }
    }

    private function renameRoute(
        string $authItem,
        ?string $childTable,
        string $from,
        string $to,
        int $now
    ): void {
        if ($from === $to) {
            return;
        }

        if ($childTable !== null) {
            $this->rewireChildRoutes($childTable, $from, $to);
        }

        $fromExists = (new Query())->from($authItem)->where(['name' => $from, 'type' => self::ROUTE_TYPE])->exists($this->db);
        $toExists = (new Query())->from($authItem)->where(['name' => $to, 'type' => self::ROUTE_TYPE])->exists($this->db);

        if ($fromExists && !$toExists) {
            $this->db->createCommand()->update($authItem, [
                'name' => $to,
                'updated_at' => $now,
            ], ['name' => $from, 'type' => self::ROUTE_TYPE])->execute();

            return;
        }

        if ($fromExists && $toExists && $childTable !== null) {
            $this->inheritFrom($childTable, $from, $to);
        }

        if (!$toExists) {
            $this->ensureRoute($authItem, $to, $now);
            if ($fromExists && $childTable !== null) {
                $this->inheritFrom($childTable, $from, $to);
            }
        }
    }

    private function rewireChildRoutes(string $childTable, string $from, string $to): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $from])
            ->column($this->db);

        foreach ($parents as $parent) {
            $hasTarget = (new Query())
                ->from($childTable)
                ->where(['parent' => $parent, 'child' => $to])
                ->exists($this->db);
            if ($hasTarget) {
                $this->db->createCommand()->delete($childTable, [
                    'parent' => $parent,
                    'child' => $from,
                ])->execute();
                continue;
            }
            $this->db->createCommand()->update($childTable, ['child' => $to], [
                'parent' => $parent,
                'child' => $from,
            ])->execute();
        }
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name, 'type' => self::ROUTE_TYPE])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API flujo asistente (ruta canónica)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $sourceRoute, string $targetRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $sourceRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => $parent, 'child' => $targetRoute])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $targetRoute,
            ])->execute();
        }
    }
}
