<?php

namespace common\components\Inpatient;

use common\models\InternacionEpicrisisPlantilla;
use common\models\Persona;
use common\models\SegNivelInternacion;
use yii\db\Query;

/**
 * Plantillas de epicrisis por efector / servicio.
 */
final class InternacionEpicrisisPlantillaService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listar(int $idEfector, ?int $idServicio = null): array
    {
        $q = (new Query())
            ->from(['p' => InternacionEpicrisisPlantilla::tableName()])
            ->where(['p.activo' => 1])
            ->andWhere([
                'or',
                ['p.id_efector' => $idEfector],
                ['p.id_efector' => 0],
            ]);

        if ($idServicio !== null && $idServicio > 0) {
            $q->andWhere([
                'or',
                ['p.id_servicio' => null],
                ['p.id_servicio' => $idServicio],
            ]);
        }

        $rows = $q->orderBy(['p.orden' => SORT_ASC, 'p.nombre' => SORT_ASC])->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'nombre' => (string) $row['nombre'],
                'id_efector' => (int) $row['id_efector'],
                'id_servicio' => $row['id_servicio'] !== null ? (int) $row['id_servicio'] : null,
            ];
        }

        return $out;
    }

    public function render(int $plantillaId, SegNivelInternacion $internacion): string
    {
        $plantilla = InternacionEpicrisisPlantilla::findOne([
            'id' => $plantillaId,
            'activo' => 1,
        ]);
        if ($plantilla === null) {
            throw new \InvalidArgumentException('Plantilla de epicrisis no encontrada.');
        }

        $paciente = $internacion->paciente;
        $nombre = $paciente && method_exists($paciente, 'getNombreCompleto')
            ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        $dias = null;
        $fechaIngreso = trim((string) ($internacion->fecha_inicio ?? ''));
        if ($fechaIngreso !== '') {
            foreach (['d/m/Y', 'Y-m-d'] as $fmt) {
                $dt = \DateTimeImmutable::createFromFormat($fmt, $fechaIngreso);
                if ($dt !== false) {
                    $dias = (int) (new \DateTimeImmutable('today'))->diff($dt)->days;
                    break;
                }
            }
        }

        $reemplazos = [
            '{paciente}' => $nombre,
            '{fecha_ingreso}' => $fechaIngreso,
            '{dias_internacion}' => $dias !== null ? (string) $dias : '—',
            '{documento}' => $paciente ? (string) ($paciente->documento ?? '') : '',
        ];

        return strtr((string) $plantilla->cuerpo, $reemplazos);
    }
}
