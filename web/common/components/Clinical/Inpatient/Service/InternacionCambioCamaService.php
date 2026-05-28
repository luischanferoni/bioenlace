<?php

namespace common\components\Clinical\Inpatient\Service;

use common\models\InfraestructuraCama;
use common\models\Persona;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionHcama;
use common\models\SegNivelInternacionRepository;

/**
 * Cambio de cama durante un episodio de internación activo (staff).
 */
final class InternacionCambioCamaService
{
    /**
     * @return array<string, mixed>
     */
    public function contextoCambioCama(SegNivelInternacion $internacion, int $idEfector): array
    {
        if (!$internacion->enableCambioCama()) {
            throw new \InvalidArgumentException('La internación no admite cambio de cama (egreso registrado).');
        }
        InternacionEfectorAccess::assertInternacionEnEfector($internacion, $idEfector);

        $paciente = $internacion->paciente;
        $nombre = $paciente && method_exists($paciente, 'getNombreCompleto')
            ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        $camaActual = SegNivelInternacionHcama::getCamaActualLabel((int) $internacion->id_cama);
        $camas = SegNivelInternacionHcama::getCamasDisponiblesForSelect($idEfector);

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
        $internacion = SegNivelInternacion::findOne($internacionId);
        if ($internacion === null) {
            throw new \InvalidArgumentException('Internación no encontrada.');
        }
        if (!$internacion->enableCambioCama()) {
            throw new \InvalidArgumentException('La internación no admite cambio de cama.');
        }
        InternacionEfectorAccess::assertInternacionEnEfector($internacion, $idEfector);

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
        InternacionEfectorAccess::assertCamaEnEfector($cama, $idEfector);

        $hcama = new SegNivelInternacionHcama();
        $hcama->id_internacion = (int) $internacion->id;
        $hcama->id_cama = $idCamaNueva;
        $hcama->motivo = $motivo;

        if (!$hcama->validate()) {
            $first = reset($hcama->firstErrors);
            throw new \InvalidArgumentException($first !== false ? (string) $first : 'Datos de cambio de cama inválidos.');
        }

        SegNivelInternacionRepository::doCambioCama($internacion, $hcama);

        $nuevaLabel = SegNivelInternacionHcama::getCamaActualLabel($idCamaNueva);

        return [
            'internacion_id' => (int) $internacion->id,
            'id_cama' => $idCamaNueva,
            'cama_label' => (string) ($nuevaLabel['label'] ?? ''),
            'message' => 'Cambio de cama efectuado con éxito.',
        ];
    }
}
