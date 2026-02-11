<?php

namespace common\components;

use Yii;
use common\models\Turno;
use common\models\Agenda_rrhh;
use common\models\ServiciosEfector;
use common\models\RrhhServicio;
use yii\db\Expression;

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
        $idServicio = $criteria['id_servicio'] ?? null;
        if (!$idServicio) {
            throw new \InvalidArgumentException('TurnoSlotFinder::findFirstAvailable requiere id_servicio');
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

        // Preprocesar restricciones en estructuras útiles
        $diasSemanaExcluidos = self::buildDiasSemanaExcluidos($restricciones);
        $franjasExcluidas = self::buildFranjasExcluidas($restricciones);

        // RRHH + agendas que atienden este servicio en este efector
        $queryRrhhServicios = RrhhServicio::find();
        if (!is_object($queryRrhhServicios)) {
            // Salvaguarda teórica: en Yii2 find() debería devolver siempre un ActiveQuery
            return null;
        }
        $queryRrhhServicios->from(['rs' => RrhhServicio::tableName()])
            ->leftJoin('rrhh_efector re', 're.id_rr_hh = rs.id_rr_hh')
            ->andWhere(['re.id_efector' => $idEfector])
            ->andWhere(['rs.id_servicio' => $idServicio]);

        /** @var RrhhServicio[] $rrhhServicios */
        $rrhhServicios = $queryRrhhServicios->all();

        if (!$rrhhServicios) {
            return null;
        }

        // Pre-cargar agendas por id_rrhh_servicio_asignado
        $idsRrhhServicio = array_map(function (RrhhServicio $rs) {
            return $rs->id;
        }, $rrhhServicios);

        $queryAgendas = Agenda_rrhh::find();
        if (!is_object($queryAgendas)) {
            return null;
        }
        $queryAgendas->andWhere(['in', 'id_rrhh_servicio_asignado', $idsRrhhServicio])
            ->indexBy('id_rrhh_servicio_asignado');

        /** @var Agenda_rrhh[] $agendas */
        $agendas = $queryAgendas->all();

        if (!$agendas) {
            return null;
        }

        // Columnas de agenda por día de la semana (N=1..7 -> índice 0..6)
        $columnasAgenda = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];

        // Búsqueda día a día hasta max_dias
        for ($offset = 0; $offset < $maxDias; $offset++) {
            $dia = date('Y-m-d', strtotime($fechaDesde . " +{$offset} days"));
            $nroDiaSemana = (int)date('N', strtotime($dia)); // 1 (lunes) a 7 (domingo)

            if (in_array($nroDiaSemana, $diasSemanaExcluidos, true)) {
                continue;
            }

            $colAgenda = $columnasAgenda[$nroDiaSemana - 1] ?? null;
            if (!$colAgenda) {
                continue;
            }

            foreach ($agendas as $agenda) {
                /** @var Agenda_rrhh $agenda */
                $colValue = $agenda->{$colAgenda};
                if (!$colValue) {
                    continue;
                }

                $horariosAgenda = array_map('intval', explode(',', $colValue));
                if (empty($horariosAgenda)) {
                    continue;
                }

                // Determinar intervalo entre pacientes
                if (is_null($agenda->cupo_pacientes) || $agenda->cupo_pacientes == 0) {
                    $minutosXPaciente = 15;
                    $agregoSegundos = false;
                    $cupoPacientes = count($horariosAgenda) * 5;
                } else {
                    $minutosXPaciente = 60 * count($horariosAgenda) / $agenda->cupo_pacientes;
                    $agregoSegundos = ($minutosXPaciente - (int)$minutosXPaciente) >= 0.5;
                    $cupoPacientes = $agenda->cupo_pacientes;
                }

                $slots = self::crearSlots($horariosAgenda, $cupoPacientes, $minutosXPaciente, $agregoSegundos);
                if (empty($slots)) {
                    continue;
                }

                // Filtrar por franja horaria excluida y turnos ya tomados
                foreach ($slots as $hora) {
                    if (self::isHoraExcluida($hora, $franjasExcluidas)) {
                        continue;
                    }

                    // No permitir horas pasadas si es hoy
                    if ($dia === date('Y-m-d') && $hora <= date('H:i')) {
                        continue;
                    }

                    // Verificar que no exista ya un turno activo para ese RRHH/servicio/hora
                    $existe = Turno::findActive()
                        ->andWhere([
                            'fecha' => $dia,
                            'hora' => $hora . ':00',
                            'id_rrhh_servicio_asignado' => $agenda->id_rrhh_servicio_asignado,
                        ])
                        ->exists();

                    if ($existe) {
                        continue;
                    }

                    // Resolver id_rr_hh desde rrhh_servicio
                    /** @var RrhhServicio $rrhhServ */
                    $rrhhServ = null;
                    foreach ($rrhhServicios as $rs) {
                        if ((int)$rs->id === (int)$agenda->id_rrhh_servicio_asignado) {
                            $rrhhServ = $rs;
                            break;
                        }
                    }
                    if (!$rrhhServ) {
                        continue;
                    }

                    return [
                        'fecha' => $dia,
                        'hora' => $hora,
                        'id_rr_hh' => (int)$rrhhServ->id_rr_hh,
                        'id_rrhh_servicio_asignado' => (int)$agenda->id_rrhh_servicio_asignado,
                        'id_efector' => (int)$idEfector,
                        'id_servicio' => (int)$idServicio,
                    ];
                }
            }
        }

        // No se encontró ningún slot en el rango
        return null;
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

    /**
     * Copia simplificada de la lógica de TurnosController::crearSlots,
     * extraída aquí para poder reutilizarla sin depender del controlador.
     *
     * @param int[] $horariosAgenda
     * @param int $cupoPacientes
     * @param float|int $minutosXPaciente
     * @param bool $agregoSegundos
     * @return string[] Lista de horas en formato HH:MM
     */
    private static function crearSlots(array $horariosAgenda, int $cupoPacientes, $minutosXPaciente, bool $agregoSegundos): array
    {
        $intervalos = [];
        $intervaloActual = [];

        for ($i = 0; $i < count($horariosAgenda); $i++) {
            if (empty($intervaloActual)) {
                $intervaloActual[] = $horariosAgenda[$i];
            } else {
                if ($horariosAgenda[$i] === $intervaloActual[count($intervaloActual) - 1] + 1) {
                    $intervaloActual[] = $horariosAgenda[$i];
                } else {
                    $intervalos[] = $intervaloActual;
                    $intervaloActual = [$horariosAgenda[$i]];
                }
            }
        }
        if (!empty($intervaloActual)) {
            $intervalos[] = $intervaloActual;
        }

        $slots = [];
        $minutosXPaciente = (int)$minutosXPaciente;

        foreach ($intervalos as $horarios) {
            $inicio = new \DateTime(sprintf('%02d:00', $horarios[0]));
            $ultHora = new \DateTime(sprintf('%02d:00', $horarios[count($horarios) - 1]));
            $fin = $ultHora->modify('+60 minutes');

            while ($inicio < $fin && $cupoPacientes > 0) {
                $slots[] = $inicio->format('H:i');
                if ($agregoSegundos) {
                    $inicio->modify("+{$minutosXPaciente} minutes 30 seconds");
                } else {
                    $inicio->modify("+{$minutosXPaciente} minutes");
                }
                $cupoPacientes--;
            }
        }

        return $slots;
    }
}

