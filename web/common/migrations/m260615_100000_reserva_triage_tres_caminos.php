<?php

use yii\db\Migration;

/**
 * Triage v2: malestar_nuevo + categorías urg_cat_* (teleconsulta excluida).
 */
class m260615_100000_reserva_triage_tres_caminos extends Migration
{
    private const TELE = '{{%reserva_triage_teleconsulta_elegibilidad}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TELE, true) === null) {
            return;
        }

        $rows = [
            ['malestar_nuevo', 'permitido', 50, 'Malestar nuevo ambulatorio'],
            ['urgencia', 'excluido', 5, 'Camino urgencia — solo guardia'],
            ['urg_cat_neurologico', 'excluido', 5, null],
            ['urg_cat_cardiaco', 'excluido', 5, null],
            ['urg_cat_respiratorio', 'excluido', 5, null],
            ['urg_cat_trauma_sangrado', 'excluido', 5, null],
            ['urg_cat_abdominal', 'excluido', 5, null],
            ['urg_cat_fiebre', 'excluido', 5, null],
            ['urg_cat_otro', 'excluido', 5, null],
        ];

        foreach ($rows as [$codigo, $elegibilidad, $prioridad, $notas]) {
            $exists = (int) $this->db->createCommand(
                'SELECT COUNT(*) FROM ' . self::TELE . ' WHERE triage_codigo = :c',
                [':c' => $codigo]
            )->queryScalar();
            if ($exists > 0) {
                continue;
            }
            $this->insert(self::TELE, [
                'triage_codigo' => $codigo,
                'elegibilidad' => $elegibilidad,
                'prioridad' => $prioridad,
                'notas' => $notas,
            ]);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TELE, true) === null) {
            return;
        }
        foreach ([
            'malestar_nuevo', 'urgencia', 'urg_cat_neurologico', 'urg_cat_cardiaco',
            'urg_cat_respiratorio', 'urg_cat_trauma_sangrado', 'urg_cat_abdominal',
            'urg_cat_fiebre', 'urg_cat_otro',
        ] as $codigo) {
            $this->delete(self::TELE, ['triage_codigo' => $codigo]);
        }
    }
}
