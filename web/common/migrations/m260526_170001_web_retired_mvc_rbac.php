<?php

use yii\db\Migration;

/**
 * RBAC webvimark: retira rutas MVC eliminadas o migradas a API (fase 04 clean-legacy).
 */
class m260526_170001_web_retired_mvc_rbac extends Migration
{
    /** @var string[] */
    private const ROUTES_TO_DROP = [
        '/frontend/guardia/*',
        '/frontend/guardia/create',
        '/frontend/guardia/delete',
        '/frontend/guardia/finalizar',
        '/frontend/guardia/index',
        '/frontend/guardia/libro-guardia',
        '/frontend/guardia/view',
        '/frontend/internacion-atenciones-enfermeria/*',
        '/frontend/internacion-atenciones-enfermeria/create',
        '/frontend/internacion-atenciones-enfermeria/delete',
        '/frontend/internacion-atenciones-enfermeria/index',
        '/frontend/internacion-atenciones-enfermeria/update',
        '/frontend/internacion-atenciones-enfermeria/view',
        '/frontend/internacion-diagnostico/*',
        '/frontend/internacion-diagnostico/create',
        '/frontend/internacion-diagnostico/delete',
        '/frontend/internacion-diagnostico/index',
        '/frontend/internacion-diagnostico/update',
        '/frontend/internacion-diagnostico/view',
        '/frontend/internacion-medicamento/*',
        '/frontend/internacion-medicamento/create',
        '/frontend/internacion-medicamento/delete',
        '/frontend/internacion-medicamento/index',
        '/frontend/internacion-medicamento/update',
        '/frontend/internacion-medicamento/view',
        '/frontend/internacion-practica/*',
        '/frontend/internacion-practica/create',
        '/frontend/internacion-practica/delete',
        '/frontend/internacion-practica/index',
        '/frontend/internacion-practica/update',
        '/frontend/internacion-practica/view',
        '/frontend/turnos/create',
        '/frontend/turnos/delete',
        '/frontend/turnos/no-se-presento',
        '/frontend/internacion-hcama/create',
        '/frontend/internacion-hcama/update',
    ];

    /** @var string[] permisos huérfanos (solo web guardia clínica retirada) */
    private const PERMISSIONS_TO_DROP = [
        'front_crear_episodio_guardia',
        'front_pacientes_guardia',
        'front_libro_guardia',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260526_170001: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260526_170001: sin auth_item, omitido.\n";

            return;
        }

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            foreach (self::ROUTES_TO_DROP as $route) {
                $this->delete($child, ['child' => $route]);
                $this->delete($child, ['parent' => $route]);
            }
            foreach (self::PERMISSIONS_TO_DROP as $perm) {
                $this->delete($child, ['parent' => $perm]);
                $this->delete($child, ['child' => $perm]);
            }
        }

        $assignment = $this->db->schema->getRawTableName('{{%auth_assignment}}');
        if ($this->db->schema->getTableSchema($assignment, true) !== null) {
            foreach (self::PERMISSIONS_TO_DROP as $perm) {
                $this->delete($assignment, ['item_name' => $perm]);
            }
        }

        foreach (array_merge(self::ROUTES_TO_DROP, self::PERMISSIONS_TO_DROP) as $name) {
            $this->delete($authItem, ['name' => $name]);
        }

        echo "    > RBAC: rutas/permisos MVC retirados (fase 04).\n";
    }

    public function safeDown()
    {
        echo "    > m260526_170001: safeDown no restaura auth_item.\n";

        return true;
    }
}
