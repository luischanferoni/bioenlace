<?php

use yii\db\Migration;

/**
 * Agrega medicina_clinica como fallback (prioridad 10) para zonas que hoy solo
 * apuntan a especialidades: abdomen, espalda, piel, sistemas.
 */
class m260819_120000_reserva_triage_zona_fallback_clinica extends Migration
{
    private const TABLE = '{{%reserva_triage_codigo_servicio}}';

    /** @var list<string> */
    private const ZONAS = [
        'zona_abdomen',
        'zona_musculoesqueletico',
        'zona_piel',
        'zona_sistemas',
        'zona_genitourinario',
    ];

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            echo "m260819_120000: sin tabla reserva_triage_codigo_servicio, omitido.\n";

            return;
        }

        $ids = $this->idsServicioHub();
        if ($ids === []) {
            echo "m260819_120000: no se encontraron servicios hub (medicina clínica/general), omitido.\n";

            return;
        }

        foreach (self::ZONAS as $codigo) {
            foreach ($ids as $idServicio) {
                $exists = (int) $this->db->createCommand(
                    'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE triage_codigo = :c AND id_servicio = :s',
                    [':c' => $codigo, ':s' => $idServicio]
                )->queryScalar();
                if ($exists > 0) {
                    continue;
                }
                $this->insert(self::TABLE, [
                    'triage_codigo' => $codigo,
                    'id_servicio' => $idServicio,
                    'prioridad' => 10,
                    'notas' => 'Fallback medicina clínica/general',
                ]);
            }
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $ids = $this->idsServicioHub();
        if ($ids === []) {
            return;
        }

        foreach (self::ZONAS as $codigo) {
            $this->delete(self::TABLE, [
                'triage_codigo' => $codigo,
                'id_servicio' => $ids,
                'notas' => 'Fallback medicina clínica/general',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function idsServicioHub(): array
    {
        $patterns = [
            'med clinica',
            'med clínica',
            'med general',
            'med familiar',
            'medicina clínica',
            'medicina clinica',
            'medicina general',
            'medicina de familia',
        ];

        $rows = $this->db->createCommand(
            'SELECT id_servicio, nombre FROM {{%servicios}} WHERE acepta_turnos = :si',
            [':si' => 'SI']
        )->queryAll();

        $ids = [];
        foreach ($rows as $row) {
            $nombre = mb_strtolower(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
            if ($nombre === '') {
                continue;
            }
            foreach ($patterns as $p) {
                if ($p !== '' && str_contains($nombre, $p)) {
                    $ids[] = (int) $row['id_servicio'];
                    break;
                }
            }
        }

        sort($ids);

        return array_values(array_unique($ids));
    }
}
