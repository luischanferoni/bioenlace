<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Domain\Clinical\Emergency\Enum\CircuitoEventType;
use common\components\Domain\Clinical\Emergency\Enum\GuardiaEgresoDestino;
use common\models\Guardia;
use common\models\Person\Persona;
use Yii;

/**
 * Egreso / conducta de guardia: destino, diagnóstico operativo, epicrisis y checklist.
 */
final class GuardiaEgresoEstructuradoService
{
    /** @var GuardiaOperacionService */
    private $operacion;

    /** @var GuardiaInternacionService */
    private $internacion;

    /** @var GuardiaCircuitoService */
    private $circuito;

    public function __construct(
        ?GuardiaOperacionService $operacion = null,
        ?GuardiaInternacionService $internacion = null,
        ?GuardiaCircuitoService $circuito = null
    ) {
        $this->operacion = $operacion ?? new GuardiaOperacionService();
        $this->internacion = $internacion ?? new GuardiaInternacionService();
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
    }

    /**
     * @return array<string, mixed>
     */
    public function contexto(int $guardiaId, int $idEfector): array
    {
        $guardia = $this->loadActiva($guardiaId, $idEfector);
        $paciente = $guardia->paciente;
        $nombre = $paciente instanceof Persona
            ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        $pesId = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);

        return [
            'guardia_id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'paciente_nombre' => $nombre,
            'circuito_estado' => $this->circuito->effectiveEstado($guardia),
            'circuito_estado_label' => CircuitoEstado::label($this->circuito->effectiveEstado($guardia)),
            'destinos' => GuardiaEgresoDestino::options(),
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
            'egreso_formulario_path' => '/api/v1/clinical/emergency-guardia/'
                . (int) $guardia->id . '/egreso-formulario',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function registrar(int $guardiaId, int $idEfector, array $post): array
    {
        $guardia = $this->loadActiva($guardiaId, $idEfector);

        $destino = strtoupper(trim((string) ($post['destino_egreso'] ?? $post['destino'] ?? '')));
        if (!in_array($destino, GuardiaEgresoDestino::values(), true)) {
            throw new \InvalidArgumentException('Se requiere un destino de egreso válido.');
        }

        $diagnostico = trim((string) ($post['diagnostico_operativo'] ?? ''));
        if (mb_strlen($diagnostico) < 5) {
            throw new \InvalidArgumentException('El diagnóstico operativo debe tener al menos 5 caracteres.');
        }

        $epicrisis = trim((string) ($post['epicrisis'] ?? ''));
        if (mb_strlen($epicrisis) < 20) {
            throw new \InvalidArgumentException('La epicrisis de guardia debe tener al menos 20 caracteres.');
        }

        $pautas = trim((string) ($post['pautas_alarma'] ?? ''));
        if (GuardiaEgresoDestino::requiresPautasAlarma($destino) && mb_strlen($pautas) < 10) {
            throw new \InvalidArgumentException(
                'Para alta domiciliaria indicá pautas de alarma (mín. 10 caracteres).'
            );
        }

        foreach (['checklist_indicaciones', 'checklist_epicrisis'] as $chk) {
            if (!$this->isTruthy($post[$chk] ?? null)) {
                throw new \InvalidArgumentException(
                    'Confirmá el checklist de egreso (indicaciones y epicrisis revisada).'
                );
            }
        }

        $idEfectorDerivacion = (int) ($post['id_efector_derivacion'] ?? 0);
        if (GuardiaEgresoDestino::requiresEfectorDerivacion($destino)) {
            if ($idEfectorDerivacion <= 0) {
                throw new \InvalidArgumentException('Para derivación se requiere el efector destino.');
            }
            $guardia->id_efector_derivacion = $idEfectorDerivacion;
            $guardia->condiciones_derivacion = trim((string) ($post['condiciones_derivacion'] ?? '')) ?: null;
        }

        if (GuardiaEgresoDestino::requestsInternacion($destino)) {
            $idInternacionEfector = (int) ($post['notificar_internacion_id_efector'] ?? $idEfector);
            if ($idInternacionEfector <= 0) {
                $idInternacionEfector = $idEfector;
            }
            $this->internacion->solicitarInternacion($guardiaId, $idEfector, $idInternacionEfector);
            $guardia->refresh();
        }

        $pesId = (int) ($post['id_profesional_responsable'] ?? 0);
        if ($pesId <= 0) {
            $pesId = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        }

        $meta = [
            'checklist_indicaciones' => true,
            'checklist_epicrisis' => true,
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
            'registrado_at' => date('c'),
            'destino_label' => GuardiaEgresoDestino::label($destino),
        ];

        $guardia->destino_egreso = $destino;
        $guardia->diagnostico_operativo = $diagnostico;
        $guardia->epicrisis = $epicrisis;
        $guardia->pautas_alarma = $pautas !== '' ? $pautas : null;
        $guardia->egreso_meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);

        $guardia->updateAttributes([
            'destino_egreso' => $guardia->destino_egreso,
            'diagnostico_operativo' => $guardia->diagnostico_operativo,
            'epicrisis' => $guardia->epicrisis,
            'pautas_alarma' => $guardia->pautas_alarma,
            'egreso_meta_json' => $guardia->egreso_meta_json,
            'id_efector_derivacion' => $guardia->id_efector_derivacion,
            'condiciones_derivacion' => $guardia->condiciones_derivacion,
        ]);

        if (GuardiaEgresoDestino::requiresEfectorDerivacion($destino)) {
            $this->circuito->recordEvent($guardiaId, CircuitoEventType::DERIVACION, $pesId > 0 ? $pesId : null, [
                'id_efector_derivacion' => $idEfectorDerivacion,
                'via' => 'egreso_estructurado',
            ]);
        }

        $result = $this->operacion->finalizar($guardiaId, [
            'fecha_fin' => $this->normalizeFechaFin($post['fecha_fin'] ?? null),
            'hora_fin' => $this->normalizeHoraFin($post['hora_fin'] ?? null),
        ], $idEfector);

        if ($destino === GuardiaEgresoDestino::DERIVACION) {
            $guardia->refresh();
            $guardia->circuito_estado = CircuitoEstado::DERIVADO;
            $guardia->updateAttributes(['circuito_estado' => CircuitoEstado::DERIVADO]);
            $result['circuito_estado'] = CircuitoEstado::DERIVADO;
            $result['circuito_estado_label'] = CircuitoEstado::label(CircuitoEstado::DERIVADO);
        }

        $result['destino_egreso'] = $destino;
        $result['destino_egreso_label'] = GuardiaEgresoDestino::label($destino);
        $result['diagnostico_operativo'] = $diagnostico;
        $result['epicrisis_len'] = mb_strlen($epicrisis);
        $result['message'] = 'Egreso de guardia registrado (' . GuardiaEgresoDestino::label($destino) . ').';

        return $result;
    }

    private function loadActiva(int $guardiaId, int $idEfector): Guardia
    {
        $guardia = Guardia::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        GuardiaEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);
        $estado = $this->circuito->effectiveEstado($guardia);
        if ($estado === CircuitoEstado::FINALIZADO) {
            throw new \InvalidArgumentException('La guardia ya está finalizada.');
        }

        return $guardia;
    }

    /**
     * @param mixed $value
     */
    private function isTruthy($value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $v = strtolower(trim($value));

        return in_array($v, ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
    }

    /**
     * @param mixed $raw
     */
    private function normalizeFechaFin($raw): string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return date('d/m/Y');
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        return $s;
    }

    /**
     * @param mixed $raw
     */
    private function normalizeHoraFin($raw): string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return date('H:i');
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $s, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }

        return $s;
    }
}
