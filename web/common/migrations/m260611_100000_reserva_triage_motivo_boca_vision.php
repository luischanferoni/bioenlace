<?php

use yii\db\Migration;

/**
 * Triage motivo: mapeo boca/vista → servicios (prioridad en listado presencial).
 */
class m260611_100000_reserva_triage_motivo_boca_vision extends Migration
{
    private const TABLE = '{{%reserva_triage_codigo_servicio}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            echo "m260611_100000: sin tabla reserva_triage_codigo_servicio, omitido.\n";

            return;
        }

        foreach ($this->reglas() as [$codigo, $patrones, $prioridad, $notas]) {
            $this->seedCodigoConPatrones($codigo, $patrones, $prioridad, $notas);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }
        foreach (['det_boca_muela', 'det_vision'] as $codigo) {
            $this->delete(self::TABLE, ['triage_codigo' => $codigo]);
        }
    }

    /**
     * @return list<array{0: string, 1: list<string>, 2: int, 3: string|null}>
     */
    private function reglas(): array
    {
        return [
            ['det_boca_muela', ['odontolog', 'odonto', 'dental', 'boca'], 50, 'Motivo triage: dolor muela'],
            ['det_vision', ['oftalmolog', 'oftalmo', 'vista', 'ojos'], 50, 'Motivo triage: problema vista'],
        ];
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
