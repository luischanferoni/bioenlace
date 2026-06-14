<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\models\SegNivelInternacion;
use yii\db\ActiveQuery;

/**
 * KPIs de internación: ocupación y estadía.
 */
final class InternacionIndicadoresService
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(int $idEfector): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector.');
        }

        $mapa = (new InternacionMapaCamasService())->mapa($idEfector);
        $resumenMapa = $mapa['resumen'] ?? [];

        $activas = $this->queryActivasEfector($idEfector)->all();
        $dias = [];
        foreach ($activas as $int) {
            $d = $this->diasDesdeIngreso($int);
            if ($d !== null) {
                $dias[] = $d;
            }
        }

        $estadiaMedia = $dias !== [] ? round(array_sum($dias) / count($dias), 1) : null;
        $estadiaMediana = $this->median($dias);

        return [
            'id_efector' => $idEfector,
            'camas_total' => (int) ($resumenMapa['camas_total'] ?? 0),
            'camas_ocupadas' => (int) ($resumenMapa['ocupadas'] ?? 0),
            'camas_libres' => (int) ($resumenMapa['libres'] ?? 0),
            'ocupacion_pct' => $resumenMapa['ocupacion_pct'] ?? null,
            'internaciones_activas' => count($activas),
            'estadia_media_dias' => $estadiaMedia,
            'estadia_mediana_dias' => $estadiaMediana,
            'resumen_texto' => $this->formatResumenTexto(
                (string) ($mapa['resumen_texto'] ?? ''),
                count($activas),
                $estadiaMedia,
                $estadiaMediana
            ),
        ];
    }

    private function queryActivasEfector(int $idEfector): ActiveQuery
    {
        return SegNivelInternacion::find()
            ->alias('i')
            ->innerJoinWith([
                'cama.sala.piso' => static function (ActiveQuery $q) use ($idEfector): void {
                    $q->andWhere(['infraestructura_piso.id_efector' => $idEfector]);
                },
            ])
            ->andWhere(['i.fecha_fin' => null]);
    }

    private function diasDesdeIngreso(SegNivelInternacion $internacion): ?int
    {
        $fecha = trim((string) ($internacion->fecha_inicio ?? ''));
        if ($fecha === '') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d'] as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $fecha);
            if ($dt !== false) {
                return (int) (new \DateTimeImmutable('today'))->diff($dt)->days;
            }
        }

        return null;
    }

    /**
     * @param int[] $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = (int) floor($n / 2);

        return $n % 2 === 0
            ? round(($values[$mid - 1] + $values[$mid]) / 2, 1)
            : (float) $values[$mid];
    }

    private function formatResumenTexto(
        string $mapaResumen,
        int $activas,
        ?float $media,
        ?float $mediana
    ): string {
        $lines = [
            $mapaResumen,
            "Internaciones activas: {$activas}",
        ];
        if ($media !== null) {
            $lines[] = "Estadía media (días): {$media}";
        }
        if ($mediana !== null) {
            $lines[] = "Estadía mediana (días): {$mediana}";
        }

        return implode("\n", array_filter($lines));
    }
}
