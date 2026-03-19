<?php

namespace common\components\Services\Turnos;

use Yii;
use common\models\Turno;
use common\models\Agenda_rrhh;
use common\models\RrhhServicio;

/**
 * Servicio de búsqueda de slots de turnos a partir de parámetros ya NORMALIZADOS.
 *
 * Esta clase NO interpreta lenguaje natural ni corrige texto.
 * Solo acepta valores ya normalizados (ej. dia_semana = LUNES, operador = ANY/NOT, etc.)
 * y devuelve el primer turno disponible que cumpla las restricciones.
 *
 * Pensado para ser usado por:
 * - Handlers de chatbot (TurnosHandler)
 * - Endpoints de API que implementen "lo antes posible", "cualquiera", "no los lunes", etc.
 */
class TurnoSlotFinder
{
    /**
     * Mapa de nombre de día (normalizado) a número ISO-8601 (1 = lunes, 7 = domingo)
     */
    public const DIAS_SEMANA = [
        'LUNES' => 1,
        'MARTES' => 2,
        'MIERCOLES' => 3,
        'MIÉRCOLES' => 3,
        'JUEVES' => 4,
        'VIERNES' => 5,
        'SABADO' => 6,
        'SÁBADO' => 6,
        'DOMINGO' => 7,
    ];

    /**
     * Franjas horarias estándar (pueden ajustarse desde configuración si hace falta)
     */
    public const FRANJAS_HORARIAS = [
        'MANANA' => ['08:00', '12:59'],
        'MAÑANA' => ['08:00', '12:59'],
        'TARDE' => ['13:00', '18:59'],
        'NOCHE' => ['19:00', '23:59'],
    ];

    /**
     * Operadores lógicos soportados en restricciones
     */
    public const OPERADOR_ANY = 'ANY';
    public const OPERADOR_NOT = 'NOT';

    /**
     * Buscar el PRIMER slot disponible que cumpla las restricciones.
     *
     * IMPORTANTE:
     * - Todos los parámetros deben venir ya normalizados por la IA / capa superior.
     * - Este método no interpreta lenguaje natural ni corrige typos.
     *
     * Ejemplo de $criteria:
     * [
     *   'id_servicio' => 10,
     *   'id_efector' => 3,                    // opcional; si null se usa getIdEfector()
     *   'fecha_desde' => '2026-02-11',        // opcional; default hoy
     *   'max_dias' => 30,                     // opcional; límite de búsqueda
     *   'restricciones' => [
     *       ['campo' => 'dia_semana', 'operador' => 'NOT', 'valor' => 'LUNES'],
     *       ['campo' => 'franja_horaria', 'operador' => 'NOT', 'valor' => 'TARDE'],
     *   ],
     * ]
     *
     * @param array $criteria
     * @return array|null [
     *   'fecha' => 'YYYY-MM-DD',
     *   'hora' => 'HH:MM',
     *   'id_rr_hh' => int,
     *   'id_rrhh_servicio_asignado' => int,
     *   'id_efector' => int,
     *   'id_servicio' => int,
     * ]
     */
    public static function findFirstAvailable(array $criteria): ?array
    {
        $found = self::findAvailableSlots($criteria, 1);
        return $found ? $found[0] : null;
    }

    /**
     * Lista hasta $limit slots libres (mismo criterio que findFirstAvailable).
     * Criterio opcional: id_rrhh_servicio_asignado — limita a esa agenda/profesional.
     *
     * @param array $criteria
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public static function findAvailableSlots(array $criteria, $limit = 10): array
    {
        $idServicio = $criteria['id_servicio'] ?? null;
        if (!$idServicio) {
            throw new \InvalidArgumentException('TurnoSlotFinder requiere id_servicio');
        }

        $idEfector = $criteria['id_efector'] ?? null;
        if (!$idEfector) {
            $idEfector = Yii::$app->user->getIdEfector();
        }
        if (!$idEfector) {
            throw new \RuntimeException('No se pudo determinar el efector para la búsqueda de slots');
        }

        $fechaDesde = $criteria['fecha_desde'] ?? date('Y-m-d');
        $maxDias = (int)($criteria['max_dias'] ?? 30);
        if ($maxDias <= 0) {
            $maxDias = 30;
        }

        $restricciones = $criteria['restricciones'] ?? [];
        $soloRrhhServicio = isset($criteria['id_rrhh_servicio_asignado'])
            ? (int) $criteria['id_rrhh_servicio_asignado']
            : null;

        $diasSemanaExcluidos = self::buildDiasSemanaExcluidos($restricciones);
        $franjasExcluidas = self::buildFranjasExcluidas($restricciones);

        $rrhhServicios = RrhhServicio::findPorServicioEfector((int) $idServicio, (int) $idEfector);
        if (empty($rrhhServicios)) {
            return [];
        }

        $idsRrhhServicio = array_map(function (RrhhServicio $rs) {
            return (int) $rs->id;
        }, $rrhhServicios);
        if ($soloRrhhServicio) {
            if (!in_array($soloRrhhServicio, $idsRrhhServicio, true)) {
                return [];
            }
            $idsRrhhServicio = [$soloRrhhServicio];
        }

        $agendas = Agenda_rrhh::findPorIdsRrhhServicio($idsRrhhServicio);
        if (empty($agendas)) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $out = [];

        for ($offset = 0; $offset < $maxDias && count($out) < $limit; $offset++) {
            $dia = date('Y-m-d', strtotime($fechaDesde . " +{$offset} days"));
            $nroDiaSemana = (int) date('N', strtotime($dia));

            if (in_array($nroDiaSemana, $diasSemanaExcluidos, true)) {
                continue;
            }

            foreach ($agendas as $agenda) {
                if (count($out) >= $limit) {
                    break 2;
                }
                $slots = $agenda->getSlotsParaDia($dia);
                if (empty($slots)) {
                    continue;
                }

                foreach ($slots as $hora) {
                    if (count($out) >= $limit) {
                        break 3;
                    }
                    if (self::isHoraExcluida($hora, $franjasExcluidas)) {
                        continue;
                    }
                    if ($dia === date('Y-m-d') && $hora <= date('H:i')) {
                        continue;
                    }
                    if (Turno::estaOcupadoSlot($agenda->id_rrhh_servicio_asignado, $dia, $hora)) {
                        continue;
                    }

                    $rrhhServ = null;
                    foreach ($rrhhServicios as $rs) {
                        if ((int) $rs->id === (int) $agenda->id_rrhh_servicio_asignado) {
                            $rrhhServ = $rs;
                            break;
                        }
                    }
                    if (!$rrhhServ) {
                        continue;
                    }

                    $out[] = [
                        'fecha' => $dia,
                        'hora' => $hora,
                        'id_rr_hh' => (int) $rrhhServ->id_rr_hh,
                        'id_rrhh_servicio_asignado' => (int) $agenda->id_rrhh_servicio_asignado,
                        'id_efector' => (int) $idEfector,
                        'id_servicio' => (int) $idServicio,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Construye lista de días de la semana (1..7) excluidos a partir de restricciones.
     *
     * @param array $restricciones
     * @return int[]
     */
    private static function buildDiasSemanaExcluidos(array $restricciones): array
    {
        $result = [];
        foreach ($restricciones as $r) {
            $campo = strtoupper($r['campo'] ?? '');
            $operador = strtoupper($r['operador'] ?? '');
            $valor = strtoupper($r['valor'] ?? '');

            if ($campo !== 'DIA_SEMANA' || $operador !== self::OPERADOR_NOT) {
                continue;
            }

            if (isset(self::DIAS_SEMANA[$valor])) {
                $n = self::DIAS_SEMANA[$valor];
                if (!in_array($n, $result, true)) {
                    $result[] = $n;
                }
            }
        }
        return $result;
    }

    /**
     * Construye lista de franjas horarias excluidas a partir de restricciones.
     *
     * @param array $restricciones
     * @return array<string, array{0:string,1:string}>
     */
    private static function buildFranjasExcluidas(array $restricciones): array
    {
        $result = [];
        foreach ($restricciones as $r) {
            $campo = strtoupper($r['campo'] ?? '');
            $operador = strtoupper($r['operador'] ?? '');
            $valor = strtoupper($r['valor'] ?? '');

            if ($campo !== 'FRANJA_HORARIA' || $operador !== self::OPERADOR_NOT) {
                continue;
            }

            if (isset(self::FRANJAS_HORARIAS[$valor])) {
                $result[$valor] = self::FRANJAS_HORARIAS[$valor];
            }
        }
        return $result;
    }

    /**
     * Indica si una hora concreta está dentro de alguna franja excluida.
     *
     * @param string $hora HH:MM
     * @param array<string, array{0:string,1:string}> $franjasExcluidas
     * @return bool
     */
    private static function isHoraExcluida(string $hora, array $franjasExcluidas): bool
    {
        if (empty($franjasExcluidas)) {
            return false;
        }

        foreach ($franjasExcluidas as $franja) {
            [$desde, $hasta] = $franja;
            if ($hora >= $desde && $hora <= $hasta) {
                return true;
            }
        }

        return false;
    }
}

