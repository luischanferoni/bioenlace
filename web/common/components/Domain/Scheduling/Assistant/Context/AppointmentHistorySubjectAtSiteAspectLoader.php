<?php

namespace common\components\Domain\Scheduling\Assistant\Context;

use common\components\Platform\Assistant\Context\AssistantContextAspectLoaderInterface;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Context\AssistantContextLoadContext;
use common\models\Scheduling\Turno;
use Yii;

final class AppointmentHistorySubjectAtSiteAspectLoader implements AssistantContextAspectLoaderInterface
{
    public function aspectKey(): string
    {
        return AssistantContextHISAreaAspect::APPOINTMENT_HISTORY_SUBJECT_AT_SITE;
    }

    public function load(AssistantContextLoadContext $ctx): array
    {
        $idPersona = $ctx->anchors->subjectPersonaId;
        $siteId = $ctx->anchors->siteId;
        if ($idPersona <= 0) {
            return ['scope' => [], 'records' => []];
        }

        $limit = max(1, min(100, (int) (Yii::$app->params['asistente_context_history_limit'] ?? 20)));

        $query = Turno::findActive()
            ->where(['id_persona' => $idPersona]);
        if ($siteId > 0) {
            $query->andWhere(['id_efector' => $siteId]);
        }
        $rows = $query
            ->orderBy(['fecha' => SORT_DESC, 'hora' => SORT_DESC])
            ->limit($limit)
            ->all();

        $records = [];
        foreach ($rows as $turno) {
            if (!$turno instanceof Turno) {
                continue;
            }
            $records[] = [
                'scheduled_date' => (string) $turno->fecha,
                'scheduled_time' => substr(trim((string) $turno->hora), 0, 5),
                'status' => (string) $turno->estado,
                'status_reason' => $turno->estado_motivo !== null ? (string) $turno->estado_motivo : null,
            ];
        }

        return [
            'scope' => [
                'subject_persona_id' => $idPersona,
                'site_id' => $siteId > 0 ? $siteId : null,
                'limit' => $limit,
            ],
            'records' => $records,
        ];
    }
}
