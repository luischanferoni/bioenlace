<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Ai\IAManager;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Sugerencias orientativas (diagnósticos / prácticas) a partir del resumen de motivos + contexto del paciente.
 */
final class AppointmentReasonClinicalInsightsService
{
    /**
     * @return array{
     *   diagnosticos_sugeridos: list<array{termino: string, codigo_snomed: string|null, justificacion: string}>,
     *   practicas_sugeridas: list<array{termino: string, tipo: string, justificacion: string}>,
     *   generado_at: string
     * }|null
     */
    public static function generateAndPersist(int $encounterId, string $motivosResumen): ?array
    {
        $encounter = Encounter::findOne(['id' => $encounterId]);
        if ($encounter === null || trim($motivosResumen) === '') {
            return null;
        }

        $existing = self::decodeInsights($encounter->motivos_ia_insights_json ?? null);
        if ($existing !== null) {
            return $existing;
        }

        $insights = self::inferWithIa($motivosResumen, (int) $encounter->subject_persona_id);
        if ($insights === null) {
            return null;
        }

        $encounter->motivos_ia_insights_json = json_encode($insights, JSON_UNESCAPED_UNICODE);
        $encounter->save(false, ['motivos_ia_insights_json']);

        return $insights;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodeInsights(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{
     *   diagnosticos_sugeridos: list<array<string, mixed>>,
     *   practicas_sugeridas: list<array<string, mixed>>,
     *   generado_at: string
     * }|null
     */
    private static function inferWithIa(string $motivosResumen, int $subjectPersonaId): ?array
    {
        $patientBlock = '';
        if ($subjectPersonaId > 0) {
            $patientBlock = (new PatientAiContextBuilder())->build(
                $subjectPersonaId,
                PatientAiContextBuilder::PROFILE_ENCOUNTER
            );
        }

        $prompt = <<<PROMPT
Sos un asistente clínico de apoyo al médico (no reemplazás criterio profesional).

Resumen de motivos de consulta del paciente:
---
{$motivosResumen}
---
PROMPT;

        if ($patientBlock !== '') {
            $prompt .= "\n\n" . $patientBlock;
        }

        $prompt .= <<<PROMPT


Con el resumen y el contexto del paciente, proponé orientación PRELIMINAR para la apertura de la consulta.
Responde ÚNICAMENTE con JSON válido (sin markdown):
{
  "diagnosticos_sugeridos": [
    {"termino": "...", "codigo_snomed": null, "justificacion": "breve"}
  ],
  "practicas_sugeridas": [
    {"termino": "...", "tipo": "estudio|procedimiento|control", "justificacion": "breve"}
  ]
}
Reglas: máximo 5 ítems por lista; hipótesis razonables, no afirmativas; sin recetas ni dosis.
PROMPT;

        $raw = IAManager::consultarIA($prompt, 'motivos-consulta-insights', 'analysis');

        return self::normalizeInsightsPayload($raw);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>|null
     */
    private static function normalizeInsightsPayload($raw): ?array
    {
        $data = $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($data)) {
            return null;
        }

        $dx = [];
        foreach ($data['diagnosticos_sugeridos'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $term = trim((string) ($row['termino'] ?? ''));
            if ($term === '') {
                continue;
            }
            $dx[] = [
                'termino' => $term,
                'codigo_snomed' => isset($row['codigo_snomed']) && $row['codigo_snomed'] !== ''
                    ? (string) $row['codigo_snomed']
                    : null,
                'justificacion' => trim((string) ($row['justificacion'] ?? '')),
            ];
            if (count($dx) >= 5) {
                break;
            }
        }

        $pr = [];
        foreach ($data['practicas_sugeridas'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $term = trim((string) ($row['termino'] ?? ''));
            if ($term === '') {
                continue;
            }
            $pr[] = [
                'termino' => $term,
                'tipo' => trim((string) ($row['tipo'] ?? 'estudio')) ?: 'estudio',
                'justificacion' => trim((string) ($row['justificacion'] ?? '')),
            ];
            if (count($pr) >= 5) {
                break;
            }
        }

        if ($dx === [] && $pr === []) {
            return null;
        }

        return [
            'diagnosticos_sugeridos' => $dx,
            'practicas_sugeridas' => $pr,
            'generado_at' => date('c'),
        ];
    }
}
