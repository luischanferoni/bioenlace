<?php

namespace common\components\Domain\Clinical\Emergency\Application\Service;

use common\models\Clinical\Emergency\EmergencyEpisode;
use common\models\Clinical\InpatientStay;
use yii\helpers\Url;

/**
 * Solicitud y trazabilidad guardia → internación (cama).
 */
final class EmergencyInpatientTransferService
{
    public function solicitarInternacion(int $guardiaId, int $idEfector, int $idEfectorInternacion): array
    {
        if ($idEfectorInternacion <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector para internación.');
        }

        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        $guardia->notificar_internacion_id_efector = $idEfectorInternacion;
        $guardia->updateAttributes(['notificar_internacion_id_efector' => $idEfectorInternacion]);

        return $this->serializePendiente($guardia);
    }

    public function internacionResuelta(int $guardiaId): bool
    {
        return InpatientStay::find()
            ->where(['id_guardia' => $guardiaId])
            ->andWhere(['fecha_fin' => null])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePendiente(EmergencyEpisode $guardia): array
    {
        $pendiente = $this->isPendienteInternacion($guardia);

        return [
            'internacion_pendiente' => $pendiente,
            'notificar_internacion_id_efector' => $guardia->notificar_internacion_id_efector
                ? (int) $guardia->notificar_internacion_id_efector
                : null,
            'internacion_ingreso_url' => $pendiente
                ? $this->buildInternacionIngresoUrl($guardia)
                : null,
            'id_internacion' => $this->findInternacionActivaId((int) $guardia->id),
        ];
    }

    public function isPendienteInternacion(EmergencyEpisode $guardia): bool
    {
        $efector = (int) ($guardia->notificar_internacion_id_efector ?? 0);
        if ($efector <= 0) {
            return false;
        }

        return !$this->internacionResuelta((int) $guardia->id);
    }

    public function marcarInternacionDesdeGuardia(int $guardiaId, int $idInternacion): void
    {
        $guardia = EmergencyEpisode::findOne($guardiaId);
        if ($guardia === null) {
            return;
        }
        $internacion = InpatientStay::findOne($idInternacion);
        if ($internacion === null) {
            return;
        }
        if (empty($internacion->id_guardia)) {
            $internacion->updateAttributes(['id_guardia' => $guardiaId]);
        }
        $guardia->updateAttributes(['notificar_internacion_id_efector' => null]);
    }

    private function findInternacionActivaId(int $guardiaId): ?int
    {
        $row = InpatientStay::find()
            ->select('id')
            ->where(['id_guardia' => $guardiaId])
            ->andWhere(['fecha_fin' => null])
            ->orderBy(['id' => SORT_DESC])
            ->scalar();

        return $row ? (int) $row : null;
    }

    private function buildInternacionIngresoUrl(EmergencyEpisode $guardia): string
    {
        return Url::to([
            '/internacion/ingreso',
            'id_guardia' => (int) $guardia->id,
        ]);
    }

    private function loadGuardia(int $guardiaId, int $idEfector): Guardia
    {
        $guardia = EmergencyEpisode::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        EmergencyEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);

        return $guardia;
    }
}
