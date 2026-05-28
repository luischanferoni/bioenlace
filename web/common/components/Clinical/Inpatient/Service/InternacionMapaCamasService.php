<?php

namespace common\components\Clinical\Inpatient\Service;

use common\models\InfraestructuraCama;
use common\models\Persona;
use common\models\SegNivelInternacion;
use yii\db\ActiveQuery;

/**
 * Mapa operativo de camas por efector (libre, ocupada, bloqueada, aislamiento).
 */
final class InternacionMapaCamasService
{
    public const ESTADO_LIBRE = 'libre';
    public const ESTADO_OCUPADA = 'ocupada';
    public const ESTADO_BLOQUEADA = 'bloqueada';
    public const ESTADO_AISLAMIENTO = 'aislamiento';

    /**
     * @return array<string, mixed>
     */
    public function mapa(int $idEfector, ?int $idPiso = null, ?int $idSala = null): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector.');
        }

        $internacionesActivas = $this->loadInternacionesActivasPorEfector($idEfector);
        $porCama = [];
        foreach ($internacionesActivas as $int) {
            $porCama[(int) $int->id_cama] = $int;
        }

        $pisosOut = [];
        $counts = [
            'camas_total' => 0,
            'libres' => 0,
            'ocupadas' => 0,
            'bloqueadas' => 0,
            'aislamiento' => 0,
        ];

        foreach (InternacionEfectorAccess::pisosDelEfector($idEfector) as $piso) {
            if ($idPiso !== null && $idPiso > 0 && (int) $piso->id !== $idPiso) {
                continue;
            }
            $salasOut = [];
            foreach ($piso->infraestructuraSalas as $sala) {
                if ($idSala !== null && $idSala > 0 && (int) $sala->id !== $idSala) {
                    continue;
                }
                $camasOut = [];
                foreach ($sala->infraestructuraCamas as $cama) {
                    $row = $this->serializeCama($cama, $porCama[(int) $cama->id] ?? null);
                    $camasOut[] = $row;
                    $counts['camas_total']++;
                    switch ($row['estado_mapa']) {
                        case self::ESTADO_OCUPADA:
                            $counts['ocupadas']++;
                            break;
                        case self::ESTADO_BLOQUEADA:
                            $counts['bloqueadas']++;
                            break;
                        case self::ESTADO_AISLAMIENTO:
                            $counts['aislamiento']++;
                            break;
                        default:
                            $counts['libres']++;
                    }
                }
                if ($camasOut !== []) {
                    $salasOut[] = [
                        'id' => (int) $sala->id,
                        'descripcion' => (string) ($sala->descripcion ?? ''),
                        'nro_sala' => $sala->nro_sala ?? null,
                        'camas' => $camasOut,
                    ];
                }
            }
            if ($salasOut !== []) {
                $pisosOut[] = [
                    'id' => (int) $piso->id,
                    'descripcion' => (string) ($piso->descripcion ?? ''),
                    'nro_piso' => (int) ($piso->nro_piso ?? 0),
                    'salas' => $salasOut,
                ];
            }
        }

        $cerradas = $counts['ocupadas'] + $counts['libres'] + $counts['bloqueadas'] + $counts['aislamiento'];
        $ocupacionPct = $counts['camas_total'] > 0
            ? round(100.0 * $counts['ocupadas'] / $counts['camas_total'], 1)
            : null;

        return [
            'id_efector' => $idEfector,
            'filtros' => [
                'id_piso' => $idPiso,
                'id_sala' => $idSala,
            ],
            'resumen' => array_merge($counts, [
                'ocupacion_pct' => $ocupacionPct,
            ]),
            'pisos' => $pisosOut,
            'resumen_texto' => $this->formatResumenTexto($counts, $ocupacionPct),
        ];
    }

    /**
     * @param array<string, int> $counts
     */
    private function formatResumenTexto(array $counts, ?float $ocupacionPct): string
    {
        $total = (int) ($counts['camas_total'] ?? 0);
        $ocupadas = (int) ($counts['ocupadas'] ?? 0);
        $libres = (int) ($counts['libres'] ?? 0);
        $bloq = (int) ($counts['bloqueadas'] ?? 0);
        $aisl = (int) ($counts['aislamiento'] ?? 0);
        $pct = $ocupacionPct !== null ? " ({$ocupacionPct}% ocupación)" : '';

        return "Camas: {$total} · Ocupadas: {$ocupadas}{$pct} · Libres: {$libres} · Bloqueadas: {$bloq} · Aislamiento: {$aisl}";
    }

    /**
     * @param array<int, SegNivelInternacion> $porCama
     * @return array<string, mixed>
     */
    private function serializeCama(InfraestructuraCama $cama, ?SegNivelInternacion $internacion): array
    {
        $estadoMapa = $this->resolveEstadoMapa($cama, $internacion);
        $paciente = $internacion?->paciente;
        $nombre = $paciente && method_exists($paciente, 'getNombreCompleto')
            ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : null;

        return [
            'id' => (int) $cama->id,
            'nro_cama' => $cama->nro_cama,
            'estado_mapa' => $estadoMapa,
            'estado_cama' => (string) ($cama->estado ?? ''),
            'internacion_id' => $internacion ? (int) $internacion->id : null,
            'id_persona' => $internacion ? (int) $internacion->id_persona : null,
            'paciente_nombre' => $nombre,
            'paciente_documento' => $paciente ? (string) ($paciente->documento ?? '') : null,
            'dias_internacion' => $internacion ? $this->diasDesdeIngreso($internacion) : null,
            'id_guardia' => $internacion && !empty($internacion->id_guardia)
                ? (int) $internacion->id_guardia
                : null,
            'ingreso_url' => $estadoMapa === self::ESTADO_LIBRE
                ? '/internacion/ingreso?id=' . (int) $cama->id
                : null,
            'ver_url' => $internacion
                ? '/internacion/view?id=' . (int) $internacion->id
                : null,
        ];
    }

    private function resolveEstadoMapa(InfraestructuraCama $cama, ?SegNivelInternacion $internacion): string
    {
        if ($internacion !== null) {
            return self::ESTADO_OCUPADA;
        }
        $raw = strtolower(trim((string) ($cama->estado ?? '')));
        if ($raw === self::ESTADO_BLOQUEADA) {
            return self::ESTADO_BLOQUEADA;
        }
        if ($raw === self::ESTADO_AISLAMIENTO) {
            return self::ESTADO_AISLAMIENTO;
        }
        if ($raw === 'ocupada') {
            return self::ESTADO_LIBRE;
        }

        return self::ESTADO_LIBRE;
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
                $hoy = new \DateTimeImmutable('today');

                return (int) $hoy->diff($dt)->days;
            }
        }

        return null;
    }

    /**
     * @return SegNivelInternacion[]
     */
    private function loadInternacionesActivasPorEfector(int $idEfector): array
    {
        return SegNivelInternacion::find()
            ->alias('i')
            ->innerJoinWith([
                'cama.sala.piso' => static function (ActiveQuery $q) use ($idEfector): void {
                    $q->andWhere(['infraestructura_piso.id_efector' => $idEfector]);
                },
            ])
            ->with(['paciente'])
            ->andWhere(['i.fecha_fin' => null])
            ->all();
    }
}
