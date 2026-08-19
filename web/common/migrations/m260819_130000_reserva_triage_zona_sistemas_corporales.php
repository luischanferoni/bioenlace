<?php

use yii\db\Migration;

/**
 * Mapeos zona→servicio para sistemas corporales ampliados:
 * musculoesquelético (reemplaza espalda en UI) y genitourinario.
 *
 * Copia filas legacy de zona_espalda → zona_musculoesqueletico para drafts en curso.
 */
class m260819_130000_reserva_triage_zona_sistemas_corporales extends Migration
{
    private const TABLE = '{{%reserva_triage_codigo_servicio}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            echo "m260819_130000: sin tabla reserva_triage_codigo_servicio, omitido.\n";

            return;
        }

        $this->copiarMapeosLegacy('zona_espalda', 'zona_musculoesqueletico');

        foreach ($this->reglasEspecialidad() as [$codigo, $patrones, $prioridad, $notas]) {
            $this->seedCodigoConPatrones($codigo, $patrones, $prioridad, $notas);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        foreach (['zona_musculoesqueletico', 'zona_genitourinario'] as $codigo) {
            $this->delete(self::TABLE, ['triage_codigo' => $codigo]);
        }
    }

    private function copiarMapeosLegacy(string $desde, string $hacia): void
    {
        $rows = $this->db->createCommand(
            'SELECT id_servicio, prioridad, notas FROM ' . self::TABLE . ' WHERE triage_codigo = :c',
            [':c' => $desde]
        )->queryAll();

        foreach ($rows as $row) {
            $id = (int) ($row['id_servicio'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $exists = (int) $this->db->createCommand(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE triage_codigo = :c AND id_servicio = :s',
                [':c' => $hacia, ':s' => $id]
            )->queryScalar();
            if ($exists > 0) {
                continue;
            }
            $this->insert(self::TABLE, [
                'triage_codigo' => $hacia,
                'id_servicio' => $id,
                'prioridad' => (int) ($row['prioridad'] ?? 20),
                'notas' => $row['notas'] ?? 'Copiado desde ' . $desde,
            ]);
        }
    }

    /**
     * @return list<array{0: string, 1: list<string>, 2: int, 3: string|null}>
     */
    private function reglasEspecialidad(): array
    {
        return [
            ['zona_musculoesqueletico', ['traumatolog', 'ortoped'], 20, 'Espalda/huesos/músculos → traumatología'],
            ['zona_genitourinario', ['ginecolog', 'obstetr', 'urolog'], 20, 'Ginecológico/embarazo/urinario'],
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
