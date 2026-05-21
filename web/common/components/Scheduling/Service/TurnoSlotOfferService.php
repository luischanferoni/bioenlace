<?php

namespace common\components\Scheduling\Service;

use Yii;

/**
 * Oferta de slots libres: lista desde {@see TurnoSlotFinder} y, si hace falta, agrupación por día y franja mañana/tarde.
 */
class TurnoSlotOfferService
{
    /**
     * @param array<string, mixed> $criteria mismo contrato que {@see TurnoSlotFinder::findAvailableSlots}
     * @param int $limite máximo de slots a devolver
     * @param int $maxDias días a explorar en la búsqueda
     * @param string $franjaTardeDesde hora "HH:MM"; horas &lt; este valor van a `manana`, el resto a `tarde`
     * @return array{
     *   limite: int,
     *   max_dias_busqueda: int,
     *   franja_tarde_desde: string,
     *   por_dia: list<array{fecha: string, manana: list<array<string, mixed>>, tarde: list<array<string, mixed>>}>,
     *   total: int
     * }
     */
    public static function buildGrouped(array $criteria, int $limite, int $maxDias, string $franjaTardeDesde): array
    {
        $limite = max(1, $limite);
        $maxDias = max(1, $maxDias);
        $criteria['max_dias'] = $maxDias;

        $plano = TurnoSlotFinder::findAvailableSlots($criteria, $limite);

        return self::buildOfferFromPlano($plano, $franjaTardeDesde, $limite, $maxDias);
    }

    /**
     * Agrupa una lista plana de slots (mismo shape que {@see TurnoSlotFinder::findAvailableSlots}) por día y franja.
     * Útil cuando los slots ya vienen de otra fuente (p. ej. reprogramación).
     *
     * @param list<array<string, mixed>> $plano
     * @return array{
     *   limite: int,
     *   max_dias_busqueda: int,
     *   franja_tarde_desde: string,
     *   por_dia: list<array{fecha: string, manana: list<array<string, mixed>>, tarde: list<array<string, mixed>>}>,
     *   total: int
     * }
     */
    public static function buildOfferFromPlano(array $plano, string $franjaTardeDesde, int $limiteReported, int $maxDiasReported): array
    {
        $porFecha = [];
        foreach ($plano as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            // Normalizar `slot_id` PES-first cuando sea posible.
            if (!isset($slot['slot_id']) || !is_string($slot['slot_id']) || trim($slot['slot_id']) === '') {
                $fechaSlot = (string) ($slot['fecha'] ?? '');
                $horaSlot = (string) ($slot['hora'] ?? '');
                $idPes = isset($slot['id_profesional_efector_servicio']) && $slot['id_profesional_efector_servicio'] !== null
                    ? (int) $slot['id_profesional_efector_servicio']
                    : 0;
                if ($fechaSlot !== '' && $horaSlot !== '' && $idPes > 0) {
                    $intervalo = 15;
                    $ver = \common\models\ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha($idPes, $fechaSlot);
                    if ($ver !== null) {
                        $intervalo = $ver->getIntervaloMinutosEfectivo();
                    }
                    $slot['slot_id'] = 'pes:' . $idPes . '|' . $fechaSlot . '|' . $horaSlot . '|' . $intervalo;
                    $slot['intervalo_minutos'] = $intervalo;
                }
            }
            $fecha = isset($slot['fecha']) ? (string) $slot['fecha'] : '';
            if ($fecha === '') {
                continue;
            }
            if (!isset($porFecha[$fecha])) {
                $porFecha[$fecha] = ['manana' => [], 'tarde' => []];
            }
            $hora = isset($slot['hora']) ? (string) $slot['hora'] : '';
            if (self::esManana($hora, $franjaTardeDesde)) {
                $porFecha[$fecha]['manana'][] = $slot;
            } else {
                $porFecha[$fecha]['tarde'][] = $slot;
            }
        }

        $porDia = [];
        foreach ($porFecha as $fecha => $franjas) {
            $porDia[] = [
                'fecha' => $fecha,
                'manana' => $franjas['manana'],
                'tarde' => $franjas['tarde'],
            ];
        }

        $dias = [];
        foreach (array_keys($porFecha) as $f) {
            $dias[] = [
                'id' => (string) $f,
                'label' => TurnoSlotOfferUiPresenter::friendlyDayHeading((string) $f),
            ];
        }
        $franjas = [
            ['id' => 'manana', 'label' => 'Por la mañana'],
            ['id' => 'tarde', 'label' => 'Por la tarde'],
        ];

        return [
            'limite' => max(1, $limiteReported),
            'max_dias_busqueda' => max(1, $maxDiasReported),
            'franja_tarde_desde' => $franjaTardeDesde,
            'por_dia' => $porDia,
            'available_filters' => [
                'dias' => $dias,
                'franjas' => $franjas,
            ],
            'total' => count($plano),
        ];
    }

    private static function esManana(string $hora, string $franjaTardeDesde): bool
    {
        return strcmp($hora, $franjaTardeDesde) < 0;
    }

    /**
     * Defaults para autogestión paciente desde Yii params `turnosPaciente`.
     *
     * @return array{limite: int, max_dias: int, franja_tarde_desde: string, min_minutos_desde_ahora: int}
     */
    public static function leerDefaultsTurnosPaciente(): array
    {
        $p = Yii::$app->params['turnosPaciente'] ?? [];

        return [
            'limite' => max(1, (int) ($p['slots_oferta_max'] ?? 20)),
            'max_dias' => max(1, (int) ($p['slots_busqueda_max_dias'] ?? 45)),
            'franja_tarde_desde' => (string) ($p['franja_tarde_desde'] ?? '13:00'),
            'min_minutos_desde_ahora' => max(0, (int) ($p['slots_min_minutos_desde_ahora'] ?? 15)),
        ];
    }
}
