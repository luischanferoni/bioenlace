<?php

namespace common\components\Clinical\PatientSummary;

use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterPatientSummary;
use Yii;
use yii\db\Query;

/**
 * Lectura de resúmenes publicados para el paciente autenticado.
 */
final class PatientEncounterSummaryQueryService
{
    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listForPersona(int $idPersona, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);

        $base = (new Query())
            ->from(['s' => EncounterPatientSummary::tableName()])
            ->innerJoin(['e' => Encounter::tableName()], 'e.id = s.encounter_id')
            ->where([
                's.subject_persona_id' => $idPersona,
                'e.deleted_at' => null,
            ]);

        $total = (int) (clone $base)->count('*', Yii::$app->db);

        $rows = $base
            ->select(['s.*'])
            ->orderBy(['s.published_at' => SORT_DESC])
            ->limit($limit)
            ->offset($offset)
            ->all();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->listItemFromRow($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetailForPersona(int $idPersona, int $encounterId): ?array
    {
        $summary = EncounterPatientSummary::findOne([
            'encounter_id' => $encounterId,
            'subject_persona_id' => $idPersona,
        ]);
        if ($summary === null) {
            return null;
        }

        if ($summary->summary_json !== null && $summary->summary_json !== '') {
            $decoded = json_decode($summary->summary_json, true);
            if (is_array($decoded)) {
                $decoded['publishedAt'] = $summary->published_at;
                $decoded['version'] = (int) $summary->version;

                return $decoded;
            }
        }

        return [
            'encounterId' => (int) $summary->encounter_id,
            'narrativeText' => (string) ($summary->narrative_text ?? ''),
            'publishedAt' => $summary->published_at,
            'version' => (int) $summary->version,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestForPersona(int $idPersona): ?array
    {
        $summary = EncounterPatientSummary::find()
            ->where(['subject_persona_id' => $idPersona])
            ->orderBy(['published_at' => SORT_DESC])
            ->one();

        if ($summary === null) {
            return null;
        }

        return $this->getDetailForPersona($idPersona, (int) $summary->encounter_id);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function listItemFromRow(array $row): array
    {
        $teaser = '';
        $efectorNombre = null;
        $profesional = null;
        if (!empty($row['summary_json'])) {
            $decoded = json_decode((string) $row['summary_json'], true);
            if (is_array($decoded)) {
                $teaser = mb_substr(trim((string) ($decoded['narrativeText'] ?? '')), 0, 120);
                if (isset($decoded['efector']['nombre'])) {
                    $efectorNombre = (string) $decoded['efector']['nombre'];
                } else {
                    $efectorNombre = null;
                }
                if (isset($decoded['profesional']['display'])) {
                    $profesional = (string) $decoded['profesional']['display'];
                } else {
                    $profesional = null;
                }
            }
        }

        return [
            'encounterId' => (int) $row['encounter_id'],
            'publishedAt' => $row['published_at'],
            'teaser' => $teaser,
            'efectorNombre' => $efectorNombre ?? null,
            'profesionalDisplay' => $profesional ?? null,
        ];
    }
}
