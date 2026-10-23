<?php

namespace common\components\Domain\Clinical\CareCohort\Application\Service;

use common\components\Domain\Clinical\CareCohort\Domain\CohortKeyBuilder;
use common\components\Domain\Clinical\CareCohort\Domain\CarePackType;
use common\components\Domain\Clinical\Encounter\Application\UseCase\RecordEncounterReason;
use common\components\Platform\Ai\Prompt\PromptRegistry;
use common\components\Platform\Ai\Prompt\YamlPrompt;
use common\models\Clinical\Encounter;

final class CarePackPromptService
{
    private const PROMPT_FILE = 'clinical.care_pack';

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
        $file = PromptRegistry::path(self::PROMPT_FILE);
        $contextBlock = $this->cohortBuilder->patientContextBlock($subjectPersonaId);
        $profileJson = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $encounterHint = '';
        if ($encounter !== null) {
            $motivo = (new RecordEncounterReason())->displayText($encounter);
            $encounterHint = "\n" . YamlPrompt::text($file, 'encounter_id_label') . ' ' . (int) $encounter->id
                . "\n" . YamlPrompt::text($file, 'encounter_reason_label') . ' ' . $motivo;
        }

        $deltaHint = '';
        if (!empty($profile['delta_context']) && is_array($profile['delta_context'])) {
            $deltaHint = "\n" . YamlPrompt::text($file, 'delta_header') . "\n"
                . json_encode($profile['delta_context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n" . YamlPrompt::text($file, 'delta_footer');
        }

        return YamlPrompt::render($file, [
            'profile_json' => (string) $profileJson,
            'encounter_hint' => $encounterHint,
            'delta_hint' => $deltaHint,
            'context_block' => (string) $contextBlock,
            'schema' => $this->schemaHint($packType),
        ]);
    }

    private function schemaHint(string $packType): string
    {
        if (!in_array($packType, [CarePackType::ASSISTANCE_QUESTIONS, CarePackType::FOLLOWUP_PROGRAM, CarePackType::EDUCATION_BUNDLE], true)) {
            throw new \InvalidArgumentException('pack_type desconocido');
        }

        return YamlPrompt::text(PromptRegistry::path(self::PROMPT_FILE), 'schema_' . $packType);
    }
}
