<?php

namespace common\components\Clinical\CareCohort\Service;

use common\components\Clinical\CareCohort\CohortKeyBuilder;
use common\components\Clinical\CareCohort\Enum\CarePackType;
use common\models\Clinical\Encounter;

final class CarePackPromptBuilder
{
    private CohortKeyBuilder $cohortBuilder;

    public function __construct(?CohortKeyBuilder $cohortBuilder = null)
    {
        $this->cohortBuilder = $cohortBuilder ?? new CohortKeyBuilder();
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function build(
        string $packType,
        array $profile,
        int $subjectPersonaId,
        ?Encounter $encounter = null
    ): string {
        $contextBlock = $this->cohortBuilder->patientContextBlock($subjectPersonaId);
        $profileJson = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $encounterHint = '';
        if ($encounter !== null) {
            $encounterHint = "\nEncounter id: " . (int) $encounter->id
                . "\nMotivo/resumen: " . trim((string) $encounter->reason_text);
        }

        $schema = $this->schemaHint($packType);

        return <<<PROMPT
Sos un asistente clínico de Bioenlace. Generá un pack reutilizable para pacientes con el mismo perfil de cohorte (no personalices nombres ni fechas).

Perfil de cohorte (JSON):
{$profileJson}
{$encounterHint}

{$contextBlock}

{$schema}

Respondé ÚNICAMENTE con JSON válido, sin markdown ni texto adicional.
PROMPT;
    }

    private function schemaHint(string $packType): string
    {
        switch ($packType) {
            case CarePackType::ASSISTANCE_QUESTIONS:
                return 'Esquema: {"version":1,"questions":[{"id":"q1","text":"...","answer_type":"choice|text|scale","options":["..."],"required":true}],"notes_for_staff":"..."} — entre 5 y 10 preguntas clínicamente relevantes para esta cohorte.';
            case CarePackType::FOLLOWUP_PROGRAM:
                return 'Esquema: {"version":1,"touchpoints":[{"delay_days":3,"title":"...","purpose":"evolution|education","form_kind":"evolution_short|adherence|symptoms","education_refs":["m1"]}],"alert_rules":[{"signal":"worsening","action":"notify_staff"}]} — 2 a 4 touchpoints.';
            case CarePackType::EDUCATION_BUNDLE:
                return 'Esquema: {"version":1,"modules":[{"id":"m1","title":"...","summary":"...","bullet_points":["..."],"when_to_seek_care":"..."}]} — 2 a 5 módulos breves en español claro.';
            default:
                throw new \InvalidArgumentException('pack_type desconocido');
        }
    }
}
