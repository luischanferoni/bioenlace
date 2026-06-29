<?php

namespace common\components\Domain\Person\Service;

use common\components\Domain\Integrations\Mpi\MpiDomicilioGatewayService;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\models\Person\Persona;
use common\models\Person\PersonaPacienteContexto;
use Yii;

/**
 * Verificación de domicilio vía MPI con reintentos (ventana 24 h).
 */
final class PacienteDomicilioVerificacionService
{
    public const VENTANA_SEGUNDOS = 86400;

    public const INTERVALO_REINTENTO_SEGUNDOS = 1800;

    public function __construct(
        private readonly MpiDomicilioGatewayService $domicilioGateway = new MpiDomicilioGatewayService(),
        private readonly RenaperDomicilioPersisterService $domicilioPersister = new RenaperDomicilioPersisterService(),
        private readonly PacienteContextoService $contextoService = new PacienteContextoService(),
    ) {
    }

    public function iniciarTrasRegistro(Persona $persona): void
    {
        $ctx = $this->contextoService->inicializarTrasRegistro($persona);
        $this->intentarVerificacion($persona, $ctx);
    }

    public function procesarPendientes(int $limit = 50): int
    {
        $q = PersonaPacienteContexto::find()
            ->where(['domicilio_estado' => PersonaPacienteContexto::DOMICILIO_PENDIENTE])
            ->orderBy(['domicilio_ultimo_intento' => SORT_ASC])
            ->limit($limit);

        $n = 0;
        foreach ($q->each() as $ctx) {
            /** @var PersonaPacienteContexto $ctx */
            if ($this->ventanaExpirada($ctx)) {
                $this->contextoService->marcarRequiereProvinciaManual($ctx);
                continue;
            }
            if (!$this->debeReintentar($ctx)) {
                continue;
            }
            $persona = Persona::findOne((int) $ctx->id_persona);
            if ($persona === null) {
                continue;
            }
            if ($this->intentarVerificacion($persona, $ctx)) {
                $n++;
            }
        }

        return $n;
    }

    private function intentarVerificacion(Persona $persona, PersonaPacienteContexto $ctx): bool
    {
        if ($ctx->domicilio_estado !== PersonaPacienteContexto::DOMICILIO_PENDIENTE) {
            return false;
        }
        if ($this->ventanaExpirada($ctx)) {
            $this->contextoService->marcarRequiereProvinciaManual($ctx);

            return false;
        }

        $this->contextoService->registrarIntento($ctx);

        $domicilio = $this->domicilioGateway->fetchByPersona($persona);
        if ($domicilio === null) {
            return false;
        }

        $provincia = $this->domicilioPersister->resolveProvincia($domicilio);
        if ($provincia === null) {
            return false;
        }

        try {
            $result = $this->domicilioPersister->persistFromRenaper($persona, $domicilio);
        } catch (\Throwable $e) {
            Yii::warning('Verificación domicilio falló al persistir: ' . $e->getMessage(), 'paciente_contexto');

            return false;
        }

        $idProvincia = (int) ($result['id_provincia'] ?? $provincia->id_provincia);
        if ($idProvincia <= 0) {
            return false;
        }

        $ctx->refresh();
        $this->contextoService->marcarDomicilioVerificado($ctx, $idProvincia);
        $this->notificarVerificado((int) $persona->id_persona);

        return true;
    }

    private function ventanaExpirada(PersonaPacienteContexto $ctx): bool
    {
        $inicio = strtotime((string) $ctx->domicilio_verificacion_inicio);

        return $inicio > 0 && (time() - $inicio) >= self::VENTANA_SEGUNDOS;
    }

    private function debeReintentar(PersonaPacienteContexto $ctx): bool
    {
        if ($ctx->domicilio_ultimo_intento === null || $ctx->domicilio_ultimo_intento === '') {
            return true;
        }
        $ultimo = strtotime((string) $ctx->domicilio_ultimo_intento);

        return $ultimo <= 0 || (time() - $ultimo) >= self::INTERVALO_REINTENTO_SEGUNDOS;
    }

    private function notificarVerificado(int $idPersona): void
    {
        try {
            (new PushNotificationSender())->sendToPersona(
                $idPersona,
                ['type' => 'DOMICILIO_VERIFICADO'],
                'Domicilio verificado',
                'Tu domicilio fue verificado. Ya podés usar todos los servicios de la app.'
            );
        } catch (\Throwable $e) {
            Yii::warning('Push domicilio verificado: ' . $e->getMessage(), 'paciente_contexto');
        }
    }
}
