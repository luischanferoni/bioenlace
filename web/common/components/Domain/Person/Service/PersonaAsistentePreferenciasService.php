<?php

namespace common\components\Domain\Person\Service;

use common\models\Person\PersonaAsistentePreferencias;
use Yii;

/**
 * Preferencias del paciente sobre el asistente conversacional (extracto de HC).
 *
 * Sin fila = encendido (comportamiento histórico). No aplica a motivos ni captura.
 */
final class PersonaAsistentePreferenciasService
{
    /**
     * @return array{usa_resumen_hc_en_asistente: bool}
     */
    public function getForPersona(int $idPersona): array
    {
        if ($idPersona <= 0) {
            return $this->defaultsArray();
        }

        $row = $this->findRow($idPersona);

        return $row !== null ? $row->toApiArray() : $this->defaultsArray();
    }

    public function usaResumenHcEnAsistente(int $idPersona): bool
    {
        return $this->getForPersona($idPersona)['usa_resumen_hc_en_asistente'];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{usa_resumen_hc_en_asistente: bool}
     */
    public function saveForPersona(int $idPersona, array $input): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('id_persona inválido.');
        }
        if (!array_key_exists('usa_resumen_hc_en_asistente', $input)) {
            throw new \InvalidArgumentException('Falta usa_resumen_hc_en_asistente.');
        }

        $row = $this->findRow($idPersona);
        $now = date('Y-m-d H:i:s');
        if ($row === null) {
            $row = new PersonaAsistentePreferencias();
            $row->id_persona = $idPersona;
            $row->created_at = $now;
        }

        $row->usa_resumen_hc_en_asistente = $this->toBool($input['usa_resumen_hc_en_asistente']);
        $row->updated_at = $now;
        if (!$row->save()) {
            throw new \RuntimeException('No se pudieron guardar preferencias: ' . json_encode($row->errors));
        }

        return $row->toApiArray();
    }

    /**
     * @return array{usa_resumen_hc_en_asistente: bool}
     */
    public function defaultsArray(): array
    {
        return ['usa_resumen_hc_en_asistente' => true];
    }

    private function findRow(int $idPersona): ?PersonaAsistentePreferencias
    {
        try {
            return PersonaAsistentePreferencias::findOne(['id_persona' => $idPersona]);
        } catch (\Throwable $e) {
            Yii::warning('PersonaAsistentePreferenciasService: ' . $e->getMessage(), 'asistente');

            return null;
        }
    }

    /**
     * @param mixed $value
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        $s = strtolower(trim((string) $value));

        return in_array($s, ['1', 'true', 'si', 'sí', 'on'], true);
    }
}
