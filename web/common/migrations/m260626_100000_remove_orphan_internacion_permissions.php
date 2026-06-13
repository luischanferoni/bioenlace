<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Elimina permisos lógicos huérfanos sin rol ni intent YAML canónico.
 *
 * - Internacion.update — duplica Internacion.discharge
 * - Internacion.view — duplica Internacion.view_map (canónico)
 */
class m260626_100000_remove_orphan_internacion_permissions extends Migration
{
    /** @var list<string> */
    private const ORPHAN_PERMISSIONS = [
        'Internacion.update',
        'Internacion.view',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260626_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $itemTable = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null
            || $this->db->schema->getTableSchema($itemTable, true) === null) {
            echo "m260626_100000: sin tablas RBAC, omitido.\n";

            return;
        }

        $removedLinks = (int) $this->db->createCommand()->delete($childTable, [
            'or',
            ['parent' => self::ORPHAN_PERMISSIONS],
            ['child' => self::ORPHAN_PERMISSIONS],
        ])->execute();

        $removedItems = (int) $this->db->createCommand()->delete($itemTable, [
            'and',
            ['type' => 2],
            ['name' => self::ORPHAN_PERMISSIONS],
        ])->execute();

        $remaining = (new Query())
            ->from($itemTable)
            ->where(['name' => self::ORPHAN_PERMISSIONS])
            ->count('*', $this->db);

        echo sprintf(
            "m260626_100000: auth_item_child=%d auth_item=%d restantes=%d\n",
            $removedLinks,
            $removedItems,
            $remaining
        );
    }

    public function safeDown()
    {
        echo "m260626_100000: safeDown no restaura permisos huérfanos.\n";
    }
}
