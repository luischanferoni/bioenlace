<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Enlaza el intent staff de tutela con sus rutas API (listado UI + POST verificar).
 *
 * Sin este vínculo, roles con el intent pueden cargar open_ui vía FlowStepAccessService
 * pero el POST de flow_submit falla con 403 (ruta no heredada del permiso intent).
 */
class m260706_120000_person_representation_staff_tutela_intent_route_links extends Migration
{
    private const PERMISSION_TYPE = 2;

    private const INTENT_ID = 'personas.verificar-tutela-staff-flow';

    private const UI_ROUTE = '/api/person-representation/solicitudes-tutela-pendientes-para-staff';

    private const VERIFY_ROUTE = '/api/person-representation/verificar-vinculo-para-staff';

    private const ADMIN_ROLE = 'AdminEfector';

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
        $this->ensurePermission($authItem, self::INTENT_ID, $now);
        $this->linkPermissionToRoute($childTable, self::INTENT_ID, self::VERIFY_ROUTE);
        $this->linkPermissionToRoute($childTable, self::INTENT_ID, self::UI_ROUTE);
        $this->grantRoleIntent($childTable, $authItem);
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
            'parent' => self::INTENT_ID,
            'child' => [self::VERIFY_ROUTE, self::UI_ROUTE],
        ])->execute();
        $this->db->createCommand()->delete($childTable, [
            'parent' => self::ADMIN_ROLE,
            'child' => self::INTENT_ID,
        ])->execute();
    }

    private function ensurePermission(string $authItem, string $intentId, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $intentId,
            'type' => self::PERMISSION_TYPE,
            'description' => 'Intent ' . $intentId,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function linkPermissionToRoute(string $childTable, string $permission, string $route): void
    {
        if ((new Query())->from($childTable)->where([
            'parent' => $permission,
            'child' => $route,
        ])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($childTable, [
            'parent' => $permission,
            'child' => $route,
        ])->execute();
    }

    private function grantRoleIntent(string $childTable, string $authItem): void
    {
        if (!(new Query())->from($authItem)->where(['name' => self::ADMIN_ROLE])->exists($this->db)) {
            return;
        }
        if ((new Query())->from($childTable)->where([
            'parent' => self::ADMIN_ROLE,
            'child' => self::INTENT_ID,
        ])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($childTable, [
            'parent' => self::ADMIN_ROLE,
            'child' => self::INTENT_ID,
        ])->execute();
    }
}
