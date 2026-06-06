<?php

use yii\db\Migration;

/**
 * Reglas triage_codigo → servicio_rol para reserva de turno (paciente).
 *
 * @deprecated Superseded by {@see reserva_triage_codigo_servicio} (FK directa a servicios).
 *             Se conserva por compatibilidad; el seed de la nueva tabla se genera desde aquí.
 */
class m260607_110000_reserva_triage_codigo_servicio_rol extends Migration
{
    private const TABLE = '{{%reserva_triage_codigo_servicio_rol}}';

    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->unsigned(),
            'triage_codigo' => $this->string(64)->notNull()->comment('Código interno del catálogo triage (p. ej. det_piel_erupcion)'),
            'servicio_rol' => $this->string(64)->notNull()->comment('Rol lógico; ver ReservaTriageServicioRol / reserva_triage_servicio_map_v1.yaml'),
            'prioridad' => $this->smallInteger()->notNull()->defaultValue(100),
            'notas' => $this->text()->null(),
        ]);
        $this->createIndex('uq_reserva_triage_codigo_servicio_rol_codigo', self::TABLE, 'triage_codigo', true);

        $rows = [
            ['sintoma_nuevo', 'medicina_clinica', 10, 'Default síntoma nuevo'],
            ['control_cronico', 'medicina_clinica', 10, null],
            ['tramite_admin', 'medicina_clinica', 10, null],
            ['zona_cabeza_cuello', 'medicina_clinica', 20, null],
            ['zona_pecho', 'medicina_clinica', 20, null],
            ['zona_abdomen', 'gastroenterologia', 20, 'Dolor/malestar abdominal → gastro si autogestión especialista off → orientación clínica'],
            ['zona_espalda', 'traumatologia', 20, null],
            ['zona_brazo_mano', 'traumatologia', 20, null],
            ['zona_pierna_pie', 'traumatologia', 20, null],
            ['zona_piel', 'dermatologia', 20, null],
            ['zona_general', 'medicina_clinica', 20, null],
            ['det_cabeza_dolor', 'medicina_clinica', 50, null],
            ['det_cabeza_mareo', 'neurologia', 50, null],
            ['det_pecho_dolor', 'cardiologia', 50, null],
            ['det_pecho_tos', 'neumonologia', 50, null],
            ['det_abd_dolor', 'gastroenterologia', 50, null],
            ['det_abd_nauseas', 'gastroenterologia', 50, null],
            ['det_espalda_dolor', 'traumatologia', 50, null],
            ['det_musculo_esfuerzo', 'traumatologia', 50, null],
            ['det_musculo_esfuerzo_brazo', 'traumatologia', 50, null],
            ['det_musculo_esfuerzo_pierna', 'traumatologia', 50, null],
            ['det_extremidad_hinchazon', 'medicina_clinica', 50, null],
            ['det_piel_erupcion', 'dermatologia', 50, null],
            ['det_general_fiebre', 'medicina_clinica', 50, null],
            ['det_general_otro', 'medicina_clinica', 50, null],
        ];

        foreach ($rows as [$codigo, $rol, $prioridad, $notas]) {
            $this->insert(self::TABLE, [
                'triage_codigo' => $codigo,
                'servicio_rol' => $rol,
                'prioridad' => (int) $prioridad,
                'notas' => $notas,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropTable(self::TABLE);
    }
}
