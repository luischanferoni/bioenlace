<?php

namespace common\components\Platform\Assistant\WhatsApp;

use common\components\Platform\Assistant\UiActions\AllowedRoutesResolver;
use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\models\AsistenteWhatsappVinculo;
use common\models\PersonaTelefono;
use common\models\Person\Persona;
use common\models\User;
use common\models\ProfesionalEfectorServicio;
use common\models\BioenlaceDbManager;
use Yii;

/**
 * Vincula wa_id ↔ User/Persona (match teléfono + confirmación SI) e hidrata sesión Yii.
 */
final class WhatsAppIdentityService
{
    public const APP_CLIENT_ID = WhatsAppConfig::APP_CLIENT_ID;

    /**
     * @return array{status: string, vinculo?: AsistenteWhatsappVinculo, message?: string, menu?: bool}
     */
    public function resolveOrBootstrap(string $waId, string $waPhone, string $inboundText): array
    {
        $waId = trim($waId);
        $vinculo = AsistenteWhatsappVinculo::findByWaId($waId);
        if ($vinculo === null) {
            $vinculo = new AsistenteWhatsappVinculo([
                'wa_id' => $waId,
                'estado' => AsistenteWhatsappVinculo::ESTADO_PENDIENTE_CONFIRMACION,
            ]);
            $vinculo->save(false);
        }

        if ($vinculo->estado === AsistenteWhatsappVinculo::ESTADO_ACTIVO
            && (int) $vinculo->user_id > 0
            && (int) $vinculo->id_persona > 0
        ) {
            return ['status' => 'linked', 'vinculo' => $vinculo];
        }

        $normalized = mb_strtolower(trim($inboundText));
        $isSi = in_array($normalized, ['si', 'sí', 'yes', 'ok', 'dale'], true);
        $isNo = in_array($normalized, ['no', 'nop', 'cancelar'], true);

        if ($vinculo->estado === AsistenteWhatsappVinculo::ESTADO_PENDIENTE_CONFIRMACION
            && (int) $vinculo->pending_user_id > 0
            && $isSi
        ) {
            $vinculo->user_id = (int) $vinculo->pending_user_id;
            $vinculo->id_persona = (int) $vinculo->pending_id_persona;
            $vinculo->pending_user_id = null;
            $vinculo->pending_id_persona = null;
            $vinculo->estado = AsistenteWhatsappVinculo::ESTADO_ACTIVO;
            $vinculo->save(false);

            return [
                'status' => 'just_linked',
                'vinculo' => $vinculo,
                'message' => 'Listo, tu WhatsApp quedó vinculado a Bioenlace.',
                'menu' => true,
            ];
        }

        if ($vinculo->estado === AsistenteWhatsappVinculo::ESTADO_PENDIENTE_CONFIRMACION
            && (int) $vinculo->pending_user_id > 0
            && $isNo
        ) {
            $vinculo->pending_user_id = null;
            $vinculo->pending_id_persona = null;
            $vinculo->estado = AsistenteWhatsappVinculo::ESTADO_RECHAZADO;
            $vinculo->save(false);

            return [
                'status' => 'rejected',
                'vinculo' => $vinculo,
                'message' => 'No vinculamos este número. Si fue un error, escribí de nuevo o cargá el teléfono correcto en la app Bioenlace.',
            ];
        }

        $match = $this->findUniquePatientByPhone($waPhone !== '' ? $waPhone : $waId);
        if ($match === null) {
            $vinculo->estado = AsistenteWhatsappVinculo::ESTADO_PENDIENTE_CONFIRMACION;
            $vinculo->pending_user_id = null;
            $vinculo->pending_id_persona = null;
            $vinculo->save(false);

            return [
                'status' => 'unmatched',
                'vinculo' => $vinculo,
                'message' => 'No encontramos tu cuenta con este número. Registrate en la app Bioenlace y cargá este teléfono en tu perfil; después escribí de nuevo acá.',
            ];
        }

        $vinculo->estado = AsistenteWhatsappVinculo::ESTADO_PENDIENTE_CONFIRMACION;
        $vinculo->pending_user_id = $match['user_id'];
        $vinculo->pending_id_persona = $match['id_persona'];
        $vinculo->user_id = null;
        $vinculo->id_persona = null;
        $vinculo->save(false);

        $nombre = $match['nombre'];

        return [
            'status' => 'confirm_required',
            'vinculo' => $vinculo,
            'message' => '¿Sos ' . $nombre . '? Respondé SI para vincular este WhatsApp a tu cuenta Bioenlace, o NO si no sos vos.',
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

    /**
     * @return array{user_id: int, id_persona: int, nombre: string}|null
     */
    private function findUniquePatientByPhone(string $waPhone): ?array
    {
        $digits = self::digitsOnly($waPhone);
        if (strlen($digits) < 8) {
            return null;
        }

        $suffix = substr($digits, -10);
        $likeNeedle = substr($suffix, -8);

        /** @var list<PersonaTelefono> $rows */
        $rows = PersonaTelefono::find()
            ->where(['like', 'numero', $likeNeedle])
            ->limit(80)
            ->all();

        $matches = [];
        foreach ($rows as $row) {
            $rowDigits = self::digitsOnly((string) $row->numero);
            if ($rowDigits === '' || !self::phonesMatch($digits, $rowDigits)) {
                continue;
            }
            $persona = Persona::findOne((int) $row->id_persona);
            if (!$persona || (int) ($persona->id_user ?? 0) <= 0) {
                continue;
            }
            $user = User::findOne((int) $persona->id_user);
            if (!$user || (int) $user->status !== User::STATUS_ACTIVE) {
                continue;
            }
            $key = (int) $persona->id_persona;
            $matches[$key] = [
                'user_id' => (int) $user->id,
                'id_persona' => (int) $persona->id_persona,
                'nombre' => trim((string) $persona->nombre . ' ' . (string) $persona->apellido),
            ];
        }

        if (count($matches) !== 1) {
            return null;
        }

        return reset($matches) ?: null;
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function phonesMatch(string $aDigits, string $bDigits): bool
    {
        if ($aDigits === '' || $bDigits === '') {
            return false;
        }
        if ($aDigits === $bDigits) {
            return true;
        }
        // Sufijo local (8) cubre 54 9 11… vs 011… / 11…
        $a8 = substr($aDigits, -8);
        $b8 = substr($bDigits, -8);
        if (strlen($a8) === 8 && strlen($b8) === 8 && $a8 === $b8) {
            return true;
        }
        $a10 = substr($aDigits, -10);
        $b10 = substr($bDigits, -10);

        return strlen($a10) >= 10 && strlen($b10) >= 10 && $a10 === $b10;
    }
}
