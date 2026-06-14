<?php

namespace common\components\Domain\Clinical\LegalRecord;

use kartik\mpdf\Pdf;
use Yii;

/**
 * PDF del expediente legal (documento amplio para staff).
 */
final class LegalRecordExportPdfService
{
    /**
     * @param array<string, mixed> $payload salida de {@see LegalRecordExportDataCollector}
     */
    public function renderBinary(array $payload): string
    {
        $html = $this->buildHtml($payload);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'destination' => Pdf::DEST_STRING,
            'content' => $html,
            'cssInline' => 'body{font-family:DejaVu Sans,sans-serif;font-size:10px;line-height:1.35;} h2{font-size:14px;margin-top:16px;} h3{font-size:12px;} table{width:100%;border-collapse:collapse;margin:8px 0;} th,td{border:1px solid #ccc;padding:5px;vertical-align:top;} th{background:#eee;} .meta{color:#444;font-size:9px;} .encounter{border:1px solid #bbb;padding:10px;margin:12px 0;page-break-inside:avoid;}',
            'options' => ['title' => 'Expediente legal'],
            'methods' => [
                'SetHeader' => ['Expediente legal — Bioenlace — CONFIDENCIAL'],
                'SetFooter' => ['{PAGENO}'],
            ],
        ]);

        $content = $pdf->render();
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('No se pudo generar el PDF del expediente legal.');
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildHtml(array $payload): string
    {
        $paciente = $payload['paciente'] ?? [];
        $nombre = htmlspecialchars((string) ($paciente['nombreCompleto'] ?? ''), ENT_QUOTES, 'UTF-8');
        $doc = htmlspecialchars((string) ($paciente['documento'] ?? ''), ENT_QUOTES, 'UTF-8');
        $hc = htmlspecialchars((string) ($paciente['numeroHistoriaClinica'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $efector = htmlspecialchars((string) (($payload['efector']['nombre'] ?? '') ?: 'Todos los efectores'), ENT_QUOTES, 'UTF-8');
        $genAt = htmlspecialchars((string) ($payload['generatedAt'] ?? ''), ENT_QUOTES, 'UTF-8');

        $med = $payload['informacionMedica'] ?? [];
        $medHtml = $this->renderTermList('Condiciones activas', $med['condicionesActivas'] ?? [])
            . $this->renderTermList('Condiciones crónicas', $med['condicionesCronicas'] ?? [])
            . $this->renderTermList('Alergias', $med['alergias'] ?? [])
            . $this->renderTermList('Antecedentes personales', $med['antecedentesPersonales'] ?? [])
            . $this->renderTermList('Antecedentes familiares', $med['antecedentesFamiliares'] ?? []);

        $atencionesHtml = '';
        foreach ($payload['atenciones'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $atencionesHtml .= $this->renderEncounterBlock($enc);
        }
        if ($atencionesHtml === '') {
            $atencionesHtml = '<p>Sin atenciones ambulatorias finalizadas en el alcance solicitado.</p>';
        }

        return <<<HTML
<h1>Expediente legal</h1>
<p class="meta">Generado: {$genAt} · Efector: {$efector}</p>
<h2>Identificación del paciente</h2>
<p><strong>Nombre:</strong> {$nombre}<br>
<strong>Documento:</strong> {$doc}<br>
<strong>Historia clínica (efector):</strong> {$hc}</p>
<h2>Información médica estructurada</h2>
{$medHtml}
<h2>Atenciones ambulatorias</h2>
<p class="meta">Incluye nota clínica registrada al cierre, pedidos, recetas e informes de laboratorio vinculados.</p>
{$atencionesHtml}
<p class="meta" style="margin-top:24px;">Documento generado por Bioenlace para uso interno del equipo de salud. No sustituye la historia clínica oficial del prestador sin validación jurídica.</p>
HTML;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function renderTermList(string $title, array $items): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        if ($items === []) {
            return "<h3>{$titleEsc}</h3><p>—</p>";
        }
        $lis = '';
        foreach ($items as $row) {
            $term = htmlspecialchars((string) ($row['termino'] ?? $row['codigo'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $lis .= "<li>{$term}</li>";
        }

        return "<h3>{$titleEsc}</h3><ul>{$lis}</ul>";
    }

    /**
     * @param array<string, mixed> $enc
     */
    private function renderEncounterBlock(array $enc): string
    {
        $fecha = htmlspecialchars((string) ($enc['periodEnd'] ?? $enc['periodStart'] ?? ''), ENT_QUOTES, 'UTF-8');
        $efector = htmlspecialchars((string) ($enc['efector']['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
        $prof = htmlspecialchars((string) ($enc['profesional']['display'] ?? ''), ENT_QUOTES, 'UTF-8');
        $motivo = nl2br(htmlspecialchars((string) ($enc['reasonText'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $nota = nl2br(htmlspecialchars((string) ($enc['narrativeText'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $pub = !empty($enc['resumenPublicadoPaciente'])
            ? '<p class="meta">Resumen publicado al paciente: sí</p>'
            : '';

        $rxRows = '';
        foreach ($enc['prescriptions'] ?? [] as $rx) {
            if (!is_array($rx)) {
                continue;
            }
            $rxRows .= '<tr><td>Receta #' . (int) ($rx['id'] ?? 0)
                . '</td><td>' . htmlspecialchars((string) ($rx['issuedAt'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $rxTable = $rxRows !== ''
            ? "<h4>Recetas</h4><table><thead><tr><th>Id</th><th>Emitida</th></tr></thead><tbody>{$rxRows}</tbody></table>"
            : '';

        $orderRows = '';
        foreach ($enc['orders'] ?? [] as $o) {
            if (!is_array($o)) {
                continue;
            }
            $orderRows .= '<tr><td>' . htmlspecialchars((string) ($o['display'] ?? ''), ENT_QUOTES, 'UTF-8')
                . '</td><td>' . htmlspecialchars((string) ($o['category'] ?? ''), ENT_QUOTES, 'UTF-8')
                . '</td><td>' . htmlspecialchars((string) ($o['resultStatus'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $ordersTable = $orderRows !== ''
            ? "<h4>Pedidos</h4><table><thead><tr><th>Descripción</th><th>Categoría</th><th>Resultado</th></tr></thead><tbody>{$orderRows}</tbody></table>"
            : '';

        $labRows = '';
        foreach ($enc['laboratoryReports'] ?? [] as $lr) {
            if (!is_array($lr)) {
                continue;
            }
            $labRows .= '<tr><td>' . htmlspecialchars((string) ($lr['display'] ?? ''), ENT_QUOTES, 'UTF-8')
                . '</td><td>' . htmlspecialchars((string) ($lr['issuedAt'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $labTable = $labRows !== ''
            ? "<h4>Laboratorio</h4><table><thead><tr><th>Informe</th><th>Fecha</th></tr></thead><tbody>{$labRows}</tbody></table>"
            : '';

        return <<<HTML
<div class="encounter">
<h3>Atención {$fecha} — {$efector}</h3>
<p><strong>Profesional:</strong> {$prof}</p>
<p><strong>Motivo:</strong> {$motivo}</p>
<p><strong>Nota clínica / evolución:</strong><br>{$nota}</p>
{$pub}
{$rxTable}
{$ordersTable}
{$labTable}
</div>
HTML;
    }

    public function resolveStorageDir(): string
    {
        $dir = Yii::getAlias('@runtime/legal-record-exports');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de expedientes legales.');
        }

        return $dir;
    }
}
