<?php

use yii\db\Migration;

/**
 * Triage v1: mapeo det_espalda_dolor → traumatología (prioridad listado presencial).
 */
class m260612_110000_reserva_triage_det_espalda_dolor extends Migration
{
    private const TABLE = '{{%reserva_triage_codigo_servicio}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            echo "m260612_110000: sin tabla reserva_triage_codigo_servicio, omitido.\n";

            return;
        }

        $this->seedCodigoConPatrones(
            'det_espalda_dolor',
            ['traumatolog', 'ortoped', 'columna', 'espalda'],
            50,
            'Motivo triage: dolor espalda/muscular'
        );
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }
        $this->delete(self::TABLE, ['triage_codigo' => 'det_espalda_dolor']);
    }

    /**
     * @param list<string> $patrones
     */
    private function seedCodigoConPatrones(string $codigo, array $patrones, int $prioridad, ?string $notas): void
    {
        $rows = $this->db->createCommand(
            'SELECT id_servicio, nombre FROM {{%servicios}} WHERE acepta_turnos = :si',
            [':si' => 'SI']
        )->queryAll();

        foreach ($rows as $row) {
            $id = (int) ($row['id_servicio'] ?? 0);
            $nombre = mb_strtolower(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
            if ($id <= 0 || $nombre === '') {
                continue;
            }
            $match = false;
            foreach ($patrones as $p) {
                if ($p !== '' && str_contains($nombre, mb_strtolower($p, 'UTF-8'))) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                continue;
            }
            $exists = (int) $this->db->createCommand(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE triage_codigo = :c AND id_servicio = :s',
                [':c' => $codigo, ':s' => $id]
            )->queryScalar();
            if ($exists > 0) {
                continue;
            }
            $this->insert(self::TABLE, [
                'triage_codigo' => $codigo,
                'id_servicio' => $id,
                'prioridad' => $prioridad,
                'notas' => $notas,
            ]);
        }
    }
}
