<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\models\InfraestructuraCama;
use common\models\SegNivelInternacion;

/**
 * Bloqueo / aislamiento / liberación operativa de camas.
 */
final class InternacionCamaEstadoService
{
    /** @var list<string> */
    public const ESTADOS_STAFF = [
        InternacionMapaCamasService::ESTADO_LIBRE,
        InternacionMapaCamasService::ESTADO_BLOQUEADA,
        InternacionMapaCamasService::ESTADO_AISLAMIENTO,
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
        InternacionEfectorAccess::assertCamaEnEfector($cama, $idEfector);

        if ($this->tieneInternacionActiva($idCama)) {
            throw new \InvalidArgumentException(
                'No se puede cambiar el estado operativo: la cama tiene un paciente internado.'
            );
        }

        $cama->estado = $estadoMapa === InternacionMapaCamasService::ESTADO_LIBRE
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
        return SegNivelInternacion::find()
            ->where(['id_cama' => $idCama, 'fecha_fin' => null])
            ->exists();
    }
}
