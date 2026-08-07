<?php

use common\components\Domain\Clinical\Emergency\Enum\GuardiaEgresoDestino;
use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use yii\db\Migration;
use yii\db\Query;

/**
 * Egreso estructurado de guardia: destino, diagnóstico, epicrisis, pautas + RBAC UI.
 */
class m260807_120000_guardia_egreso_estructurado extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_ROUTE = '/api/clinical/emergency-guardia/egreso-formulario';

    private const INHERIT_FROM = '/api/clinical/emergency-guardia/finalizar';

    public function safeUp()
    {
        $table = '{{%guardia}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null) {
            if ($schema->getColumn('destino_egreso') === null) {
                $enumSql = MigrationEnumColumn::mysqlEnum(
                    GuardiaEgresoDestino::values(),
                    GuardiaEgresoDestino::ALTA_DOMICILIARIA,
                    false,
                    implode('|', GuardiaEgresoDestino::values())
                );
                // Nullable hasta el egreso (sin default efectivo en filas activas).
                $enumSql = preg_replace('/\s+DEFAULT\s+\'[^\']+\'/i', '', $enumSql);
                $this->addColumn($table, 'destino_egreso', $enumSql . ' DEFAULT NULL');
            }
            if ($schema->getColumn('diagnostico_operativo') === null) {
                $this->addColumn($table, 'diagnostico_operativo', $this->text()->null());
            }
            if ($schema->getColumn('epicrisis') === null) {
                $this->addColumn($table, 'epicrisis', $this->text()->null());
            }
            if ($schema->getColumn('pautas_alarma') === null) {
                $this->addColumn($table, 'pautas_alarma', $this->text()->null());
            }
            if ($schema->getColumn('egreso_meta_json') === null) {
                $this->addColumn($table, 'egreso_meta_json', $this->text()->null());
            }
        }

        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $now = time();
        if (!(new Query())->from($authItem)->where(['name' => self::GHOST_ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::GHOST_ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'API urgencias: egreso estructurado (UI JSON)',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);

        if ($parents === []) {
            $parents = (new Query())
                ->select('parent')
                ->from($childTable)
                ->where(['child' => '/api/clinical/emergency-guardia/tablero'])
                ->column($this->db);
        }

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => self::GHOST_ROUTE,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => self::GHOST_ROUTE,
            ])->execute();
        }
    }

    public function safeDown()
    {
        $table = '{{%guardia}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null) {
            foreach (['egreso_meta_json', 'pautas_alarma', 'epicrisis', 'diagnostico_operativo', 'destino_egreso'] as $col) {
                if ($schema->getColumn($col) !== null) {
                    $this->dropColumn($table, $col);
                }
            }
        }

        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::GHOST_ROUTE])->execute();
        }
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::GHOST_ROUTE])->execute();
        }
    }
}
