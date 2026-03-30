<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use common\models\SolicitudRrhh;
use common\models\SolicitudRrhhEvento;
use common\models\EfectorTurnosConfig;
use common\models\RrhhEfector;

/**
 * Solicitudes / pedidos entre profesionales (según modo_comunicacion_medicos del efector).
 */
class SolicitudRrhhController extends BaseController
{
    public function actionListar()
    {
        $idEfector = Yii::$app->user->getIdEfector();
        $idRrhh = Yii::$app->user->getIdRecursoHumano();
        if (!$idEfector || !$idRrhh) {
            throw new BadRequestHttpException('Efector o RRHH no determinado');
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector($idEfector);
        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DESHABILITADO) {
            return ['success' => true, 'solicitudes' => [], 'message' => 'Módulo deshabilitado'];
        }

        $q = SolicitudRrhh::find()->where(['id_efector' => $idEfector]);
        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DIRECTO) {
            $q->andWhere([
                'or',
                ['id_solicitante_rr_hh' => $idRrhh],
                ['id_destinatario_rr_hh' => $idRrhh],
            ]);
        }
        $list = $q->orderBy(['id' => SORT_DESC])->limit(100)->all();
        $out = [];
        foreach ($list as $s) {
            $out[] = [
                'id' => $s->id,
                'estado' => $s->estado,
                'tipo' => $s->tipo,
                'mensaje' => $s->mensaje,
                'id_solicitante_rr_hh' => $s->id_solicitante_rr_hh,
                'id_destinatario_rr_hh' => $s->id_destinatario_rr_hh,
                'created_at' => $s->created_at,
            ];
        }
        return ['success' => true, 'solicitudes' => $out];
    }

    public function actionCrear()
    {
        $idEfector = Yii::$app->user->getIdEfector();
        $idRrhh = Yii::$app->user->getIdRecursoHumano();
        if (!$idEfector || !$idRrhh) {
            throw new BadRequestHttpException('Efector o RRHH no determinado');
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector($idEfector);
        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DESHABILITADO) {
            throw new ForbiddenHttpException('Comunicación entre médicos deshabilitada');
        }

        $mensaje = Yii::$app->request->post('mensaje');
        if ($mensaje === null || $mensaje === '') {
            throw new BadRequestHttpException('mensaje requerido');
        }
        $dest = Yii::$app->request->post('id_destinatario_rr_hh');

        $s = new SolicitudRrhh();
        $s->id_efector = $idEfector;
        $s->id_solicitante_rr_hh = $idRrhh;
        $s->mensaje = $mensaje;
        $s->tipo = Yii::$app->request->post('tipo', 'general');

        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DIRECTO) {
            if (!$dest) {
                throw new BadRequestHttpException('id_destinatario_rr_hh requerido en modo directo');
            }
            $s->id_destinatario_rr_hh = (int) $dest;
        } elseif ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_INTERMEDIARIO) {
            $s->id_intermediario_user = Yii::$app->user->id;
        } elseif ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_AUTO_ASIGNACION) {
            $candidato = $this->pickAutoDestinatario($idEfector, $idRrhh);
            $s->id_destinatario_rr_hh = $candidato;
        }

        if (!$s->save()) {
            throw new BadRequestHttpException(implode(', ', $s->getFirstErrors()));
        }
        $ev = new SolicitudRrhhEvento();
        $ev->id_solicitud = $s->id;
        $ev->id_user = Yii::$app->user->id;
        $ev->tipo = 'creada';
        $ev->save(false);

        return ['success' => true, 'id' => $s->id];
    }

    /**
     * Heurística simple: primer otro RRHH del mismo efector.
     */
    protected function pickAutoDestinatario($idEfector, $excludeRrhh)
    {
        $r = RrhhEfector::find()->where(['id_efector' => $idEfector])->andWhere(['<>', 'id_rr_hh', $excludeRrhh])->one();
        return $r ? (int) $r->id_rr_hh : null;
    }
}
