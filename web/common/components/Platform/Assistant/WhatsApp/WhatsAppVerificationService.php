<?php

namespace common\components\Platform\Assistant\WhatsApp;

use common\components\Domain\Person\FrontDesk\Infrastructure\External\Identity\Connector\DiditClient;
use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\components\Domain\Person\Identity\Application\Service\IdentityVerificationException;
use common\models\Integrations\AsistenteWhatsappVerificacion;
use common\models\Integrations\AsistenteWhatsappVinculo;
use common\models\Person\Persona;
use Yii;

/**
 * Verificación de identidad del canal WhatsApp.
 *
 * Emite tokens de vinculación y completa la verificación con Didit. No conoce
 * intents ni reglas de dominio: solo resuelve identidad y deja el vínculo listo.
 */
final class WhatsAppVerificationService
{
    public const LINK_TTL_SECONDS = 600;
    public const STEP_UP_TTL_SECONDS = 600;

    public const RESULT_LINKED = 'linked';
    public const RESULT_NO_ACCOUNT = 'no_account';
    public const RESULT_REJECTED = 'rejected';
    public const RESULT_RETRY = 'retry';
    public const RESULT_ERROR = 'error';

    private DiditClient $didit;

    public function __construct(?DiditClient $didit = null)
    {
        $this->didit = $didit ?? new DiditClient();
    }

    /**
     * Emite un enlace nuevo y devuelve la URL que se envía por WhatsApp.
     */
    public function issueLink(AsistenteWhatsappVinculo $vinculo): string
    {
        $token = $vinculo->issueLinkToken(self::LINK_TTL_SECONDS);
        $vinculo->touchActivity();
        $vinculo->save(false);

        return $this->buildLinkUrl($token);
    }

    public function buildLinkUrl(string $token): string
    {
        $base = rtrim(WhatsAppConfig::get()['appDeepLinkBase'], '/');

        return $base . '/whatsapp-verificacion/' . rawurlencode($token);
    }

    public function findVinculoByToken(string $token): ?AsistenteWhatsappVinculo
    {
        return AsistenteWhatsappVinculo::findByLinkToken($token);
    }

    /**
     * Crea una sesión hosted de Didit para vincular el número.
     *
     * @return array{url: string, session_id: string}
     */
    public function startDiditLink(AsistenteWhatsappVinculo $vinculo, string $callbackUrl): array
    {
        $session = $this->didit->createVerificationSession([
            'callback' => $callbackUrl,
            'vendor_data' => 'wa-link-' . (int) $vinculo->id,
            'language' => 'es',
        ]);

        if (empty($session['success'])) {
            throw new \RuntimeException(
                trim((string) ($session['message'] ?? 'No se pudo crear la sesión de verificación.'))
            );
        }

        $sessionId = trim((string) ($session['session_id'] ?? ''));
        $url = trim((string) ($session['url'] ?? ''));
        if ($sessionId === '' || $url === '') {
            throw new \RuntimeException('Didit devolvió una sesión incompleta.');
        }

        AsistenteWhatsappVerificacion::updateAll(
            ['status' => AsistenteWhatsappVerificacion::STATUS_EXPIRED],
            ['wa_id' => $vinculo->wa_id, 'status' => AsistenteWhatsappVerificacion::STATUS_PENDING]
        );

        $row = new AsistenteWhatsappVerificacion([
            'wa_id' => $vinculo->wa_id,
            'user_id' => (int) $vinculo->user_id > 0 ? (int) $vinculo->user_id : null,
            'id_persona' => (int) $vinculo->id_persona > 0 ? (int) $vinculo->id_persona : null,
            'purpose' => AsistenteWhatsappVerificacion::PURPOSE_LINK,
            'method' => AsistenteWhatsappVerificacion::METHOD_DIDIT,
            'status' => AsistenteWhatsappVerificacion::STATUS_PENDING,
            'didit_session_id' => $sessionId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::LINK_TTL_SECONDS),
        ]);
        $row->save(false);

        return ['url' => $url, 'session_id' => $sessionId];
    }

    /**
     * Consulta la decisión de Didit y vincula el número a la Persona existente.
     *
     * @return array{result: string, message_key: string}
     */
    public function completeDiditLink(AsistenteWhatsappVinculo $vinculo, string $sessionId): array
    {
        $row = AsistenteWhatsappVerificacion::findByDiditSession($sessionId);
        if ($row === null
            || $row->wa_id !== $vinculo->wa_id
            || $row->purpose !== AsistenteWhatsappVerificacion::PURPOSE_LINK
            || $row->status !== AsistenteWhatsappVerificacion::STATUS_PENDING
        ) {
            return ['result' => self::RESULT_ERROR, 'message_key' => 'whatsapp_link_invalid'];
        }

        try {
            $result = $this->didit->getIdentityVerification($sessionId);
            DiditIdentityService::assertApprovedVerification($result);
        } catch (IdentityVerificationException $e) {
            $this->finishVerification($row, AsistenteWhatsappVerificacion::STATUS_REJECTED);
            $vinculo->registerFailure();
            $vinculo->save(false);

            return [
                'result' => $e->isRetryable() ? self::RESULT_RETRY : self::RESULT_REJECTED,
                'message_key' => $e->isRetryable() ? 'whatsapp_link_retry' : 'whatsapp_link_failed',
            ];
        } catch (\Throwable $e) {
            Yii::error('WhatsApp link Didit: ' . $e->getMessage(), WhatsAppConfig::LOG_CATEGORY);

            return ['result' => self::RESULT_ERROR, 'message_key' => 'whatsapp_link_retry'];
        }

        $document = preg_replace('/\D+/', '', (string) ($result['documento'] ?? '')) ?? '';
        if ($document === '') {
            $this->finishVerification($row, AsistenteWhatsappVerificacion::STATUS_REJECTED);

            return ['result' => self::RESULT_REJECTED, 'message_key' => 'whatsapp_link_failed'];
        }

        /** @var list<Persona> $personas */
        $personas = Persona::find()->where(['documento' => $document])->limit(2)->all();
        if (count($personas) !== 1 || (int) ($personas[0]->id_user ?? 0) <= 0) {
            $this->finishVerification($row, AsistenteWhatsappVerificacion::STATUS_REJECTED, $document);
            $this->rejectLink($vinculo);

            return ['result' => self::RESULT_NO_ACCOUNT, 'message_key' => 'whatsapp_link_no_account'];
        }

        $persona = $personas[0];

        $vinculo->markLinkVerified(
            (int) $persona->id_user,
            (int) $persona->id_persona,
            AsistenteWhatsappVinculo::VERIFICATION_METHOD_DIDIT
        );
        $vinculo->save(false);

        $row->status = AsistenteWhatsappVerificacion::STATUS_APPROVED;
        $row->id_persona = (int) $persona->id_persona;
        $row->user_id = (int) $persona->id_user;
        $row->didit_verification_id = (string) ($result['verification_id'] ?? $sessionId);
        $row->didit_reference_id = $result['didit_reference_id'] ?? null;
        $row->linked_document = $document;
        $row->consumed_at = date('Y-m-d H:i:s');
        $row->save(false);

        return ['result' => self::RESULT_LINKED, 'message_key' => 'whatsapp_link_ready'];
    }

    private function rejectLink(AsistenteWhatsappVinculo $vinculo): void
    {
        $vinculo->estado = AsistenteWhatsappVinculo::ESTADO_RECHAZADO;
        $vinculo->last_failed_at = date('Y-m-d H:i:s');
        $vinculo->link_token_hash = null;
        $vinculo->link_token_expires_at = null;
        $vinculo->save(false);
    }

    private function finishVerification(
        AsistenteWhatsappVerificacion $row,
        string $status,
        ?string $document = null
    ): void {
        $row->status = $status;
        $row->linked_document = $document;
        $row->consumed_at = date('Y-m-d H:i:s');
        $row->save(false);
    }
}
