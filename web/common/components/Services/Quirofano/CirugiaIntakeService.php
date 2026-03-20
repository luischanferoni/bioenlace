<?php

namespace common\components\Services\Quirofano;

use Yii;
use common\models\Cirugia;
use common\models\Persona;
use common\models\QuirofanoSala;
use common\services\EntityIntake\EntitySchemaRegistry;
use common\services\EntityIntake\ResponseParser;

/**
 * Interpreta texto libre (p. ej. dictado) y devuelve borrador + campos faltantes para crear una cirugía vía API.
 * No lanza excepciones HTTP.
 */
final class CirugiaIntakeService
{
    private const ENTITY = 'quirofano_cirugia';

    /**
     * @return array{
     *   draft: array<string, mixed>,
     *   missing_fields: string[],
     *   hints: array<string, string>,
     *   confidence: float,
     *   parse_error: ?string
     * }
     */
    public function procesar(string $texto, int $idEfector): array
    {
        $texto = trim($texto);
        $schema = EntitySchemaRegistry::getSchema(self::ENTITY, null);
        if ($schema === null) {
            return $this->emptyResult('schema_not_found');
        }

        $salas = QuirofanoSala::find()
            ->where(['id_efector' => $idEfector, 'deleted_at' => null])
            ->orderBy(['nombre' => SORT_ASC])
            ->all();

        if ($texto === '') {
            return $this->emptyResult(null);
        }

        if (!Yii::$app->has('iamanager')) {
            return $this->emptyResult('ia_no_configurada');
        }

        $prompt = $this->buildPrompt($texto, $schema, $salas);

        try {
            $raw = Yii::$app->iamanager->consultar($prompt, 'quirofano-cirugia-intake', 'analysis');
        } catch (\Throwable $e) {
            Yii::error('CirugiaIntakeService: ' . $e->getMessage(), 'quirofano-intake');
            return $this->emptyResult('ia_error');
        }

        $parsed = ResponseParser::parse($raw);
        $prefill = $parsed['prefill'] ?? [];

        $draft = $this->extractDraft($prefill);
        $this->resolveSala($draft, $prefill, $salas);
        $this->resolvePersona($draft, $prefill);
        $this->normalizeDatetimes($draft);
        $this->normalizeEstado($draft);

        $hints = $this->buildHints($draft, $prefill);

        return [
            'draft' => $draft,
            'missing_fields' => $this->computeMissing($draft),
            'hints' => $hints,
            'confidence' => (float) ($parsed['confidence'] ?? 0.0),
            'parse_error' => $parsed['parse_error'] ?? null,
        ];
    }

    private function buildPrompt(string $texto, array $schema, array $salas): string
    {
        $required = $schema['required'] ?? [];
        $optional = $schema['optional'] ?? [];
        $fields = array_values(array_unique(array_merge($required, $optional)));
        $fieldsList = implode(', ', $fields);
        $requiredList = implode(', ', $required);

        $salasLines = [];
        foreach ($salas as $s) {
            $line = 'id=' . (int) $s->id . ' nombre="' . str_replace('"', "'", (string) $s->nombre) . '"';
            if ($s->codigo !== null && $s->codigo !== '') {
                $line .= ' codigo="' . str_replace('"', "'", (string) $s->codigo) . '"';
            }
            $salasLines[] = $line;
        }
        $salasBlock = $salasLines !== [] ? implode("\n", $salasLines) : '(sin salas en este efector)';

        return "Extrae datos estructurados en JSON para registrar una cirugía en agenda quirúrgica.\n"
            . "Entity: quirofano_cirugia\n"
            . "Campos disponibles: {$fieldsList}\n"
            . "Campos requeridos en el borrador final: {$requiredList}\n\n"
            . "Salas del efector (usá id_quirofano_sala si podés identificar la sala; si no, sala_nombre y/o sala_codigo):\n"
            . $salasBlock . "\n\n"
            . "Reglas:\n"
            . "- Responde SOLO con JSON válido (sin texto antes o después).\n"
            . "- Si un campo no está en el texto, usa null. No inventes pacientes ni horarios.\n"
            . "- id_persona solo si el texto lo indica explícitamente; si hay DNI del paciente usá documento_paciente (solo dígitos).\n"
            . "- fecha_hora_inicio y fecha_hora_fin_estimada en formato que strtotime entienda (ej. YYYY-MM-DD HH:MM) o null.\n"
            . "- estado solo si el texto lo menciona; valores: LISTA_ESPERA, CONFIRMADA, EN_CURSO, REALIZADA, CANCELADA, SUSPENDIDA.\n\n"
            . "Formato EXACTO:\n"
            . "{\"prefill\":{ \"campo\": null }, \"missing_required\": [\"campo\"], \"confidence\": 0.0}\n\n"
            . "Texto: \"" . str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $texto) . "\"";
    }

    private function extractDraft(array $prefill): array
    {
        $keys = [
            'id_quirofano_sala', 'id_persona', 'fecha_hora_inicio', 'fecha_hora_fin_estimada',
            'id_seg_nivel_internacion', 'id_practica', 'procedimiento_descripcion', 'observaciones', 'estado',
        ];
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $prefill)) {
                continue;
            }
            $v = $prefill[$k];
            if ($v === null || $v === '') {
                continue;
            }
            if (in_array($k, ['id_quirofano_sala', 'id_persona', 'id_seg_nivel_internacion', 'id_practica'], true)) {
                if (is_numeric($v)) {
                    $out[$k] = (int) $v;
                }
            } else {
                $out[$k] = is_string($v) ? trim($v) : $v;
            }
        }
        return $out;
    }

    /**
     * @param QuirofanoSala[] $salas
     */
    private function resolveSala(array &$draft, array $prefill, array $salas): void
    {
        if (!empty($draft['id_quirofano_sala'])) {
            $allowed = false;
            foreach ($salas as $s) {
                if ((int) $s->id === (int) $draft['id_quirofano_sala']) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                unset($draft['id_quirofano_sala']);
            }
            return;
        }

        $nombre = isset($prefill['sala_nombre']) ? trim((string) $prefill['sala_nombre']) : '';
        $codigo = isset($prefill['sala_codigo']) ? trim((string) $prefill['sala_codigo']) : '';
        if ($nombre === '' && $codigo === '') {
            return;
        }

        $codigoLower = $codigo !== '' ? mb_strtolower($codigo) : '';
        foreach ($salas as $s) {
            if ($codigo !== '' && $s->codigo !== null && mb_strtolower(trim((string) $s->codigo)) === $codigoLower) {
                $draft['id_quirofano_sala'] = (int) $s->id;
                return;
            }
        }

        $nombreLower = $nombre !== '' ? mb_strtolower($nombre) : '';
        foreach ($salas as $s) {
            if ($nombre !== '' && mb_strtolower(trim((string) $s->nombre)) === $nombreLower) {
                $draft['id_quirofano_sala'] = (int) $s->id;
                return;
            }
        }

        if ($nombre !== '') {
            foreach ($salas as $s) {
                $sn = mb_strtolower((string) $s->nombre);
                if (mb_stripos($sn, $nombreLower) !== false || mb_stripos($nombreLower, $sn) !== false) {
                    $draft['id_quirofano_sala'] = (int) $s->id;
                    return;
                }
            }
        }
    }

    private function resolvePersona(array &$draft, array $prefill): void
    {
        if (!empty($draft['id_persona'])) {
            return;
        }
        $doc = $prefill['documento_paciente'] ?? null;
        if ($doc === null || $doc === '') {
            return;
        }
        $digits = preg_replace('/\D+/', '', (string) $doc);
        if ($digits === '') {
            return;
        }
        $p = Persona::find()->where(['documento' => $digits])->one();
        if ($p !== null) {
            $draft['id_persona'] = (int) $p->id_persona;
        }
    }

    private function normalizeDatetimes(array &$draft): void
    {
        foreach (['fecha_hora_inicio', 'fecha_hora_fin_estimada'] as $attr) {
            if (empty($draft[$attr]) || !is_string($draft[$attr])) {
                continue;
            }
            $ts = strtotime($draft[$attr]);
            if ($ts !== false) {
                $draft[$attr] = date('Y-m-d H:i:s', $ts);
            }
        }
    }

    private function normalizeEstado(array &$draft): void
    {
        if (empty($draft['estado']) || !is_string($draft['estado'])) {
            unset($draft['estado']);
            return;
        }
        $e = strtoupper(trim($draft['estado']));
        if (!array_key_exists($e, Cirugia::ESTADOS)) {
            unset($draft['estado']);
            return;
        }
        $draft['estado'] = $e;
    }

    private function computeMissing(array $draft): array
    {
        $required = ['id_quirofano_sala', 'id_persona', 'fecha_hora_inicio', 'fecha_hora_fin_estimada'];
        $missing = [];
        foreach ($required as $f) {
            if (!isset($draft[$f]) || $draft[$f] === '' || $draft[$f] === null) {
                $missing[] = $f;
            }
        }
        return $missing;
    }

    private function buildHints(array $draft, array $prefill): array
    {
        $hints = [];
        if (empty($draft['id_persona'])) {
            $d = $prefill['documento_paciente'] ?? null;
            if ($d !== null && $d !== '') {
                $hints['documento_paciente'] = (string) $d;
            }
        }
        if (empty($draft['id_quirofano_sala'])) {
            if (!empty($prefill['sala_nombre'])) {
                $hints['sala_nombre'] = trim((string) $prefill['sala_nombre']);
            }
            if (!empty($prefill['sala_codigo'])) {
                $hints['sala_codigo'] = trim((string) $prefill['sala_codigo']);
            }
        }
        return $hints;
    }

    private function emptyResult(?string $code): array
    {
        return [
            'draft' => [],
            'missing_fields' => ['id_quirofano_sala', 'id_persona', 'fecha_hora_inicio', 'fecha_hora_fin_estimada'],
            'hints' => [],
            'confidence' => 0.0,
            'parse_error' => $code,
        ];
    }
}
