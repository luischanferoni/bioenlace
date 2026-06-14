<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Versión de agenda PES con vigencia desde una fecha.
 *
 * @property int $id
 * @property int $id_profesional_efector_servicio
 * @property int $id_efector
 * @property string $vigente_desde
 * @property int $intervalo_minutos
 * @property string $formas_atencion
 * @property bool $acepta_consultas_online
 * @property string|null $lunes_2
 * @property string|null $martes_2
 * @property string|null $miercoles_2
 * @property string|null $jueves_2
 * @property string|null $viernes_2
 * @property string|null $sabado_2
 * @property string|null $domingo_2
 * @property string $created_at
 * @property int|null $created_by
 */
class ProfesionalEfectorServicioAgendaVersion extends ActiveRecord
{
    use \common\traits\AgendaHorarioSlotsTrait;

    public static function tableName()
    {
        return 'profesional_efector_servicio_agenda_version';
    }

    public function rules()
    {
        return [
            [['id_profesional_efector_servicio', 'id_efector', 'vigente_desde', 'intervalo_minutos', 'formas_atencion'], 'required'],
            [['id_profesional_efector_servicio', 'id_efector', 'intervalo_minutos', 'created_by'], 'integer'],
            [['acepta_consultas_online'], 'boolean'],
            [['vigente_desde', 'created_at'], 'safe'],
            [['formas_atencion'], 'string', 'max' => 32],
            [['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'safe'],
            ['intervalo_minutos', 'validateIntervalo'],
        ];
    }

    public function validateIntervalo(): void
    {
        if (!\common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos::isAllowed((int) $this->intervalo_minutos)) {
            $this->addError('intervalo_minutos', 'Intervalo no permitido. Use 15, 20, 30, 45 o 60 minutos.');
        }
    }

    public function getIntervaloMinutosEfectivo(): int
    {
        return \common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos::normalize($this->intervalo_minutos);
    }

    /**
     * Versión programada exactamente para una fecha de vigencia (clave única PES + vigente_desde).
     */
    public static function findPorPesYVigenteDesde(int $idPes, string $vigenteDesdeYmd): ?self
    {
        if ($idPes <= 0 || $vigenteDesdeYmd === '') {
            return null;
        }

        /** @var self|null $row */
        $row = static::findOne([
            'id_profesional_efector_servicio' => $idPes,
            'vigente_desde' => $vigenteDesdeYmd,
        ]);

        return $row;
    }

    /**
     * Versión vigente para una fecha (mayor vigente_desde <= fecha).
     */
    public static function findVigenteParaPesEnFecha(int $idPes, string $fechaYmd): ?self
    {
        if ($idPes <= 0 || $fechaYmd === '') {
            return null;
        }
        /** @var self|null $row */
        $row = static::find()
            ->where(['id_profesional_efector_servicio' => $idPes])
            ->andWhere(['<=', 'vigente_desde', $fechaYmd])
            ->orderBy(['vigente_desde' => SORT_DESC, 'id' => SORT_DESC])
            ->one();

        return $row;
    }

    /**
     * @return self[]
     */
    public static function findAllPorPes(int $idPes): array
    {
        return static::find()
            ->where(['id_profesional_efector_servicio' => $idPes])
            ->orderBy(['vigente_desde' => SORT_ASC])
            ->all();
    }

    public function getAsignacion()
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }
}
