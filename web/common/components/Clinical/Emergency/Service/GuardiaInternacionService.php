<?php

namespace common\components\Clinical\Emergency\Service;

use common\models\Guardia;
use common\models\SegNivelInternacion;
use yii\helpers\Url;

/**
 * Solicitud y trazabilidad guardia → internación (cama).
 */
final class GuardiaInternacionService
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
        return SegNivelInternacion::find()
            ->where(['id_guardia' => $guardiaId])
            ->andWhere(['fecha_fin' => null])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePendiente(Guardia $guardia): array
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

    public function isPendienteInternacion(Guardia $guardia): bool
    {
        $efector = (int) ($guardia->notificar_internacion_id_efector ?? 0);
        if ($efector <= 0) {
            return false;
        }

        return !$this->internacionResuelta((int) $guardia->id);
    }

    public function marcarInternacionDesdeGuardia(int $guardiaId, int $idInternacion): void
    {
        $guardia = Guardia::findOne($guardiaId);
        if ($guardia === null) {
            return;
        }
        $internacion = SegNivelInternacion::findOne($idInternacion);
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
        $row = SegNivelInternacion::find()
            ->select('id')
            ->where(['id_guardia' => $guardiaId])
            ->andWhere(['fecha_fin' => null])
            ->orderBy(['id' => SORT_DESC])
            ->scalar();

        return $row ? (int) $row : null;
    }

    private function buildInternacionIngresoUrl(Guardia $guardia): string
    {
        return Url::to([
            '/internacion/ingreso',
            'id_guardia' => (int) $guardia->id,
        ]);
    }

    private function loadGuardia(int $guardiaId, int $idEfector): Guardia
    {
        $guardia = Guardia::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        GuardiaEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);

        return $guardia;
    }
}
