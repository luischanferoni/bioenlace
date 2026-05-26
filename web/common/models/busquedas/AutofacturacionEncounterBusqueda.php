<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use common\models\sumar\Autofacturacion;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Listados de autofacturación SUMAR sobre {@see Encounter} (sin `consultas`).
 */
class AutofacturacionEncounterBusqueda extends Model
{
    public $conAutofacturacion = false;
    public $listadoConsultasEnviadas = false;
    public $fecha_desde = null;
    public $fecha_hasta = null;
    public $id_efector;

    public function rules(): array
    {
        return [
            [['id_efector'], 'integer'],
            [['fecha_desde', 'fecha_hasta'], 'safe'],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $fk = Autofacturacion::legacyConsultaFkAttribute();
        $afTable = Autofacturacion::tableName();

        $query = Encounter::findActive()
            ->alias('enc')
            ->with([
                'subject',
                'conditions',
                'serviceRequests',
                'autofacturacion',
                'profesionalEfectorServicio',
                'profesionalPes',
                'appointment',
            ])
            ->andWhere(['enc.efector_id' => (int) $this->id_efector])
            ->orderBy(['enc.created_at' => SORT_DESC]);

        $this->load($params);

        if ($this->conAutofacturacion) {
            $query->innerJoin(
                ['af' => $afTable],
                "af.{$fk} = enc.id"
            );
            if ($this->listadoConsultasEnviadas) {
                $query->andWhere(['IS NOT', 'af.respuesta_sumar', null]);
                if ($this->fecha_desde) {
                    $query->andWhere(['>=', 'af.fecha_envio', $this->fecha_desde]);
                }
                if ($this->fecha_hasta) {
                    $query->andWhere(['<=', 'af.fecha_envio', $this->fecha_hasta]);
                }
            } else {
                $query->andWhere(['af.fecha_envio' => null])
                    ->andWhere(['IS NOT', 'af.respuesta_sumar', null]);
            }
        } else {
            $query->leftJoin(['af' => $afTable], "af.{$fk} = enc.id")
                ->andWhere(['af.id_sumar_autofacturacion' => null]);
        }

        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }
}
