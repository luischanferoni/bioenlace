<?php

namespace common\models\Clinical;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * Wizard por servicio / encounter class — tabla `encounter_definition`.
 *
 * @property int $id
 * @property int $service_id
 * @property string $encounter_class
 * @property string $workflow_json
 * @property string|null $pasos_legacy
 */
class EncounterDefinition extends ActiveRecord
{
    use ClinicalRecordTrait;

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
            [['workflow_json', 'pasos_legacy'], 'string'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    /** Compat lectura legacy {@see \common\models\ConsultasConfiguracion}. */
    public function getPasos_json(): string
    {
        return (string) $this->workflow_json;
    }

    public function setPasos_json(string $value): void
    {
        $this->workflow_json = $value;
    }

    public function getId_servicio(): int
    {
        return (int) $this->service_id;
    }

    public function setId_servicio($value): void
    {
        $this->service_id = (int) $value;
    }

    public function getServicio(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\common\models\Servicio::class, ['id_servicio' => 'service_id']);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?int}
     */
    public static function getUrlPorServicioYEncounterClass($idServicio, $encounterClass, $paso = null): array
    {
        $configuracion = static::find()
            ->where(['service_id' => $idServicio, 'encounter_class' => $encounterClass])
            ->andWhere('deleted_at is null')
            ->one();

        if (!$configuracion) {
            Yii::error('Servicio sin encounter_definition, servicio: ' . $idServicio . ' encounterClass: ' . $encounterClass);

            return [null, null, null, null];
        }

        $jsonPasos = json_decode($configuracion->workflow_json);
        $arrayPasos = [];
        foreach ($jsonPasos->conf as $output) {
            $arrayPasos[] = $output->url;
        }

        if ($paso !== null) {
            $urlAnterior = isset($arrayPasos[$paso - 1]) ? Url::toRoute(trim($arrayPasos[$paso - 1])) : null;
            $urlActual = isset($arrayPasos[$paso]) ? Url::toRoute(trim($arrayPasos[$paso])) : null;
            $urlSiguiente = isset($arrayPasos[$paso + 1]) ? Url::toRoute(trim($arrayPasos[$paso + 1])) : null;
        } else {
            $urlAnterior = null;
            $urlActual = Url::toRoute(trim($arrayPasos[0]));
            $urlSiguiente = isset($arrayPasos[1]) ? Url::toRoute(trim($arrayPasos[1])) : null;
        }

        return [$urlAnterior, $urlActual, $urlSiguiente, $configuracion->id];
    }

    public static function getCategoriasParaPrompt(self $configuracion): array
    {
        $jsonPasos = json_decode($configuracion->workflow_json);
        $categorias = [];

        foreach ($jsonPasos->conf as $output) {
            $categorias[] = [
                'titulo' => $output->titulo,
                'modelo' => $output->relacion,
                'requerido' => isset($output->requerido) ? (bool) $output->requerido : false,
                'campos_requeridos' => self::obtenerCamposRequeridosDelModelo($output->relacion),
            ];
        }

        return $categorias;
    }

    /**
     * @param string|array $nombreModelo
     * @return array
     */
    private static function obtenerCamposRequeridosDelModelo($nombreModelo): array
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
