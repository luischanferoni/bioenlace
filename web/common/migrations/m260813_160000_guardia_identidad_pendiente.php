<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Episodio de guardia con identidad pendiente (NN) y permiso para vincular DNI/Didit después.
 */
class m260813_160000_guardia_identidad_pendiente extends Migration
{
    private const ROUTE_TYPE = 3;

    private const INHERIT_FROM = '/api/clinical/emergency-guardia/ingresar';

    private const VINCULAR_ROUTE = '/api/clinical/emergency-guardia/vincular-identidad';

    public function safeUp()
    {
        $guardia = '{{%guardia}}';
        $schema = $this->db->schema->getTableSchema($guardia, true);
        if ($schema !== null && $schema->getColumn('identidad_pendiente') === null) {
            $this->addColumn(
                $guardia,
                'identidad_pendiente',
                $this->tinyInteger()->notNull()->defaultValue(0)->after('id_persona')
            );
            $this->createIndex(
                'ix_guardia_efector_identidad_pendiente',
                $guardia,
                ['id_efector', 'identidad_pendiente', 'estado']
            );
        }

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
        if (!(new Query())->from($authItem)->where(['name' => self::VINCULAR_ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::VINCULAR_ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'API urgencias: vincular identidad a episodio NN',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        if (!(new Query())->from($childTable)->where([
            'parent' => self::INHERIT_FROM,
            'child' => self::VINCULAR_ROUTE,
        ])->exists($this->db)) {
            $this->db->createCommand()->insert($childTable, [
                'parent' => self::INHERIT_FROM,
                'child' => self::VINCULAR_ROUTE,
            ])->execute();
        }
    }

    public function safeDown()
    {
        if (in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
            $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
            if ($this->db->schema->getTableSchema($childTable, true) !== null) {
                $this->db->createCommand()->delete($childTable, [
                    'parent' => self::INHERIT_FROM,
                    'child' => self::VINCULAR_ROUTE,
                ])->execute();
            }
            if ($this->db->schema->getTableSchema($authItem, true) !== null) {
                $this->db->createCommand()->delete($authItem, ['name' => self::VINCULAR_ROUTE])->execute();
            }
        }

        $guardia = '{{%guardia}}';
        $schema = $this->db->schema->getTableSchema($guardia, true);
        if ($schema !== null && $schema->getColumn('identidad_pendiente') !== null) {
            $this->dropIndex('ix_guardia_efector_identidad_pendiente', $guardia);
            $this->dropColumn($guardia, 'identidad_pendiente');
        }
    }
}
