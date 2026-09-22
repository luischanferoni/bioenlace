<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Service;

use common\models\Organization\InfraestructuraCama;
use common\models\Person\Persona;
use common\models\Clinical\InpatientStay;
use common\models\Clinical\InpatientBedStay;
use common\models\Clinical\InpatientStayRepository;

/**
 * Cambio de cama durante un episodio de internación activo (staff).
 */
final class InpatientBedTransferService
{
    /**
     * @return array<string, mixed>
     */
    public function contextoCambioCama(InpatientStay $internacion, int $idEfector): array
    {
        if (!$internacion->enableCambioCama()) {
            throw new \InvalidArgumentException('La internación no admite cambio de cama (egreso registrado).');
        }
        InpatientEfectorAccess::assertStayInEfector($internacion, $idEfector);

        $paciente = $internacion->paciente;
        $nombre = $paciente && method_exists($paciente, 'getNombreCompleto')
            ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        $camaActual = InpatientBedStay::getCamaActualLabel((int) $internacion->id_cama);
        $camas = InpatientBedStay::getCamasDisponiblesForSelect($idEfector);

        return [
            'internacion_id' => (int) $internacion->id,
            'paciente_nombre' => $nombre,
            'cama_actual_label' => (string) ($camaActual['label'] ?? ''),
            'id_cama_actual' => (int) $internacion->id_cama,
            'camas_disponibles' => array_map(static fn (array $row): array => [
                'value' => (string) ($row['code'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
            ], $camas),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function registrarCambioCama(int $internacionId, int $idEfector, array $post): array
    {
        $internacion = InpatientStay::findOne($internacionId);
        if ($internacion === null) {
            throw new \InvalidArgumentException('Internación no encontrada.');
        }
        if (!$internacion->enableCambioCama()) {
            throw new \InvalidArgumentException('La internación no admite cambio de cama.');
        }
        InpatientEfectorAccess::assertStayInEfector($internacion, $idEfector);

        $idCamaNueva = (int) ($post['id_cama'] ?? 0);
        $motivo = trim((string) ($post['motivo'] ?? ''));
        if ($idCamaNueva <= 0) {
            throw new \InvalidArgumentException('Seleccione la cama destino.');
        }
        if ($motivo === '') {
            throw new \InvalidArgumentException('Indique el motivo del cambio de cama.');
        }
        if ($idCamaNueva === (int) $internacion->id_cama) {
            throw new \InvalidArgumentException('La cama destino debe ser distinta de la actual.');
        }

        $cama = InfraestructuraCama::findOne($idCamaNueva);
        if ($cama === null || (string) $cama->estado !== 'desocupada') {
            throw new \InvalidArgumentException('La cama seleccionada no está disponible.');
        }
        InpatientEfectorAccess::assertCamaEnEfector($cama, $idEfector);

        $hcama = new InpatientBedStay();
        $hcama->id_internacion = (int) $internacion->id;
        $hcama->id_cama = $idCamaNueva;
        $hcama->motivo = $motivo;

        if (!$hcama->validate()) {
            $first = reset($hcama->firstErrors);
            throw new \InvalidArgumentException($first !== false ? (string) $first : 'Datos de cambio de cama inválidos.');
        }

        InpatientStayRepository::doCambioCama($internacion, $hcama);

        $nuevaLabel = InpatientBedStay::getCamaActualLabel($idCamaNueva);

        return [
            'internacion_id' => (int) $internacion->id,
            'id_cama' => $idCamaNueva,
            'cama_label' => (string) ($nuevaLabel['label'] ?? ''),
            'message' => 'Cambio de cama efectuado con éxito.',
        ];
    }
}
