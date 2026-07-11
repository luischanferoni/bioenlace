<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Scheduling\Service\TurnoSlotOfferUiPresenter;
use Yii;

/**
 * Resumen textual de turnos pendientes para el asistente (todos los canales).
 * Declared as draft_hydrator.handler: scheduling.turnos_listar_como_paciente
 */
final class TurnosVerMisTurnosFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $limit = isset($options['limit']) ? (int) $options['limit'] : 10;
        $limit = max(1, min(20, $limit));

        $params = [
            'alcance' => 'pendientes',
            'limit' => $limit,
            'offset' => 0,
        ];

        try {
            $data = (new TurnoPacienteListadoService())->list($params);
        } catch (\Throwable $e) {
            Yii::warning(
                'TurnosVerMisTurnosFlowDraftHydrator: ' . $e->getMessage(),
                'asistente'
            );
            $draft['assistant_text'] = 'No pude consultar tus turnos ahora. Probá de nuevo en unos minutos.';
            $body['draft'] = $draft;

            return;
        }

        $turnos = isset($data['turnos']) && is_array($data['turnos']) ? $data['turnos'] : [];
        $lines = [];
        foreach ($turnos as $t) {
            if (!is_array($t)) {
                continue;
            }
            $label = self::formatTurnoLine($t);
            if ($label !== '') {
                $lines[] = '• ' . $label;
            }
        }

        if ($lines === []) {
            $draft['assistant_text'] = 'No tenés turnos pendientes por ahora.';
        } else {
            $total = isset($data['total']) ? (int) $data['total'] : count($lines);
            $header = $total > count($lines)
                ? 'Tus próximos turnos (mostrando ' . count($lines) . ' de ' . $total . '):'
                : 'Tus próximos turnos:';
            $draft['assistant_text'] = $header . "\n" . implode("\n", $lines);
        }

        $body['draft'] = $draft;
    }

    /**
     * @param array<string, mixed> $t
     */
    private static function formatTurnoLine(array $t): string
    {
        $fecha = isset($t['fecha']) ? (string) $t['fecha'] : '';
        $hora = isset($t['hora']) ? (string) $t['hora'] : '';
        $svc = isset($t['servicio']) ? trim((string) $t['servicio']) : '';
        $prof = isset($t['profesional']) ? trim((string) $t['profesional']) : '';

        $fechaAmigable = $fecha !== '' ? TurnoSlotOfferUiPresenter::friendlyDayHeading($fecha) : '';
        $horaCorta = self::formatHoraCorta($hora);
        $cuando = trim($fechaAmigable . ($horaCorta !== '' ? ' · ' . $horaCorta : ''));

        $parts = array_values(array_filter([$cuando, $svc, $prof], static fn ($p) => $p !== ''));
        $label = implode(' · ', $parts);
        if ($label === '') {
            $id = isset($t['id']) ? (int) $t['id'] : 0;

            return $id > 0 ? 'Turno #' . $id : '';
        }
        if (!empty($t['en_resolucion'])) {
            $label = 'En reubicación: ' . $label;
        }

        return $label;
    }

    private static function formatHoraCorta(string $hora): string
    {
        $hora = trim($hora);
        if ($hora === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}:\d{2})/', $hora, $m)) {
            return $m[1];
        }

        return $hora;
    }
}
