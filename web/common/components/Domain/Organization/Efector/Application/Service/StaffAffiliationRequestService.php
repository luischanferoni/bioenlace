<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

use common\components\Domain\Organization\Efector\Application\UseCase\SignUpInstitutionalEfector;
use common\models\Organization\StaffAffiliationRequest;

/**
 * Registra la intención de incorporación de una cuenta personal gratuita a un
 * centro activo. Es idempotente por persona + efector y no asigna roles.
 */
final class StaffAffiliationRequestService
{
    /**
     * @return array<string, mixed>
     */
    public function record(int $idPersona, ?int $idUser, int $idEfector): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('La cuenta no está asociada a una persona.');
        }

        $center = SignUpInstitutionalEfector::findAvailableCenter($idEfector);
        if ($center === null) {
            throw new \InvalidArgumentException('El centro seleccionado no está disponible.');
        }

        $request = StaffAffiliationRequest::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->one();
        if ($request === null) {
            $request = new StaffAffiliationRequest();
            $request->id_persona = $idPersona;
            $request->id_efector = $idEfector;
            $request->status = StaffAffiliationRequest::STATUS_PENDING;
        }

        $request->id_user = $idUser;
        $request->sector = (string) $center['sector'];
        $request->nombre_efector = (string) $center['nombre'];
        $request->codigo_sisa = $center['codigo_sisa'] ?? null;
        $request->localidad = $center['localidad'] ?? null;
        $request->departamento = $center['departamento'] ?? null;
        $request->provincia = $center['provincia'] ?? null;
        $request->id_provincia = $center['id_provincia'] ?? null;
        if (!$request->save()) {
            throw new \RuntimeException(
                'No se pudo guardar la solicitud de centro: '
                . json_encode($request->getErrors())
            );
        }

        return [
            'id' => (int) $request->id,
            'id_efector' => (int) $request->id_efector,
            'nombre_efector' => (string) $request->nombre_efector,
            'sector' => (string) $request->sector,
            'status' => (string) $request->status,
        ];
    }
}
