<?php

use yii\db\Migration;

/**
 * Renombra rutas RBAC `auth_item` de `/rrhh-efector/*` a `/profesional-efector-servicio/*`
 * (alineado al ID del controller Yii tras migración PES).
 *
 * Idempotente con REPLACE (solo afecta prefijo literal).
 */
class m260513_000001_rename_web_route_rrhh_efector_to_profesional_efector_servicio extends Migration
{
    private const OLD_PREFIX = '/rrhh-efector';
    private const NEW_PREFIX = '/profesional-efector-servicio';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260513_000001: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $schema = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($schema, true) === null) {
            echo "m260513_000001: sin tabla auth_item, omitido.\n";

            return;
        }

        $from = $this->db->quoteValue(self::OLD_PREFIX);
        $to = $this->db->quoteValue(self::NEW_PREFIX);

        $this->execute(
            "UPDATE {$schema} SET `name` = REPLACE(`name`, {$from}, {$to}) WHERE `name` LIKE "
            . $this->db->quoteValue(self::OLD_PREFIX . '%')
        );

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            $this->execute(
                "UPDATE {$child} SET `parent` = REPLACE(`parent`, {$from}, {$to}) WHERE `parent` LIKE "
                . $this->db->quoteValue(self::OLD_PREFIX . '%')
            );
            $this->execute(
                "UPDATE {$child} SET `child` = REPLACE(`child`, {$from}, {$to}) WHERE `child` LIKE "
                . $this->db->quoteValue(self::OLD_PREFIX . '%')
            );
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $schema = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($schema, true) === null) {
            return;
        }

        $from = $this->db->quoteValue(self::NEW_PREFIX);
        $to = $this->db->quoteValue(self::OLD_PREFIX);

        $this->execute(
            "UPDATE {$schema} SET `name` = REPLACE(`name`, {$from}, {$to}) WHERE `name` LIKE "
            . $this->db->quoteValue(self::NEW_PREFIX . '%')
        );

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            $this->execute(
                "UPDATE {$child} SET `parent` = REPLACE(`parent`, {$from}, {$to}) WHERE `parent` LIKE "
                . $this->db->quoteValue(self::NEW_PREFIX . '%')
            );
            $this->execute(
                "UPDATE {$child} SET `child` = REPLACE(`child`, {$from}, {$to}) WHERE `child` LIKE "
                . $this->db->quoteValue(self::NEW_PREFIX . '%')
            );
        }
    }
}
