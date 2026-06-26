<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Person\PersonaAgendaPreferencias;
use common\models\Scheduling\Turno;

/**
 * Lectura/escritura de preferencias de agenda del paciente.
 */
final class PersonaAgendaPreferenciasService
{
    /**
     * @return array<string, mixed>
     */
    public function getForPersona(int $idPersona): array
    {
        if ($idPersona <= 0) {
            return $this->defaultsArray();
        }

        $row = PersonaAgendaPreferencias::findOne(['id_persona' => $idPersona]);

        return $row !== null ? $row->toApiArray() : $this->defaultsArray();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveForPersona(int $idPersona, array $input): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('id_persona inválido.');
        }

        $row = PersonaAgendaPreferencias::findOne(['id_persona' => $idPersona]);
        $now = date('Y-m-d H:i:s');
        if ($row === null) {
            $row = new PersonaAgendaPreferencias();
            $row->id_persona = $idPersona;
            $row->created_at = $now;
        }

        if (array_key_exists('auto_reserva_resolucion', $input)) {
            $row->auto_reserva_resolucion = (bool) $input['auto_reserva_resolucion'];
        }
        if (array_key_exists('mismo_pes_prioritario', $input)) {
            $row->mismo_pes_prioritario = (bool) $input['mismo_pes_prioritario'];
        }
        if (array_key_exists('franjas', $input)) {
            $row->franjas_json = $this->encodeFranjas($input['franjas']);
        }
        if (array_key_exists('dias_semana', $input)) {
            $row->dias_semana_json = $this->encodeDiasSemana($input['dias_semana']);
        }
        if (array_key_exists('tipo_atencion_preferido', $input)) {
            $tipo = trim((string) $input['tipo_atencion_preferido']);
            $row->tipo_atencion_preferido = $tipo !== '' ? $tipo : null;
        }

        $row->updated_at = $now;
        if (!$row->save()) {
            throw new \RuntimeException('No se pudieron guardar preferencias: ' . json_encode($row->errors));
        }

        return $row->toApiArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsArray(): array
    {
        return [
            'auto_reserva_resolucion' => false,
            'franjas' => [],
            'dias_semana' => [],
            'tipo_atencion_preferido' => null,
            'mismo_pes_prioritario' => true,
        ];
    }

    /**
     * @param mixed $franjas
     */
    private function encodeFranjas($franjas): ?string
    {
        if (!is_array($franjas)) {
            return null;
        }
        $out = [];
        foreach ($franjas as $f) {
            $s = strtoupper(trim((string) $f));
            if (in_array($s, ['MANANA', 'MAÑANA', 'TARDE', 'NOCHE'], true)) {
                $out[] = $s === 'MAÑANA' ? 'MANANA' : $s;
            }
        }

        return $out === [] ? null : json_encode(array_values(array_unique($out)), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param mixed $dias
     */
    private function encodeDiasSemana($dias): ?string
    {
        if (!is_array($dias)) {
            return null;
        }
        $out = [];
        foreach ($dias as $d) {
            $n = (int) $d;
            if ($n >= 1 && $n <= 7) {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));

        return $out === [] ? null : json_encode($out, JSON_UNESCAPED_UNICODE);
    }
}
