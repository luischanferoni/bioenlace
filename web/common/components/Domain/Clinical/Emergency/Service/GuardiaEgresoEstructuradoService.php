<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Domain\Clinical\Emergency\Enum\GuardiaEgresoDestino;
use common\models\Guardia;
use common\models\Persona;
use Yii;

/**
 * Egreso de guardia = cierre por retiro / fuga / abandono.
 *
 * El médico documenta el encounter (captura); no hay “egreso clínico” con diag/epicrisis.
 * Esta acción solo registra que el paciente se retiró (con o sin atención previa).
 */
final class GuardiaEgresoEstructuradoService
{
    /** @var GuardiaOperacionService */
    private $operacion;

    /** @var GuardiaCircuitoService */
    private $circuito;

    public function __construct(
        ?GuardiaOperacionService $operacion = null,
        ?GuardiaCircuitoService $circuito = null
    ) {
        $this->operacion = $operacion ?? new GuardiaOperacionService();
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
    }

    /**
     * Único modo de este formulario: retiro / abandono.
     */
    public function resolveModo(Guardia $guardia): string
    {
        return GuardiaEgresoDestino::MODO_ADMINISTRATIVO;
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
        $huboAtencion = in_array($estado, [CircuitoEstado::EN_ATENCION, CircuitoEstado::ATENDIDO], true);

        $resumen = $nombre . ' — Paciente se retiró / abandono. '
            . 'Cierra el circuito de guardia. La documentación clínica (si hubo) queda en la captura; '
            . 'acá no se pide diagnóstico ni epicrisis.';
        if ($huboAtencion) {
            $resumen .= ' Usalo solo si el paciente se fue; no sustituye la captura del encounter.';
        }

        return [
            'guardia_id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'paciente_nombre' => $nombre,
            'circuito_estado' => $estado,
            'circuito_estado_label' => CircuitoEstado::label($estado),
            'modo_egreso' => GuardiaEgresoDestino::MODO_ADMINISTRATIVO,
            'hubo_atencion' => $huboAtencion,
            'destinos' => GuardiaEgresoDestino::optionsForModo(GuardiaEgresoDestino::MODO_ADMINISTRATIVO),
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
            'diagnostico_operativo' => '',
            'epicrisis' => '',
            'resumen_texto' => $resumen,
            'egreso_formulario_path' => '/api/v1/clinical/emergency-guardia/'
                . (int) $guardia->id . '/egreso-formulario',
        ];
    }

    /**
     * Formulario mínimo: fecha/hora, destino FUGA, nota opcional.
     *
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    public function shapeUiDefinition(array $ui, array $ctx): array
    {
        $destinoOptions = array_map(static fn (array $d): array => [
            'value' => (string) $d['value'],
            'label' => (string) $d['label'],
        ], $ctx['destinos'] ?? []);

        $clinicoOnly = [
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
                $block['text'] = (string) ($ctx['resumen_texto'] ?? '');
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
                if (isset($clinicoOnly[$name])) {
                    continue;
                }
                if ($name === 'destino_egreso') {
                    $field['options'] = $destinoOptions;
                    $field['value'] = (string) ($destinoOptions[0]['value'] ?? GuardiaEgresoDestino::FUGA);
                    $field['label'] = 'Motivo de cierre';
                }
                if ($name === 'modo_egreso') {
                    $field['value'] = GuardiaEgresoDestino::MODO_ADMINISTRATIVO;
                }
                if ($name === 'nota_administrativa') {
                    $field['label'] = 'Nota (opcional)';
                }
                $fields[] = $field;
            }
            $block['fields'] = $fields;
            $block['title'] = 'Retiro / abandono';
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

        $destino = strtoupper(trim((string) ($post['destino_egreso'] ?? $post['destino'] ?? GuardiaEgresoDestino::FUGA)));
        if ($destino === '') {
            $destino = GuardiaEgresoDestino::FUGA;
        }
        if (!in_array($destino, GuardiaEgresoDestino::valuesAdministrativos(), true)) {
            throw new \InvalidArgumentException(
                'El egreso desde este formulario solo admite retiro / fuga / abandono. '
                . 'La atención clínica se documenta en la captura.'
            );
        }

        $nota = trim((string) ($post['nota_administrativa'] ?? $post['condiciones_derivacion'] ?? ''));
        $pesId = $this->resolvePesId($post);
        $meta = [
            'modo_egreso' => GuardiaEgresoDestino::MODO_ADMINISTRATIVO,
            'nota_administrativa' => $nota !== '' ? $nota : null,
            'registrado_at' => date('c'),
            'destino_label' => GuardiaEgresoDestino::label($destino),
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
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

        $result['modo_egreso'] = GuardiaEgresoDestino::MODO_ADMINISTRATIVO;
        $result['destino_egreso'] = $destino;
        $result['destino_egreso_label'] = GuardiaEgresoDestino::label($destino);
        $result['message'] = 'Registrado: paciente se retiró (' . GuardiaEgresoDestino::label($destino) . ').';

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
