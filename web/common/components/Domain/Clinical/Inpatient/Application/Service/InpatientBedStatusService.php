<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Service;

use common\models\Organization\InfraestructuraCama;
use common\models\Clinical\InpatientStay;

/**
 * Bloqueo / aislamiento / liberación operativa de camas.
 */
final class InpatientBedStatusService
{
    /** @var list<string> */
    public const ESTADOS_STAFF = [
        InpatientBedMapService::ESTADO_LIBRE,
        InpatientBedMapService::ESTADO_BLOQUEADA,
        InpatientBedMapService::ESTADO_AISLAMIENTO,
    ];

    /**
     * @return array<string, mixed>
     */
    public function marcar(int $idCama, int $idEfector, string $estadoMapa, ?string $motivo = null): array
    {
        $estadoMapa = strtolower(trim($estadoMapa));
        if (!in_array($estadoMapa, self::ESTADOS_STAFF, true)) {
            throw new \InvalidArgumentException(
                'Estado inválido. Use: libre, bloqueada o aislamiento.'
            );
        }

        $cama = InfraestructuraCama::findOne($idCama);
        if ($cama === null) {
            throw new \InvalidArgumentException('Cama no encontrada.');
        }
        InpatientEfectorAccess::assertCamaEnEfector($cama, $idEfector);

        if ($this->tieneInternacionActiva($idCama)) {
            throw new \InvalidArgumentException(
                'No se puede cambiar el estado operativo: la cama tiene un paciente internado.'
            );
        }

        $cama->estado = $estadoMapa === InpatientBedMapService::ESTADO_LIBRE
            ? 'desocupada'
            : $estadoMapa;
        if ($cama->hasAttribute('motivo_estado')) {
            $cama->motivo_estado = $motivo !== null && trim($motivo) !== ''
                ? trim($motivo)
                : null;
        }
        if (!$cama->save(false)) {
            throw new \RuntimeException('No se pudo actualizar el estado de la cama.');
        }

        return [
            'id_cama' => (int) $cama->id,
            'estado_mapa' => $estadoMapa,
            'estado_cama' => (string) $cama->estado,
            'motivo' => $motivo,
        ];
    }

    private function tieneInternacionActiva(int $idCama): bool
    {
        return InpatientStay::find()
            ->where(['id_cama' => $idCama, 'fecha_fin' => null])
            ->exists();
    }
}
