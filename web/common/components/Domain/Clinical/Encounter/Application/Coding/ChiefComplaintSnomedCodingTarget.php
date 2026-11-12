<?php

namespace common\components\Domain\Clinical\Encounter\Application\Coding;

use common\components\Domain\Clinical\Encounter\Domain\ConditionDiagnosisRole;
use common\components\Domain\Terminology\Application\Service\SnomedIaCodingService;
use common\components\Domain\Terminology\Domain\Model\SnomedCodeSystem;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;

/**
 * Motivos de consulta (Condition con rol CC) → SNOMED.
 *
 * Los motivos se persisten sin código (code_system = UNCODED_SYSTEM); este
 * target completa `code`, `code_system` y `display`.
 */
final class ChiefComplaintSnomedCodingTarget implements EncounterCodingTargetInterface
{
    public const SNOMED_CATEGORY = 'motivos_consulta';

    public function key(): string
    {
        return 'chief_complaint';
    }

    public function code(Encounter $encounter, array $context = []): int
    {
        $rows = Condition::find()
            ->where([
                'encounter_id' => (int) $encounter->id,
                'diagnosis_role' => ConditionDiagnosisRole::CHIEF_COMPLAINT,
                'deleted_at' => null,
            ])
            ->andWhere(['or', ['code' => null], ['code' => '']])
            ->all();
        if ($rows === []) {
            return 0;
        }

        $coder = new SnomedIaCodingService();
        $saved = 0;
        foreach ($rows as $row) {
            $text = trim((string) ($row->display ?: $row->note));
            if ($text === '') {
                continue;
            }
            $match = $coder->buscarCodigoSnomed($text, self::SNOMED_CATEGORY);
            if (!is_array($match) || empty($match['conceptId'])) {
                continue;
            }
            $row->updateAttributes([
                'code' => (string) $match['conceptId'],
                'code_system' => SnomedCodeSystem::URI,
                'display' => (string) ($match['term'] ?? $row->display),
            ]);
            $saved++;
        }

        return $saved;
    }
}
