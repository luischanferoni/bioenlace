<?php

use yii\db\Migration;
use yii\db\Query;

require_once __DIR__ . '/ServiciosCatalogoFkPurgeTrait.php';

/**
 * Segunda pasada de higiene: subespecialidades, patologías y programas
 * modelados como filas de `servicios` → remapar a oferta institucional y DELETE.
 *
 * No son áreas del centro: córnea/retina = tipología dentro de oftalmología;
 * diabetes/VIH = programa o condición, no HealthcareService.
 */
class m260731_170000_servicios_subespecialidades_programas extends Migration
{
    use ServiciosCatalogoFkPurgeTrait;

    private const SNOMED = 'http://snomed.info/sct';
    private const RADIOLOGY = '394914008';

    /**
     * origen → candidatos de contenedor (primer existente gana).
     *
     * @var array<string, list<string>>
     */
    private const MERGE_INTO = [
        // Subespecialidades oftalmológicas
        'RETINA' => ['OFTALMOLOGIA'],
        'CORNEA' => ['OFTALMOLOGIA'],
        'OCULOPLASTIA' => ['OFTALMOLOGIA'],
        'NEUROOFTALMOLOGIA' => ['OFTALMOLOGIA'],
        'SEGMENTO ANTERIOR' => ['OFTALMOLOGIA'],
        'BAJA VISION' => ['OFTALMOLOGIA'],
        // Patología / programa / modalidad (no oferta institucional)
        'DIABETES' => ['ENDOCRINOLOGIA', 'MED CLINICA'],
        'VIH SIDA' => ['MED CLINICA', 'APS'],
        'TELEOBSTETRICIA' => ['OBSTETRICIA'],
        'SALUD COMUNITARIA - MATERNIDAD' => ['OBSTETRICIA', 'APS'],
        'PLAZA SALUDABLE' => ['APS', 'MED FAMILIAR', 'MED GENERAL'],
        'EDUCACION SANITARIA' => ['APS', 'ENFERMERIA'],
        'DERIVACION APS' => ['APS'],
        'ALERGISTA' => ['INMUNOLOGIA CLINICA Y ALERGOLOGIA', 'MED CLINICA'],
        'GUARDIA DE ENFERMERIA' => ['GUARDIA', 'ENFERMERIA'],
        'SOCIOLOGIA' => ['TRABAJO SOCIAL'],
        'ACOMPAÑAMIENTO TERAPEUTICO' => ['PSICOLOGIA'],
        // Alias histórico de la misma oferta
        'FISIOTERAPIA' => ['KINESIOLOGIA'],
    ];

    /** Contenedores imaging equivalentes: se conserva el primero existente. */
    private const IMAGING_NAMES = [
        'DIAGNOSTICO POR IMAGENES',
        'RADIOLOGIA',
        'BIOIMAGEN',
    ];

    public function safeUp(): void
    {
        $servicios = '{{%servicios}}';
        if ($this->db->schema->getTableSchema($servicios, true) === null) {
            echo "    > servicios no existe; omitida.\n";

            return;
        }

        $byName = $this->indexByNormalizedName(
            (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db)
        );

        $this->mergeImagingContainers($servicios, $byName);

        $byName = $this->indexByNormalizedName(
            (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db)
        );

        foreach (self::MERGE_INTO as $fromName => $targets) {
            $fromKey = $this->normalizeName($fromName);
            if (!isset($byName[$fromKey])) {
                continue;
            }
            $from = $byName[$fromKey];
            $to = $this->firstExistingId($byName, $targets);
            if ($to !== null && $to !== $from) {
                $this->remapAllServicioFks($from, $to);
                $this->delete($servicios, ['id_servicio' => $from]);
                echo "    > {$fromName} (id={$from}) → contenedor id={$to}\n";
            } elseif ($to === null) {
                $this->purgeServicioFks($from);
                $this->delete($servicios, ['id_servicio' => $from]);
                echo "    > {$fromName} (id={$from}) sin contenedor; FKs purgadas y eliminado\n";
            }
            unset($byName[$fromKey]);
        }
    }

    public function safeDown(): void
    {
        // Irreversible.
    }

    /**
     * @param array<string, int> $byName
     */
    private function mergeImagingContainers(string $servicios, array &$byName): void
    {
        $ids = [];
        foreach (self::IMAGING_NAMES as $name) {
            $key = $this->normalizeName($name);
            if (isset($byName[$key])) {
                $ids[$name] = $byName[$key];
            }
        }
        if (count($ids) < 2) {
            return;
        }

        $keepName = array_key_first($ids);
        $keepId = $ids[$keepName];
        unset($ids[$keepName]);

        $this->update(
            $servicios,
            [
                'tipo' => 'diagnostico',
                'specialty_code' => self::RADIOLOGY,
                'specialty_system' => self::SNOMED,
            ],
            ['id_servicio' => $keepId]
        );

        foreach ($ids as $dupName => $dupId) {
            $this->remapAllServicioFks($dupId, $keepId);
            $this->delete($servicios, ['id_servicio' => $dupId]);
            echo "    > imaging {$dupName} (id={$dupId}) → {$keepName} (id={$keepId})\n";
        }
    }
}
