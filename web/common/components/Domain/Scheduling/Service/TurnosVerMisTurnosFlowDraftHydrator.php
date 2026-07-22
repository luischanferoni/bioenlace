<?php

namespace common\components\Domain\Scheduling\Service;

use Yii;

/**
 * Resumen textual de turnos del paciente para el asistente (todos los canales).
 * Declared as draft_hydrator.handler: scheduling.turnos_listar_como_paciente
 *
 * Options YAML: `alcance` (pendientes|pasados), `limit` (1–20; pasados tope 10).
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
        $alcance = trim((string) ($options['alcance'] ?? 'pendientes'));
        if (!in_array($alcance, ['pendientes', 'pasados'], true)) {
            $alcance = 'pendientes';
        }
        $limit = isset($options['limit']) ? (int) $options['limit'] : ($alcance === 'pasados' ? 5 : 10);
        $maxLimit = $alcance === 'pasados' ? 10 : 20;
        $limit = max(1, min($maxLimit, $limit));

        $params = [
            'alcance' => $alcance,
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

        $esPasados = $alcance === 'pasados';
        if ($lines === []) {
            $draft['assistant_text'] = $esPasados
                ? 'No tenés turnos anteriores para mostrar.'
                : 'No tenés turnos pendientes por ahora.';
        } else {
            $total = isset($data['total']) ? (int) $data['total'] : count($lines);
            if ($esPasados) {
                $header = $total > count($lines)
                    ? 'Tus turnos anteriores (últimos ' . count($lines) . ' de ' . $total . '):'
                    : 'Tus turnos anteriores:';
            } else {
                $header = $total > count($lines)
                    ? 'Tus próximos turnos (mostrando ' . count($lines) . ' de ' . $total . '):'
                    : 'Tus próximos turnos:';
            }
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
