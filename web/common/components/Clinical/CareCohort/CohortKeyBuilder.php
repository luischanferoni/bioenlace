<?php

namespace common\components\Clinical\CareCohort;

use common\components\Clinical\AiContext\PatientAiContextBuilder;
use common\models\Clinical\Encounter;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use common\models\Efector;
use common\models\Persona;

/**
 * Perfil de cohorte estable y clave hash para reutilizar packs de asistencia/seguimiento/educación.
 */
final class CohortKeyBuilder
{
    /**
     * @return array{cohort_key: string, profile: array<string, mixed>}
     */
    public function buildForPersona(int $subjectPersonaId, ?Encounter $encounter = null): array
    {
        $persona = Persona::findOne(['id_persona' => $subjectPersonaId]);
        $profile = [
            'life_stage' => $this->lifeStage($persona),
            'sexo' => $this->sexBucket($persona),
            'conditions' => $this->conditionSlugs($subjectPersonaId, 3),
            'motive_cluster' => $this->motiveCluster($encounter),
            'jurisdiction' => $this->jurisdiction($encounter),
            'season' => $this->seasonBucket(),
        ];

        return [
            'cohort_key' => $this->hashProfile($profile),
            'profile' => $profile,
        ];
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function hashProfile(array $profile): string
    {
        $normalized = $profile;
        ksort($normalized);
        if (isset($normalized['conditions']) && is_array($normalized['conditions'])) {
            sort($normalized['conditions']);
        }

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }

    private function lifeStage(?Persona $persona): string
    {
        $edad = null;
        if ($persona !== null) {
            if (method_exists($persona, 'getEdad')) {
                $edad = (int) $persona->getEdad();
            } elseif (isset($persona->edad)) {
                $edad = (int) $persona->edad;
            }
        }
        if ($edad === null || $edad < 0) {
            return 'unknown';
        }
        if ($edad <= 17) {
            return '0-17';
        }
        if ($edad <= 39) {
            return '18-39';
        }
        if ($edad <= 64) {
            return '40-64';
        }

        return '65+';
    }

    private function sexBucket(?Persona $persona): string
    {
        if ($persona === null) {
            return 'U';
        }
        $raw = null;
        if (method_exists($persona, 'getSexoLetra')) {
            $raw = strtoupper(trim((string) $persona->getSexoLetra()));
        } elseif (isset($persona->sexo)) {
            $raw = strtoupper(trim((string) $persona->sexo));
        }
        if (in_array($raw, ['M', 'F', 'O'], true)) {
            return $raw;
        }

        return 'U';
    }

    /**
     * @return list<string>
     */
    private function conditionSlugs(int $subjectPersonaId, int $limit): array
    {
        [$activas, $cronicas] = DCRepo::getCondicionesPaciente($subjectPersonaId);
        $seen = [];
        $slugs = [];

        foreach (array_merge($cronicas, $activas) as $cond) {
            $term = isset($cond->codigoSnomed) ? trim((string) $cond->codigoSnomed->term) : '';
            if ($term === '') {
                continue;
            }
            $slug = $this->slugify($term);
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $slugs[] = $slug;
            if (count($slugs) >= $limit) {
                break;
            }
        }

        if ($slugs === []) {
            $slugs[] = 'sin_condicion_registrada';
        }

        return $slugs;
    }

    private function motiveCluster(?Encounter $encounter): string
    {
        if ($encounter === null) {
            return 'general';
        }
        $reason = trim((string) $encounter->reason_text);
        if ($reason === '') {
            return 'general';
        }
        $slug = $this->slugify(mb_substr($reason, 0, 120));

        return $slug !== '' ? $slug : 'general';
    }

    private function jurisdiction(?Encounter $encounter): string
    {
        if ($encounter === null || (int) $encounter->efector_id <= 0) {
            return 'unknown';
        }
        $efector = Efector::findOne(['id_efector' => (int) $encounter->efector_id]);
        if ($efector === null || (int) $efector->id_localidad <= 0) {
            return 'unknown';
        }
        $localidad = $efector->idLocalidad;
        if ($localidad === null) {
            return 'unknown';
        }
        if (isset($localidad->id_provincia) && (int) $localidad->id_provincia > 0) {
            return 'prov-' . (int) $localidad->id_provincia;
        }
        if (!empty($localidad->nombre)) {
            return 'loc-' . $this->slugify((string) $localidad->nombre);
        }

        return 'unknown';
    }

    private function seasonBucket(): string
    {
        $month = (int) date('n');
        $quarter = (int) ceil($month / 3);

        return 'Q' . $quarter;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9áéíóúñü]+/u', '_', $text) ?? '';
        $text = trim($text, '_');

        return mb_substr($text, 0, 48);
    }

    /**
     * Bloque clínico acotado para prompts de generación de pack.
     */
    public function patientContextBlock(int $subjectPersonaId, string $profile = PatientAiContextBuilder::PROFILE_MOTIVOS): string
    {
        return (new PatientAiContextBuilder())->build($subjectPersonaId, $profile);
    }
}
