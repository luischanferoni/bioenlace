<?php

namespace common\components\Domain\Clinical\Prescription\Service;

use common\components\Domain\Clinical\Prescription\Support\PrescriptionDocumentSupport;
use common\models\Clinical\ElectronicPrescription;
use common\models\Person\Persona;
use kartik\mpdf\Pdf;

/**
 * PDF de receta emitida (Fase 2 — sin imagen QR; código de verificación en texto).
 */
final class ElectronicPrescriptionPdfService
{
    private ElectronicPrescriptionPresentationService $presentation;

    public function __construct(?ElectronicPrescriptionPresentationService $presentation = null)
    {
        $this->presentation = $presentation ?? new ElectronicPrescriptionPresentationService();
    }

    public function renderBinary(ElectronicPrescription $rx, ?Persona $patient = null): string
    {
        $html = $this->buildHtml($rx, $patient);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'destination' => Pdf::DEST_STRING,
            'content' => $html,
            'cssInline' => 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;} table{width:100%;border-collapse:collapse;margin-top:8px;} th,td{border:1px solid #ccc;padding:6px;} th{background:#f0f0f0;} .verify{font-family:monospace;font-size:12px;letter-spacing:1px;}',
            'options' => ['title' => 'Receta electrónica'],
            'methods' => [
                'SetHeader' => ['Receta electrónica — Bioenlace'],
                'SetFooter' => ['{PAGENO}'],
            ],
        ]);

        $content = $pdf->render();
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('No se pudo generar el PDF de la receta.');
        }

        return $content;
    }

    private function buildHtml(ElectronicPrescription $rx, ?Persona $patient): string
    {
        $patientLabel = htmlspecialchars($this->presentation->formatPatientLabel($patient), ENT_QUOTES, 'UTF-8');
        $number = htmlspecialchars((string) ($rx->prescription_number ?? ''), ENT_QUOTES, 'UTF-8');
        $issued = htmlspecialchars((string) ($rx->issued_at ?? ''), ENT_QUOTES, 'UTF-8');
        $validUntil = htmlspecialchars((string) ($rx->valid_until ?? ''), ENT_QUOTES, 'UTF-8');
        $diag = htmlspecialchars((string) ($rx->diagnosis_display ?? ''), ENT_QUOTES, 'UTF-8');
        $token = htmlspecialchars((string) ($rx->verification_token ?? ''), ENT_QUOTES, 'UTF-8');
        $hash = htmlspecialchars((string) ($rx->document_hash ?? ''), ENT_QUOTES, 'UTF-8');
        $verifyPayload = htmlspecialchars(PrescriptionDocumentSupport::buildVerificationPayload($rx), ENT_QUOTES, 'UTF-8');
        $verifyUrl = PrescriptionDocumentSupport::buildVerificationUrl($rx);
        $qrBlock = '';
        if ($verifyUrl !== null) {
            $qrContent = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
            $qrBlock = <<<HTML
<p>Escaneá para verificar vigencia:</p>
<barcode code="{$qrContent}" type="QR" class="barcode" size="1.1" error="M" disableborder="1" />
HTML;
        } elseif ($token !== '') {
            $qrBlock = '<p style="font-size:9px;">Configurá <code>recetaDigitalRepository.verificationPublicBaseUrl</code> para generar QR con URL de verificación.</p>';
        }

        $rows = '';
        foreach ($rx->items as $item) {
            $med = htmlspecialchars((string) ($item->medication_display ?? '—'), ENT_QUOTES, 'UTF-8');
            $dose = htmlspecialchars((string) ($item->dosage_text ?? ''), ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td>{$med}</td><td>{$dose}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="2">Sin medicación registrada.</td></tr>';
        }

        $cancelled = $rx->status === 'cancelled'
            ? '<p style="color:#a00;"><strong>RECETA ANULADA</strong></p>'
            : '';

        return <<<HTML
<h2>Receta electrónica</h2>
{$cancelled}
<p><strong>Número:</strong> {$number}</p>
<p><strong>Paciente:</strong> {$patientLabel}</p>
<p><strong>Emitida:</strong> {$issued} &nbsp; <strong>Válida hasta:</strong> {$validUntil}</p>
<p><strong>Diagnóstico:</strong> {$diag}</p>
<h3>Medicación prescrita</h3>
<table>
<thead><tr><th>Medicamento</th><th>Posología / cantidad</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<h3>Verificación</h3>
{$qrBlock}
<p class="verify">Código: {$token}</p>
<p style="font-size:9px;">Integridad (SHA-256): {$hash}</p>
<p style="font-size:9px;">Payload: {$verifyPayload}</p>
<p style="font-size:9px;">Documento emitido en Bioenlace (firma digital homologada pendiente de integración nacional).</p>
HTML;
    }
}
