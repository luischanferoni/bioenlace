<?php

namespace common\components\Domain\Organization\Pes\Application\UseCase;

use common\components\Domain\Organization\Efector\Application\Service\EfectorEncounterEntitlementService;
use common\components\Domain\Organization\Efector\Application\Seed\ActiveRecordConsoleBlame;
use common\components\Domain\Person\Identity\Application\Service\RefepsProfessionalVerificationService;
use common\components\Domain\Person\Identity\Application\Service\RefepsVerificationException;
use common\components\Domain\Person\Identity\Application\Service\PersonCuilService;
use common\models\Person\Persona;
use common\models\Organization\ProfesionalEfectorServicio as ProfesionalEfectorServicioModel;
use common\models\Organization\Servicio;
use common\models\Organization\ServiciosEfector;
use Yii;

/**
 * Alta idempotente: persona + efector + servicio → {@see ProfesionalEfectorServicioModel} (canónico).
 *
 * Sin HttpException: errores de negocio como \InvalidArgumentException.
 */
final class EnsurePesAssignment
{
    /**
     * @param bool $trustedProvisioning Only internal seed/signup provisioning may bypass actor checks and REFEPS.
     * @return array{id_profesional_efector_servicio: int, id_servicio: int, servicio_acepta_turnos: string}
     */
    public static function ensurePersonaServicioEnEfector(
        int $idPersona,
        int $idEfector,
        int $idServicio,
        ?int $actingUserId = null,
        ?string $cuil = null,
        bool $trustedProvisioning = false
    ): array {
        if ($idPersona <= 0 || $idEfector <= 0 || $idServicio <= 0) {
            throw new \InvalidArgumentException('Datos inválidos para la asignación (persona, efector o servicio).');
        }

        $habilitado = ServiciosEfector::findActive()
            ->andWhere(['id_efector' => $idEfector, 'id_servicio' => $idServicio])
            ->one();
        if ($habilitado === null) {
            throw new \InvalidArgumentException('El servicio no está habilitado en este efector.');
        }

        /** @var Servicio|null $servicio */
        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            throw new \InvalidArgumentException('Servicio inexistente.');
        }
        $acepta = strtoupper(trim((string) $servicio->acepta_turnos));
        $isAdminEfectorService = strcasecmp(
            trim((string) ($servicio->item_name ?? '')),
            EnsureAdminEfectorAssignment::ITEM_NAME
        ) === 0;

        if (!$trustedProvisioning) {
            self::assertActingUserIsEfectorAdmin($idEfector, $actingUserId);
        }

        $existingPes = ProfesionalEfectorServicioModel::findActive()
            ->andWhere([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
            ])
            ->one();
        if (
            $existingPes === null
            && !$trustedProvisioning
            && !$isAdminEfectorService
            && self::serviceRequiresProfessionalVerification($servicio)
        ) {
            self::assertProfessionalRegistryVerification($idPersona);
        }

        if (!$isAdminEfectorService) {
            $persona = Persona::findOne(['id_persona' => $idPersona]);
            if ($persona === null) {
                throw new \InvalidArgumentException('Persona inexistente.');
            }
            if ($cuil !== null && trim($cuil) !== '') {
                PersonCuilService::ensureOnPersona($idPersona, $cuil);
            } elseif (!PersonCuilService::personaTieneCuil($persona)) {
                throw new \InvalidArgumentException(
                    'Se requiere el CUIL del profesional para registrar la asignación en el efector.'
                );
            }
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            /** @var ProfesionalEfectorServicioModel|null $pes */
            $pes = ProfesionalEfectorServicioModel::findActive()
                ->andWhere([
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'id_servicio' => $idServicio,
                ])
                ->one();
            $isNew = $pes === null;
            if ($isNew) {
                EfectorEncounterEntitlementService::assertCanAddPes($idEfector, $idPersona, $idServicio);
                $pes = new ProfesionalEfectorServicioModel();
                $pes->id_persona = $idPersona;
                $pes->id_efector = $idEfector;
                $pes->id_servicio = $idServicio;
            }
            if (!$pes->validate()) {
                $flat = [];
                foreach ($pes->getErrors() as $msgs) {
                    foreach ((array) $msgs as $m) {
                        $flat[] = (string) $m;
                    }
                }
                throw new \InvalidArgumentException(
                    $flat !== []
                        ? implode(' ', $flat)
                        : 'Asignación profesional–efector–servicio inválida.'
                );
            }
            if ($actingUserId !== null && $actingUserId > 0) {
                ActiveRecordConsoleBlame::save(
                    $pes,
                    $actingUserId,
                    'No se pudo registrar la asignación profesional–efector–servicio'
                );
            } elseif (!$pes->save(false)) {
                throw new \RuntimeException('No se pudo registrar la asignación profesional–efector–servicio: ' . json_encode($pes->getErrors()));
            }

            if ($isNew) {
                EfectorEncounterEntitlementService::syncPendingDowngradeForEfector($idEfector);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [
            'id_profesional_efector_servicio' => (int) $pes->id,
            'id_servicio' => $idServicio,
            'servicio_acepta_turnos' => $acepta,
        ];
    }

    private static function assertActingUserIsEfectorAdmin(int $idEfector, ?int $actingUserId): void
    {
        if (!Yii::$app->has('user', true) || Yii::$app->user->isGuest) {
            throw new \DomainException('Solo la administración del centro puede incorporar personas.');
        }

        $currentUserId = (int) Yii::$app->user->id;
        if ($currentUserId <= 0 || ($actingUserId !== null && $actingUserId !== $currentUserId)) {
            throw new \DomainException('No se pudo validar quién realiza la asignación.');
        }
        if (Yii::$app->user->isSuperadmin) {
            return;
        }

        $persona = Persona::findOne(['id_user' => $currentUserId]);
        $adminService = EnsureAdminEfectorAssignment::findSistemaServicio();
        if ($persona === null || $adminService === null) {
            throw new \DomainException('Solo la administración del centro puede incorporar personas.');
        }

        $hasAdminAssignment = ProfesionalEfectorServicioModel::findActive()
            ->andWhere([
                'id_persona' => (int) $persona->id_persona,
                'id_efector' => $idEfector,
                'id_servicio' => (int) $adminService->id_servicio,
            ])
            ->exists();
        if (!$hasAdminAssignment) {
            throw new \DomainException('Solo la administración de este centro puede incorporar personas.');
        }
    }

    private static function assertProfessionalRegistryVerification(int $idPersona): void
    {
        $persona = Persona::findOne(['id_persona' => $idPersona]);
        if ($persona === null) {
            throw new \InvalidArgumentException('Persona inexistente.');
        }

        $result = (new RefepsProfessionalVerificationService())->verify($persona);
        if (!$result->isConfirmed()) {
            throw new RefepsVerificationException($result->status, $result->message);
        }
    }

    private static function serviceRequiresProfessionalVerification(Servicio $servicio): bool
    {
        $nonClinicalRoles = [
            'AdminEfector',
            'AdminMinisterio',
            'nivelCentral',
            'Administrativo',
            'facturista',
        ];

        return !in_array(trim((string) ($servicio->item_name ?? '')), $nonClinicalRoles, true);
    }

}
