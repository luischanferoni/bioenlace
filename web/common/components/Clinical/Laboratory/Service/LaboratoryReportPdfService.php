<?php

namespace common\components\Clinical\Laboratory\Service;

use common\models\Clinical\DiagnosticReport;
use common\models\Person\Persona;
use kartik\mpdf\Pdf;
use Yii;

/**
 * Genera PDF de un informe de laboratorio persistido (analitos normalizados + conclusión).
 */
final class LaboratoryReportPdfService
{
    /**
     * @param array<string, mixed> $serialized salida de {@see LaboratoryResultQueryService}
     */
    public function renderBinary(DiagnosticReport $report, array $serialized, ?Persona $persona = null): string
    {
        $html = $this->buildHtml($report, $serialized, $persona);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'destination' => Pdf::DEST_STRING,
            'content' => $html,
            'cssInline' => 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:6px;} th{background:#f0f0f0;}',
            'options' => ['title' => 'Informe de laboratorio'],
            'methods' => [
                'SetHeader' => ['Informe de laboratorio — Bioenlace'],
                'SetFooter' => ['{PAGENO}'],
            ],
        ]);

        $content = $pdf->render();
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('No se pudo generar el PDF del informe.');
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $serialized
     */
    private function buildHtml(DiagnosticReport $report, array $serialized, ?Persona $persona): string
    {
        $patient = 'Paciente';
        if ($persona !== null) {
            $patient = trim($persona->nombre . ' ' . $persona->apellido);
            if ($persona->documento !== null && $persona->documento !== '') {
                $patient .= ' — DNI ' . $persona->documento;
            }
        }

        $title = htmlspecialchars((string) ($serialized['display'] ?? 'Informe de laboratorio'), ENT_QUOTES, 'UTF-8');
        $issued = htmlspecialchars((string) ($serialized['issuedAt'] ?? ''), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string) ($serialized['status'] ?? ''), ENT_QUOTES, 'UTF-8');
        $source = htmlspecialchars((string) ($serialized['sourceSystem'] ?? $report->source_system), ENT_QUOTES, 'UTF-8');
        $conclusion = nl2br(htmlspecialchars((string) ($serialized['conclusion'] ?? ''), ENT_QUOTES, 'UTF-8'));

        $rows = '';
        foreach ($serialized['observations'] ?? [] as $obs) {
            if (!is_array($obs)) {
                continue;
            }
            $name = htmlspecialchars((string) ($obs['display'] ?? $obs['code'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $val = htmlspecialchars(trim((string) ($obs['valueQuantity'] ?? $obs['display'] ?? '—')), ENT_QUOTES, 'UTF-8');
            $unit = htmlspecialchars((string) ($obs['valueUnit'] ?? ''), ENT_QUOTES, 'UTF-8');
            $when = htmlspecialchars((string) ($obs['effectiveDatetime'] ?? ''), ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td>{$name}</td><td>{$val}</td><td>{$unit}</td><td>{$when}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4">Sin analitos registrados.</td></tr>';
        }

        return <<<HTML
<h2>{$title}</h2>
<p><strong>Paciente:</strong> {$patient}</p>
<p><strong>Fecha:</strong> {$issued} &nbsp; <strong>Estado:</strong> {$status} &nbsp; <strong>Origen:</strong> {$source}</p>
<h3>Analitos</h3>
<table>
<thead><tr><th>Determinación</th><th>Valor</th><th>Unidad</th><th>Fecha</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<h3>Conclusión</h3>
<p>{$conclusion}</p>
HTML;
    }
}
