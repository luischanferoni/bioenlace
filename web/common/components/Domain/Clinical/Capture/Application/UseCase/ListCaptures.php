<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;
use common\models\Clinical\EncounterCapture;

/** Caso de uso: listar capturas abiertas. */
final class ListCaptures
{
    private CaptureDraftService $draft;

    public function __construct(?CaptureDraftService $draft = null)
    {
        $this->draft = $draft ?? new CaptureDraftService();
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function execute(array $query): array
    {

        $subjectPersonaId = (int) ($query['id_persona'] ?? $query['subject_persona_id'] ?? 0);
        if ($subjectPersonaId <= 0) {
            return $this->draft->fail(400, 'Se requiere id_persona.');
        }

        $q = EncounterCapture::find()
            ->where(['subject_persona_id' => $subjectPersonaId])
            ->andWhere(['stage' => EncounterCapture::openStageValues()])
            ->orderBy(['updated_at' => SORT_DESC]);

        $parent = isset($query['parent']) ? trim((string) $query['parent']) : '';
        if ($parent !== '') {
            $q->andWhere(['parent_type' => $parent]);
        }
        if (isset($query['parent_id']) && $query['parent_id'] !== '' && $query['parent_id'] !== null) {
            $q->andWhere(['parent_id' => (int) $query['parent_id']]);
        }

        $items = [];
        foreach ($q->limit(50)->all() as $row) {
            /** @var EncounterCapture $row */
            // Listado liviano: el análisis completo va en captura/ver.
            $items[] = $this->draft->toApiArray($row, false);
        }

        return [
            'success' => true,
            'message' => 'OK',
            'items' => $items,
        ];
    }
}
