<?php

namespace common\components\Domain\Organization\Efector\Application\UseCase;

use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\models\Organization\BillingSignupRequest;
use Yii;

/**
 * Completa la identidad de una solicitud de ministerio asistida.
 *
 * La persona abre el link con token, hace el KYC con Didit y este caso de uso:
 *  - resuelve/actualiza su Persona desde Didit;
 *  - garantiza un User passwordless vinculado;
 *  - vincula la solicitud pendiente al usuario.
 *
 * La aprobación y asignación del rol AdminMinisterio siguen a cargo del admin
 * (ver RequestMinistrySignup::approve).
 */
final class CompleteMinistrySignupIdentity
{
    /**
     * @return array{id_request: int, id_persona: int, id_user: int}
     */
    public function complete(int $requestId, string $verificationId): array
    {
        $req = BillingSignupRequest::findOne(['id' => $requestId, 'deleted_at' => null]);
        if ($req === null || $req->tipo !== BillingSignupRequest::TIPO_MINISTERIO) {
            throw new \InvalidArgumentException('Solicitud de ministerio inexistente.');
        }
        if ($req->status !== BillingSignupRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('La solicitud ya fue resuelta.');
        }

        $verificationId = trim($verificationId);
        if ($verificationId === '') {
            throw new \InvalidArgumentException('verification_id (Didit) es requerido.');
        }

        $email = trim((string) $req->contacto_email);
        $resolved = (new DiditIdentityService())->resolveAndUpsertPersona(
            $verificationId,
            null,
            ['email' => $email]
        );
        $persona = $resolved['persona'];

        [$user] = (new CreateIdentityAccount())->ensureUserForPersona($persona, $email);

        $req->id_user = (int) $user->id;
        if (!$req->save(false)) {
            throw new \RuntimeException('No se pudo vincular la solicitud al usuario: ' . json_encode($req->getErrors()));
        }

        return [
            'id_request' => (int) $req->id,
            'id_persona' => (int) $persona->id_persona,
            'id_user' => (int) $user->id,
        ];
    }
}
