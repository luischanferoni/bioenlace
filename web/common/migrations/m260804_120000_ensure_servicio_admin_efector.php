<?php

use common\models\Servicio;
use yii\db\Migration;
use yii\db\Query;

/**
 * Restaura el servicio de sistema que otorga rol RBAC AdminEfector vía PES.
 *
 * La higiene de catálogo (m260731_160000) eliminó filas admin/logística
 * (p. ej. ADMINISTRAR EFECTOR). El dominio sigue necesitando exactamente una
 * fila canónica con item_name=AdminEfector (no oferta clínica; tipo soporte).
 */
class m260804_120000_ensure_servicio_admin_efector extends Migration
{
    private const ITEM_NAME = 'AdminEfector';

    private const NOMBRE = 'ADMINISTRAR EFECTOR';

    public function safeUp(): void
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            echo "    > servicios no existe; omitida.\n";

            return;
        }

        $existingId = (new Query())
            ->from($servicios)
            ->select(['id_servicio'])
            ->where(['item_name' => self::ITEM_NAME])
            ->scalar($this->db);

        if ($existingId !== false && $existingId !== null) {
            $id = (int) $existingId;
            $update = [
                'nombre' => self::NOMBRE,
                'acepta_turnos' => 'NO',
                'acepta_practicas' => 'NO',
            ];
            if (isset($schema->columns['tipo'])) {
                $update['tipo'] = Servicio::TIPO_SOPORTE;
            }
            if (isset($schema->columns['teleconsulta_politica'])) {
                $update['teleconsulta_politica'] = Servicio::TELECONSULTA_POLITICA_NINGUNA;
            }
            if (isset($schema->columns['reserva_autogestion_paciente'])) {
                $update['reserva_autogestion_paciente'] = Servicio::RESERVA_AUTOGESTION_PACIENTE_NO;
            }
            $this->update($servicios, $update, ['id_servicio' => $id]);
            echo "    > servicio AdminEfector ya existía (id={$id}); normalizado.\n";

            return;
        }

        $row = [
            'nombre' => self::NOMBRE,
            'parametros' => '',
            'item_name' => self::ITEM_NAME,
            'acepta_turnos' => 'NO',
            'acepta_practicas' => 'NO',
        ];
        if (isset($schema->columns['tipo'])) {
            $row['tipo'] = Servicio::TIPO_SOPORTE;
        }
        if (isset($schema->columns['teleconsulta_politica'])) {
            $row['teleconsulta_politica'] = Servicio::TELECONSULTA_POLITICA_NINGUNA;
        }
        if (isset($schema->columns['reserva_autogestion_paciente'])) {
            $row['reserva_autogestion_paciente'] = Servicio::RESERVA_AUTOGESTION_PACIENTE_NO;
        }

        $this->insert($servicios, $row);
        $newId = (int) $this->db->getLastInsertID();
        echo "    > creado servicio AdminEfector (id={$newId}).\n";
    }

    public function safeDown(): void
    {
        // No borrar: puede haber PES / servicios_efector apuntando al sistema.
    }
}
