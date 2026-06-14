<?php

namespace common\components\Domain\Clinical\PatientSummary;

/**
 * Texto plano para bloques ui_json `message` (resumen de atención paciente).
 */
final class PatientEncounterSummaryUiFormatter
{
    /**
     * @param array<string, mixed> $detail
     */
    public function formatDetailMessage(array $detail): string
    {
        $lines = [];
        $efector = $detail['efector']['nombre'] ?? null;
        $prof = $detail['profesional']['display'] ?? null;
        $fecha = $detail['periodEnd'] ?? $detail['publishedAt'] ?? null;

        if ($efector) {
            $lines[] = (string) $efector;
        }
        if ($prof) {
            $lines[] = 'Profesional: ' . $prof;
        }
        if ($fecha) {
            $lines[] = 'Fecha: ' . $fecha;
        }

        $reason = trim((string) ($detail['reasonText'] ?? ''));
        if ($reason !== '') {
            $lines[] = '';
            $lines[] = 'Motivo: ' . $reason;
        }

        $lines[] = '';
        $lines[] = 'Resumen de la atención:';
        $narrative = trim((string) ($detail['narrativeText'] ?? ''));
        $lines[] = $narrative !== '' ? $narrative : 'Sin resumen narrativo disponible.';

        $lines[] = '';
        $lines[] = $this->formatLinksSection($detail);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function formatLinksSection(array $detail): string
    {
        $parts = ['Próximos pasos y documentos:'];

        $rx = $detail['prescriptions'] ?? [];
        if (is_array($rx) && $rx !== []) {
            foreach ($rx as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $parts[] = '* Receta electrónica #' . (int) ($p['id'] ?? 0);
            }
        } else {
            $parts[] = '* Sin receta electrónica emitida en esta atención.';
        }

        $orders = $detail['orders'] ?? [];
        if (is_array($orders) && $orders !== []) {
            foreach ($orders as $o) {
                if (!is_array($o)) {
                    continue;
                }
                $label = trim((string) ($o['display'] ?? 'Pedido'));
                $cat = (string) ($o['category'] ?? '');
                $status = (string) ($o['resultStatus'] ?? '');
                $suffix = $status === 'available' ? ' (resultado disponible)' : ($status === 'pending' ? ' (pendiente)' : '');
                $parts[] = '* ' . $label . ($cat !== '' ? " [{$cat}]" : '') . $suffix;
            }
        }

        $labs = $detail['laboratoryReports'] ?? [];
        if (is_array($labs) && $labs !== []) {
            foreach ($labs as $lr) {
                if (!is_array($lr)) {
                    continue;
                }
                $parts[] = '* Resultado de laboratorio: ' . trim((string) ($lr['display'] ?? 'Informe'));
            }
        }

        return implode("\n", $parts);
    }
}
