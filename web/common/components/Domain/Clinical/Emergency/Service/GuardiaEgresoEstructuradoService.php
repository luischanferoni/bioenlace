<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Domain\Clinical\Emergency\Enum\CircuitoEventType;
use common\components\Domain\Clinical\Emergency\Enum\GuardiaEgresoDestino;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use common\models\Persona;
use Yii;

/**
 * Egreso de guardia en dos modos:
 * - clínico (médico, episodio en atención): destino + confirmación; diag/epicrisis heredados o cortos
 * - administrativo (staff, sin atención): fuga/abandono + fecha/hora + nota
 */
final class GuardiaEgresoEstructuradoService
{
    /** @var GuardiaOperacionService */
    private $operacion;

    /** @var GuardiaInternacionService */
    private $internacion;

    /** @var GuardiaCircuitoService */
    private $circuito;

    /** @var GuardiaEncounterResolver */
    private $encounterResolver;

    public function __construct(
        ?GuardiaOperacionService $operacion = null,
        ?GuardiaInternacionService $internacion = null,
        ?GuardiaCircuitoService $circuito = null,
        ?GuardiaEncounterResolver $encounterResolver = null
    ) {
        $this->operacion = $operacion ?? new GuardiaOperacionService();
        $this->internacion = $internacion ?? new GuardiaInternacionService();
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
        $this->encounterResolver = $encounterResolver ?? new GuardiaEncounterResolver();
    }

    /**
     * Modo según circuito: en atención → clínico; resto abierto → administrativo.
     */
    public function resolveModo(Guardia $guardia): string
    {
        $estado = $this->circuito->effectiveEstado($guardia);
        if (
            $estado === CircuitoEstado::EN_ATENCION
            || $estado === CircuitoEstado::ATENDIDO
        ) {
            return GuardiaEgresoDestino::MODO_CLINICO;
        }

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
        $modo = $this->resolveModo($guardia);
        $prefill = $this->prefillClinico($guardia);

        if ($modo === GuardiaEgresoDestino::MODO_ADMINISTRATIVO) {
            $resumen = $nombre . ' — Egreso administrativo (sin atención médica): '
                . 'registrá fuga / abandono / retiro. No requiere diagnóstico ni epicrisis.';
        } else {
            $resumen = $nombre . ' — Egreso clínico: elegí el destino. '
                . 'Diagnóstico y epicrisis se confirman desde la captura (no es un segundo dictado). '
                . 'Pedidos o derivaciones que retengan van en la consulta, no acá.';
        }

        return [
            'guardia_id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'paciente_nombre' => $nombre,
            'circuito_estado' => $this->circuito->effectiveEstado($guardia),
            'circuito_estado_label' => CircuitoEstado::label($this->circuito->effectiveEstado($guardia)),
            'modo_egreso' => $modo,
            'destinos' => GuardiaEgresoDestino::optionsForModo($modo),
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
            'diagnostico_operativo' => $prefill['diagnostico_operativo'],
            'epicrisis' => $prefill['epicrisis'],
            'resumen_texto' => $resumen,
            'egreso_formulario_path' => '/api/v1/clinical/emergency-guardia/'
                . (int) $guardia->id . '/egreso-formulario',
        ];
    }

    /**
     * Ajusta el descriptor UI JSON según modo (campos y opciones).
     *
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    public function shapeUiDefinition(array $ui, array $ctx): array
    {
        $modo = (string) ($ctx['modo_egreso'] ?? GuardiaEgresoDestino::MODO_CLINICO);
        $destinoOptions = array_map(static fn (array $d): array => [
            'value' => (string) $d['value'],
            'label' => (string) $d['label'],
        ], $ctx['destinos'] ?? []);

        $adminOnly = [
            'nota_administrativa' => true,
        ];
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

        $ui['title'] = $modo === GuardiaEgresoDestino::MODO_ADMINISTRATIVO
            ? 'Egreso administrativo (abandono / retiro)'
            : 'Egreso clínico de guardia';

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
                if ($modo === GuardiaEgresoDestino::MODO_ADMINISTRATIVO) {
                    if (isset($clinicoOnly[$name])) {
                        continue;
                    }
                } else {
                    if (isset($adminOnly[$name])) {
                        continue;
                    }
                }
                if ($name === 'destino_egreso') {
                    $field['options'] = $destinoOptions;
                    if ($modo === GuardiaEgresoDestino::MODO_ADMINISTRATIVO && $destinoOptions !== []) {
                        $field['value'] = (string) ($destinoOptions[0]['value'] ?? GuardiaEgresoDestino::FUGA);
                    }
                }
                if ($name === 'modo_egreso') {
                    $field['value'] = $modo;
                }
                if ($name === 'diagnostico_operativo' && !empty($ctx['diagnostico_operativo'])) {
                    $field['value'] = (string) $ctx['diagnostico_operativo'];
                }
                if ($name === 'epicrisis' && !empty($ctx['epicrisis'])) {
                    $field['value'] = (string) $ctx['epicrisis'];
                }
                $fields[] = $field;
            }
            $block['fields'] = $fields;
            $block['title'] = $modo === GuardiaEgresoDestino::MODO_ADMINISTRATIVO
                ? 'Cierre administrativo'
                : 'Conducta / egreso clínico';
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
        $modo = $this->resolveModo($guardia);
        $postModo = strtolower(trim((string) ($post['modo_egreso'] ?? '')));
        if ($postModo !== '' && $postModo !== $modo) {
            throw new \InvalidArgumentException(
                'El modo de egreso no coincide con el estado del episodio ('
                . ($modo === GuardiaEgresoDestino::MODO_CLINICO ? 'clínico' : 'administrativo') . ').'
            );
        }

        $destino = strtoupper(trim((string) ($post['destino_egreso'] ?? $post['destino'] ?? '')));
        $allowed = $modo === GuardiaEgresoDestino::MODO_ADMINISTRATIVO
            ? GuardiaEgresoDestino::valuesAdministrativos()
            : GuardiaEgresoDestino::valuesClinicos();
        if (!in_array($destino, $allowed, true)) {
            throw new \InvalidArgumentException('Se requiere un destino de egreso válido para este modo.');
        }

        if ($modo === GuardiaEgresoDestino::MODO_ADMINISTRATIVO) {
            return $this->registrarAdministrativo($guardia, $guardiaId, $idEfector, $destino, $post);
        }

        return $this->registrarClinico($guardia, $guardiaId, $idEfector, $destino, $post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function registrarAdministrativo(
        Guardia $guardia,
        int $guardiaId,
        int $idEfector,
        string $destino,
        array $post
    ): array {
        $nota = trim((string) ($post['nota_administrativa'] ?? $post['condiciones_derivacion'] ?? ''));
        $meta = [
            'modo_egreso' => GuardiaEgresoDestino::MODO_ADMINISTRATIVO,
            'nota_administrativa' => $nota !== '' ? $nota : null,
            'registrado_at' => date('c'),
            'destino_label' => GuardiaEgresoDestino::label($destino),
            'responsable_pes_id' => $this->resolvePesId($post),
        ];

        $guardia->destino_egreso = $destino;
        $guardia->diagnostico_operativo = null;
        $guardia->epicrisis = $nota !== '' ? ('Egreso administrativo: ' . $nota) : 'Egreso administrativo (fuga/abandono).';
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
        $result['message'] = 'Egreso administrativo registrado (' . GuardiaEgresoDestino::label($destino) . ').';

        return $result;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function registrarClinico(
        Guardia $guardia,
        int $guardiaId,
        int $idEfector,
        string $destino,
        array $post
    ): array {
        $prefill = $this->prefillClinico($guardia);
        $diagnostico = trim((string) ($post['diagnostico_operativo'] ?? ''));
        if ($diagnostico === '') {
            $diagnostico = $prefill['diagnostico_operativo'];
        }
        if (mb_strlen($diagnostico) < 5) {
            throw new \InvalidArgumentException(
                'Confirmá el diagnóstico operativo (mín. 5 caracteres). Preferible el de la captura.'
            );
        }

        $epicrisis = trim((string) ($post['epicrisis'] ?? ''));
        if ($epicrisis === '') {
            $epicrisis = $prefill['epicrisis'];
        }
        if (mb_strlen($epicrisis) < 20) {
            throw new \InvalidArgumentException(
                'Confirmá la epicrisis o resumen de conducta (mín. 20 caracteres), idealmente desde la captura.'
            );
        }

        $pautas = trim((string) ($post['pautas_alarma'] ?? ''));
        if (GuardiaEgresoDestino::requiresPautasAlarma($destino) && mb_strlen($pautas) < 10) {
            throw new \InvalidArgumentException(
                'Para alta domiciliaria indicá pautas de alarma (mín. 10 caracteres).'
            );
        }

        foreach (['checklist_indicaciones', 'checklist_epicrisis', 'checklist_sin_retencion'] as $chk) {
            if (!$this->isTruthy($post[$chk] ?? null)) {
                throw new \InvalidArgumentException(
                    'Confirmá el checklist de egreso (indicaciones, sin retención por pedidos y epicrisis).'
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

        $pesId = $this->resolvePesId($post);

        $meta = [
            'modo_egreso' => GuardiaEgresoDestino::MODO_CLINICO,
            'checklist_indicaciones' => true,
            'checklist_epicrisis' => true,
            'checklist_sin_retencion' => true,
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

        $result['modo_egreso'] = GuardiaEgresoDestino::MODO_CLINICO;
        $result['destino_egreso'] = $destino;
        $result['destino_egreso_label'] = GuardiaEgresoDestino::label($destino);
        $result['diagnostico_operativo'] = $diagnostico;
        $result['epicrisis_len'] = mb_strlen($epicrisis);
        $result['message'] = 'Egreso clínico registrado (' . GuardiaEgresoDestino::label($destino) . ').';

        return $result;
    }

    /**
     * @return array{diagnostico_operativo: string, epicrisis: string}
     */
    private function prefillClinico(Guardia $guardia): array
    {
        $diag = trim((string) ($guardia->diagnostico_operativo ?? ''));
        $epi = trim((string) ($guardia->epicrisis ?? ''));

        $encounter = $this->encounterResolver->findLatestForGuardia((int) $guardia->id);
        if ($encounter instanceof Encounter) {
            if ($diag === '') {
                $cond = Condition::find()
                    ->where(['encounter_id' => (int) $encounter->id, 'deleted_at' => null])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
                if ($cond instanceof Condition) {
                    $diag = trim((string) ($cond->display ?? ''));
                }
            }
            if ($epi === '') {
                $reason = trim((string) ($encounter->reason_text ?? ''));
                if ($reason !== '') {
                    $epi = $reason;
                }
            }
        }

        if ($diag === '') {
            $triage = GuardiaTriage::findOne(['guardia_id' => (int) $guardia->id]);
            if ($triage !== null) {
                $diag = trim((string) ($triage->reason_text ?? ''));
            }
        }

        return [
            'diagnostico_operativo' => $diag,
            'epicrisis' => $epi,
        ];
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
