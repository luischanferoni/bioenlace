<?php

namespace common\components\Domain\Clinical\Emergency\Application\UseCase;

use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyBoardService;
use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyOperationService;
use common\components\Domain\Clinical\Emergency\Domain\BoardState;
use common\components\Domain\Clinical\Emergency\Domain\EmergencyDischargeDestination;
use common\models\Clinical\Emergency\EmergencyEpisode;
use common\models\Person\Persona;
use Yii;

/**
 * Egreso de guardia = cierre por retiro / fuga / abandono (destino fijo FUGA).
 *
 * El médico documenta el encounter (captura); este formulario solo confirma retiro.
 * guardia_id viene de la URL; el motivo no se elige — está asociado a esta acción.
 */
final class DischargeEmergencyEpisode
{
    /** @var EmergencyOperationService */
    private $operacion;

    /** @var EmergencyBoardService */
    private $circuito;

    public function __construct(
        ?EmergencyOperationService $operacion = null,
        ?EmergencyBoardService $circuito = null
    ) {
        $this->operacion = $operacion ?? new EmergencyOperationService();
        $this->circuito = $circuito ?? new EmergencyBoardService();
    }

    public function resolveModo(EmergencyEpisode $guardia): string
    {
        return EmergencyDischargeDestination::MODO_ADMINISTRATIVO;
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
        $estado = $this->circuito->effectiveEstado($guardia);
        $huboAtencion = in_array($estado, [BoardState::EN_ATENCION, BoardState::ATENDIDO], true);

        return [
            'guardia_id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'paciente_nombre' => $nombre,
            'circuito_estado' => $estado,
            'circuito_estado_label' => BoardState::label($estado),
            'modo_egreso' => EmergencyDischargeDestination::MODO_ADMINISTRATIVO,
            'destino_egreso' => EmergencyDischargeDestination::FUGA,
            'hubo_atencion' => $huboAtencion,
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
            'resumen_texto' => $nombre,
            'egreso_formulario_path' => '/api/v1/clinical/emergency-guardia/'
                . (int) $guardia->id . '/egreso-formulario',
        ];
    }

    /**
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    public function shapeUiDefinition(array $ui, array $ctx): array
    {
        $omit = [
            'guardia_id' => true,
            'destino_egreso' => true,
            'diagnostico_operativo' => true,
            'epicrisis' => true,
            'pautas_alarma' => true,
            'id_efector_derivacion' => true,
            'condiciones_derivacion' => true,
            'checklist_indicaciones' => true,
            'checklist_sin_retencion' => true,
            'checklist_epicrisis' => true,
        ];

        $ui['title'] = 'Paciente se retiró';

        foreach ($ui['blocks'] ?? [] as $idx => $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['kind'] ?? '') === 'message' && ($block['id'] ?? '') === 'paciente') {
                $block['text'] = (string) ($ctx['paciente_nombre'] ?? $ctx['resumen_texto'] ?? '');
                $block['title'] = '';
            }
            if (($block['kind'] ?? '') !== 'fields') {
                $ui['blocks'][$idx] = $block;
                continue;
            }
            $fields = [];
            foreach ($block['fields'] ?? [] as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = (string) ($field['name'] ?? '');
                if (isset($omit[$name])) {
                    continue;
                }
                if ($name === 'modo_egreso') {
                    $field['value'] = EmergencyDischargeDestination::MODO_ADMINISTRATIVO;
                }
                $fields[] = $field;
            }
            $block['fields'] = $fields;
            unset($block['title']);
            $ui['blocks'][$idx] = $block;
        }

        return $ui;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function registrar(int $guardiaId, int $idEfector, array $post): array
    {
        $guardia = $this->loadActiva($guardiaId, $idEfector);
        $destino = EmergencyDischargeDestination::FUGA;

        $nota = trim((string) ($post['nota_administrativa'] ?? ''));
        $pesId = $this->resolvePesId($post);
        $meta = [
            'modo_egreso' => EmergencyDischargeDestination::MODO_ADMINISTRATIVO,
            'nota_administrativa' => $nota !== '' ? $nota : null,
            'registrado_at' => date('c'),
            'destino_label' => EmergencyDischargeDestination::label($destino),
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
            'via' => 'paciente_se_retiro',
        ];

        $guardia->destino_egreso = $destino;
        $guardia->diagnostico_operativo = null;
        $guardia->epicrisis = $nota !== ''
            ? ('Paciente se retiró: ' . $nota)
            : 'Paciente se retiró / abandono.';
        $guardia->pautas_alarma = null;
        $guardia->egreso_meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);

        $guardia->updateAttributes([
            'destino_egreso' => $guardia->destino_egreso,
            'diagnostico_operativo' => $guardia->diagnostico_operativo,
            'epicrisis' => $guardia->epicrisis,
            'pautas_alarma' => $guardia->pautas_alarma,
            'egreso_meta_json' => $guardia->egreso_meta_json,
        ]);

        $result = $this->operacion->finalizar($guardiaId, [
            'fecha_fin' => $this->normalizeFechaFin($post['fecha_fin'] ?? null),
            'hora_fin' => $this->normalizeHoraFin($post['hora_fin'] ?? null),
        ], $idEfector);

        $result['modo_egreso'] = EmergencyDischargeDestination::MODO_ADMINISTRATIVO;
        $result['destino_egreso'] = $destino;
        $result['destino_egreso_label'] = EmergencyDischargeDestination::label($destino);
        $result['message'] = 'Registrado: paciente se retiró.';

        return $result;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function resolvePesId(array $post): int
    {
        $pesId = (int) ($post['id_profesional_responsable'] ?? 0);
        if ($pesId <= 0) {
            $pesId = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        }

        return $pesId;
    }

    private function loadActiva(int $guardiaId, int $idEfector): Guardia
    {
        $guardia = EmergencyEpisode::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        EmergencyEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);
        $estado = $this->circuito->effectiveEstado($guardia);
        if ($estado === BoardState::FINALIZADO) {
            throw new \InvalidArgumentException('La guardia ya está finalizada.');
        }

        return $guardia;
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
