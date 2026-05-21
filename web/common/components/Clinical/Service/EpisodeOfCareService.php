<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\Enum\EpisodeOfCareStatus;
use common\models\Clinical\EpisodeOfCare;
use common\models\InfraestructuraCama;
use common\models\SegNivelInternacion;

final class EpisodeOfCareService
{
    public function findActiveForInternacion(int $internacionId): ?EpisodeOfCare
    {
        return EpisodeOfCare::find()
            ->andWhere(['internacion_id' => $internacionId])
            ->andWhere(['status' => EpisodeOfCareStatus::ACTIVE])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public function startInpatient(SegNivelInternacion $internacion): EpisodeOfCare
    {
        $existing = $this->findActiveForInternacion((int) $internacion->id);
        if ($existing !== null) {
            return $existing;
        }

        $episode = new EpisodeOfCare();
        $episode->subject_persona_id = (int) $internacion->id_persona;
        $episode->status = EpisodeOfCareStatus::ACTIVE;
        $episode->type_code = 'inpatient';
        $episode->internacion_id = (int) $internacion->id;
        $episode->efector_id = $this->resolveEfectorId($internacion);
        $episode->period_start = $this->internacionStartDatetime($internacion);
        $episode->title = 'Internación #' . $internacion->id;
        if (!$episode->save()) {
            throw new \RuntimeException('No se pudo crear episode_of_care: ' . json_encode($episode->getErrors()));
        }

        return $episode;
    }

    public function finish(EpisodeOfCare $episode, ?string $periodEnd = null): EpisodeOfCare
    {
        $episode->status = EpisodeOfCareStatus::FINISHED;
        $episode->period_end = $periodEnd ?? date('Y-m-d H:i:s');
        if (!$episode->save(false, ['status', 'period_end', 'updated_at', 'updated_by'])) {
            throw new \RuntimeException('No se pudo finalizar episode_of_care.');
        }

        return $episode;
    }

    private function resolveEfectorId(SegNivelInternacion $internacion): ?int
    {
        if (!$internacion->id_cama) {
            return null;
        }
        $cama = InfraestructuraCama::find()
            ->where(['id' => $internacion->id_cama])
            ->with('sala.piso')
            ->one();
        if ($cama === null || $cama->sala === null || $cama->sala->piso === null) {
            return null;
        }

        return (int) $cama->sala->piso->id_efector;
    }

    private function internacionStartDatetime(SegNivelInternacion $internacion): string
    {
        $fecha = (string) ($internacion->fecha_inicio ?? '');
        $hora = (string) ($internacion->hora_inicio ?? '00:00:00');
        if ($fecha === '') {
            return date('Y-m-d H:i:s');
        }
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        return $fecha . ' ' . $hora;
    }
}
