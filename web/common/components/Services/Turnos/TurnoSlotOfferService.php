<?php

namespace common\components\Services\Turnos;

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

        $porFecha = [];
        foreach ($plano as $slot) {
            $fecha = $slot['fecha'];
            if (!isset($porFecha[$fecha])) {
                $porFecha[$fecha] = ['manana' => [], 'tarde' => []];
            }
            $hora = $slot['hora'];
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

        return [
            'limite' => $limite,
            'max_dias_busqueda' => $maxDias,
            'franja_tarde_desde' => $franjaTardeDesde,
            'por_dia' => $porDia,
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
     * @return array{limite: int, max_dias: int, franja_tarde_desde: string}
     */
    public static function leerDefaultsTurnosPaciente(): array
    {
        $p = Yii::$app->params['turnosPaciente'] ?? [];

        return [
            'limite' => max(1, (int) ($p['slots_oferta_max'] ?? 20)),
            'max_dias' => max(1, (int) ($p['slots_busqueda_max_dias'] ?? 45)),
            'franja_tarde_desde' => (string) ($p['franja_tarde_desde'] ?? '13:00'),
        ];
    }
}
