<?php

namespace common\models\Clinical;

use Yii;
use yii\db\ActiveRecord;

/**
 * Wizard por servicio / encounter class — tabla `encounter_definition`.
 *
 * @property int $id
 * @property int $service_id
 * @property string $encounter_class
 * @property string $workflow_json
 */
class EncounterDefinition extends ActiveRecord
{
    use ClinicalRecordTrait;

    /** Etiquetas UI por código FHIR encounter class. */
    public const ENCOUNTER_CLASS = [
        'IMP' => 'Internación',
        'AMB' => 'Ambulatoria',
        'OBSENC' => 'Observación',
        'EMER' => 'Emergencia',
        'VR' => 'Virtual',
        'HH' => 'Visita Domiciliaria',
    ];

    /**
     * Clases ofrecidas al staff al elegir área (wizard / catálogo de sesión).
     * OBSENC y HH quedan en el catálogo FHIR pero no en la UI de producto activa.
     *
     * @var list<string>
     */
    public const ENCOUNTER_CLASS_SESSION_SELECTABLE = ['AMB', 'EMER', 'IMP', 'VR'];

    /**
     * @return array<string, string> code => label
     */
    public static function sessionSelectableClasses(): array
    {
        $out = [];
        foreach (self::ENCOUNTER_CLASS_SESSION_SELECTABLE as $code) {
            if (isset(self::ENCOUNTER_CLASS[$code])) {
                $out[$code] = self::ENCOUNTER_CLASS[$code];
            }
        }

        return $out;
    }

    public static function tableName(): string
    {
        return 'encounter_definition';
    }

    public static function find(): EncounterDefinitionQuery
    {
        return new EncounterDefinitionQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['service_id', 'encounter_class', 'workflow_json'], 'required'],
            [['service_id'], 'integer'],
            [['encounter_class'], 'string', 'max' => 10],
            [['workflow_json'], 'string'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    public function getServicio(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\common\models\Servicio::class, ['id_servicio' => 'service_id']);
    }

    /**
     * Categorías crudas del workflow (oferta + clase). Overlay de actor/CarePlan:
     * {@see \common\components\Domain\Clinical\Workflow\EncounterCaptureCategoryResolver}.
     *
     * @return list<array{titulo: string, modelo: string, requerido: bool, sugerido: bool, campos_requeridos: array}>
     */
    public static function getCategoriasParaPrompt(self $definition): array
    {
        $jsonPasos = json_decode((string) $definition->workflow_json);
        $categorias = [];
        if (!is_object($jsonPasos) || !isset($jsonPasos->conf) || !is_iterable($jsonPasos->conf)) {
            return [];
        }

        foreach ($jsonPasos->conf as $output) {
            if (!is_object($output) && !is_array($output)) {
                continue;
            }
            $titulo = is_object($output) ? (string) ($output->titulo ?? '') : (string) ($output['titulo'] ?? '');
            $modelo = is_object($output) ? ($output->relacion ?? '') : ($output['relacion'] ?? '');
            $requerido = is_object($output)
                ? (isset($output->requerido) ? (bool) $output->requerido : false)
                : (isset($output['requerido']) ? (bool) $output['requerido'] : false);
            $sugerido = is_object($output)
                ? (isset($output->sugerido) ? (bool) $output->sugerido : false)
                : (isset($output['sugerido']) ? (bool) $output['sugerido'] : false);
            $categorias[] = [
                'titulo' => $titulo,
                'modelo' => is_array($modelo) ? '' : (string) $modelo,
                'requerido' => $requerido,
                'sugerido' => $sugerido,
                'campos_requeridos' => self::camposRequeridosDelModelo($modelo),
            ];
        }

        return $categorias;
    }

    /**
     * @param string|array $nombreModelo
     * @return array
     */
    public static function camposRequeridosDelModelo($nombreModelo): array
    {
        if (is_array($nombreModelo)) {
            return [];
        }
        try {
            $claseModelo = "\\common\\models\\{$nombreModelo}";
            if (!class_exists($claseModelo)) {
                return [];
            }
            $modelo = new $claseModelo();
            if (method_exists($modelo, 'requeridosPrompt')) {
                return $modelo->requeridosPrompt();
            }
        } catch (\Throwable $e) {
            Yii::error("Error campos requeridos {$nombreModelo}: " . $e->getMessage());
        }

        return [];
    }
}
