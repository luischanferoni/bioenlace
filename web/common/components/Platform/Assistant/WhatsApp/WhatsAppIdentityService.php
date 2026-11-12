<?php

namespace common\components\Platform\Assistant\WhatsApp;

use common\components\Platform\Assistant\Copy\AssistantChannelCopy;
use common\components\Platform\Assistant\UiActions\AllowedRoutesResolver;
use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\models\Integrations\AsistenteWhatsappVinculo;
use common\models\Person\Persona;
use common\models\Platform\User;
use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Platform\BioenlaceDbManager;
use Yii;

/**
 * Vincula wa_id ↔ User/Persona con verificación fuerte (Didit/passkey) e hidrata sesión Yii.
 *
 * El teléfono por sí solo no vincula: la coincidencia en `PersonaTelefono` dejó de ser
 * prueba de identidad. La verificación la resuelve {@see WhatsAppVerificationService}.
 */
final class WhatsAppIdentityService
{
    public const APP_CLIENT_ID = WhatsAppConfig::APP_CLIENT_ID;

    /**
     * @return array{status: string, vinculo?: AsistenteWhatsappVinculo, message?: string}
     */
    public function resolveOrBootstrap(string $waId): array
    {
        $waId = trim($waId);
        if ($waId === '') {
            return [
                'status' => 'unmatched',
                'message' => AssistantChannelCopy::t(
                    'whatsapp_link_invalid',
                    [],
                    WhatsAppConfig::APP_CLIENT_ID
                ),
            ];
        }

        $vinculo = AsistenteWhatsappVinculo::findByWaId($waId);
        if ($vinculo === null) {
            $vinculo = new AsistenteWhatsappVinculo([
                'wa_id' => $waId,
                'estado' => AsistenteWhatsappVinculo::ESTADO_PENDIENTE_VERIFICACION,
            ]);
            $vinculo->save(false);
        }

        if ($vinculo->estado === AsistenteWhatsappVinculo::ESTADO_BLOQUEADO) {
            return [
                'status' => 'blocked',
                'vinculo' => $vinculo,
                'message' => AssistantChannelCopy::t(
                    'whatsapp_blocked',
                    [],
                    WhatsAppConfig::APP_CLIENT_ID
                ),
            ];
        }

        if ($vinculo->isLinked()) {
            $vinculo->touchActivity();
            $vinculo->save(false);

            return ['status' => 'linked', 'vinculo' => $vinculo];
        }

        if ($vinculo->estado === AsistenteWhatsappVinculo::ESTADO_RECHAZADO) {
            $vinculo->estado = AsistenteWhatsappVinculo::ESTADO_PENDIENTE_VERIFICACION;
        }

        $url = (new WhatsAppVerificationService())->issueLink($vinculo);

        return [
            'status' => 'verification_required',
            'vinculo' => $vinculo,
            'message' => AssistantChannelCopy::t(
                'whatsapp_link_required',
                ['url' => $url],
                WhatsAppConfig::APP_CLIENT_ID
            ),
        ];
    }

    /**
     * Establece identidad Yii + sesión base (idPersona) como en auth JWT.
     */
    public function establishYiiIdentity(int $userId, int $idPersona): bool
    {
        $userModel = User::findOne($userId);
        if (!$userModel || (int) $userModel->status !== User::STATUS_ACTIVE) {
            return false;
        }

        $persona = Persona::findOne($idPersona);
        if (!$persona || (int) $persona->id_user !== (int) $userModel->id) {
            return false;
        }

        BioenlaceDbManager::asignarRolPacienteSiNoExiste($userId);

        $session = Yii::$app->session;
        if (!$session->isActive) {
            $session->open();
        }
        $session->set('idPersona', (int) $persona->id_persona);
        $session->set('apellidoUsuario', $persona->apellido);
        $session->set('nombreUsuario', $persona->nombre);
        $session->set('efectores', ProfesionalEfectorServicio::getEfectoresParaSesion((int) $persona->id_persona));
        $session->set('__efectores_persona_id', (int) $persona->id_persona);

        Yii::$app->user->setIdentity($userModel);
        BioenlaceAccessChecker::ensureUpToDate();
        AllowedRoutesResolver::markSessionRoutesOwner((int) $userModel->id);

        return true;
    }
}
