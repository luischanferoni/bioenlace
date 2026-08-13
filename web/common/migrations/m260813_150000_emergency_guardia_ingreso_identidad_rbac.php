<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Quien puede ingresar a guardia también puede consultar RENAPER y abrir Didit hosted.
 */
class m260813_150000_emergency_guardia_ingreso_identidad_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const INHERIT_FROM = '/api/clinical/emergency-guardia/ingresar';

    private const ROUTES = [
        '/api/registro/preview-renaper-como-staff',
        '/api/registro/crear-sesion-didit-como-staff',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        foreach (self::ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            $this->ensureChild($childTable, self::INHERIT_FROM, $route);
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $this->db->createCommand()->delete($childTable, [
            'parent' => self::INHERIT_FROM,
            'child' => self::ROUTES,
        ])->execute();
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API registro paciente staff (identidad ingreso guardia)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensureChild(string $childTable, string $parent, string $child): void
    {
        if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $child])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();
    }
}
