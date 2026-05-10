<?php

use yii\db\Migration;

/**
 * Webvimark RBAC: alinea `auth_item` (y aristas / asignaciones) con rutas renombradas en código:
 *
 * - API: `/api/solicitud-rrhh` → `/api/solicitud-profesional` ({@see SolicitudProfesionalController})
 * - Web: `/frontend/personas/indexpersonarrhh` → `/frontend/personas/index-personas-pes`
 * - Web: `.../rrhh/rrhh-autocomplete` → `.../rrhh/profesionales-autocomplete`
 *
 * Variante con prefijo `/api/v1/` por si quedó registrada así en algún entorno.
 *
 * Idempotente: REPLACE solo donde el prefijo antiguo coincide.
 */
class m260513_000004_webvimark_routes_solicitud_profesional_y_autocomplete extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260513_000004: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260513_000004: sin tabla auth_item, omitido.\n";

            return;
        }

        $pairs = [
            ['/api/solicitud-rrhh', '/api/solicitud-profesional'],
            ['/api/v1/solicitud-rrhh', '/api/v1/solicitud-profesional'],
            ['/frontend/personas/indexpersonarrhh', '/frontend/personas/index-personas-pes'],
            ['/personas/indexpersonarrhh', '/personas/index-personas-pes'],
            ['/frontend/rrhh/rrhh-autocomplete', '/frontend/rrhh/profesionales-autocomplete'],
            ['/rrhh/rrhh-autocomplete', '/rrhh/profesionales-autocomplete'],
        ];

        foreach ($pairs as [$from, $to]) {
            $this->replaceRoutePrefix($authItem, '`name`', $from, $to);
        }

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($child, '`parent`', $from, $to);
                $this->replaceRoutePrefix($child, '`child`', $from, $to);
            }
        }

        $assignment = $this->db->schema->getRawTableName('{{%auth_assignment}}');
        if ($this->db->schema->getTableSchema($assignment, true) !== null
            && $this->columnExists($assignment, 'item_name')) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($assignment, '`item_name`', $from, $to);
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

        $pairs = [
            ['/api/solicitud-profesional', '/api/solicitud-rrhh'],
            ['/api/v1/solicitud-profesional', '/api/v1/solicitud-rrhh'],
            ['/frontend/personas/index-personas-pes', '/frontend/personas/indexpersonarrhh'],
            ['/personas/index-personas-pes', '/personas/indexpersonarrhh'],
            ['/frontend/rrhh/profesionales-autocomplete', '/frontend/rrhh/rrhh-autocomplete'],
            ['/rrhh/profesionales-autocomplete', '/rrhh/rrhh-autocomplete'],
        ];

        foreach ($pairs as [$from, $to]) {
            $this->replaceRoutePrefix($authItem, '`name`', $from, $to);
        }

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($child, '`parent`', $from, $to);
                $this->replaceRoutePrefix($child, '`child`', $from, $to);
            }
        }

        $assignment = $this->db->schema->getRawTableName('{{%auth_assignment}}');
        if ($this->db->schema->getTableSchema($assignment, true) !== null
            && $this->columnExists($assignment, 'item_name')) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($assignment, '`item_name`', $from, $to);
            }
        }
    }

    private function replaceRoutePrefix(string $table, string $column, string $from, string $to): void
    {
        $fromQ = $this->db->quoteValue($from);
        $toQ = $this->db->quoteValue($to);
        $like = $this->db->quoteValue($from . '%');
        $this->execute(
            "UPDATE {$table} SET {$column} = REPLACE({$column}, {$fromQ}, {$toQ}) WHERE {$column} LIKE {$like}"
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
