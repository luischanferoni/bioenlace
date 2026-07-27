<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: ver consulta documentada (staff).
 * ApiGhost: clinical/encounter-staff-summary/ver-consulta-como-staff
 * Alias HTTP: clinical/encounter/ver-consulta-como-staff
 */
class m260727_120000_api_encounter_ver_consulta_como_staff_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_ROUTE = '/api/clinical/encounter-staff-summary/ver-consulta-como-staff';

    private const HTTP_ALIAS = '/api/clinical/encounter/ver-consulta-como-staff';

    /** Hereda grants del resumen HC staff (misma audiencia). */
    private const INHERIT_FROM = '/api/pacientes/historia-clinica';

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260727_120000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260727_120000: sin auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach ([self::GHOST_ROUTE, self::HTTP_ALIAS] as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API ver consulta como staff',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
        }

        if (!$hasChild) {
            return;
        }

        $templateParents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);

        foreach ([self::GHOST_ROUTE, self::HTTP_ALIAS] as $route) {
            foreach ($templateParents as $parent) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $parent,
                    'child' => $route,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $route,
                ])->execute();
            }
        }

        // Alias HTTP hereda del ghost (por si el ghost se otorga aparte).
        if (!(new Query())->from($childTable)->where([
            'parent' => self::GHOST_ROUTE,
            'child' => self::HTTP_ALIAS,
        ])->exists($this->db)) {
            $this->db->createCommand()->insert($childTable, [
                'parent' => self::GHOST_ROUTE,
                'child' => self::HTTP_ALIAS,
            ])->execute();
        }
    }

    public function safeDown(): void
    {
        // No revierte grants productivos.
    }
}
