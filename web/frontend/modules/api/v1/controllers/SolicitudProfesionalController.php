<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use common\models\SolicitudRrhh;
use common\models\SolicitudRrhhEvento;
use common\models\EfectorTurnosConfig;
use common\models\ProfesionalEfectorServicio;
use yii\db\Query;

/**
 * Solicitudes entre profesionales del efector (`solicitud_rrhh` en BD; ids columnas = PES).
 */
class SolicitudProfesionalController extends BaseController
{
    private function requireStaffContextIdFromSession(): int
    {
        return (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
    }

    public function actionListar()
    {
        $idEfector = Yii::$app->user->getIdEfector();
        $idPes = $this->requireStaffContextIdFromSession();
        if (!$idEfector || !$idPes) {
            throw new BadRequestHttpException('Efector o contexto profesional no determinado');
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector($idEfector);
        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DESHABILITADO) {
            return ['success' => true, 'solicitudes' => [], 'message' => 'Módulo deshabilitado'];
        }

        $q = SolicitudRrhh::find()->where(['id_efector' => $idEfector]);
        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DIRECTO) {
            $q->andWhere([
                'or',
                ['id_solicitante_rr_hh' => $idPes],
                ['id_destinatario_rr_hh' => $idPes],
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
                'id_solicitante_profesional_efector_servicio' => $s->id_solicitante_rr_hh,
                'id_destinatario_profesional_efector_servicio' => $s->id_destinatario_rr_hh,
                'created_at' => $s->created_at,
            ];
        }

        return ['success' => true, 'solicitudes' => $out];
    }

    public function actionCrear()
    {
        $idEfector = Yii::$app->user->getIdEfector();
        $idPes = $this->requireStaffContextIdFromSession();
        if (!$idEfector || !$idPes) {
            throw new BadRequestHttpException('Efector o contexto profesional no determinado');
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector($idEfector);
        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DESHABILITADO) {
            throw new ForbiddenHttpException('Comunicación entre médicos deshabilitada');
        }

        $mensaje = Yii::$app->request->post('mensaje');
        if ($mensaje === null || $mensaje === '') {
            throw new BadRequestHttpException('mensaje requerido');
        }
        $dest = Yii::$app->request->post('id_destinatario_profesional_efector_servicio');

        $s = new SolicitudRrhh();
        $s->id_efector = $idEfector;
        $s->id_solicitante_rr_hh = $idPes;
        $s->mensaje = $mensaje;
        $s->tipo = Yii::$app->request->post('tipo', 'general');

        if ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_DIRECTO) {
            if (!$dest) {
                throw new BadRequestHttpException(
                    'id_destinatario_profesional_efector_servicio requerido en modo directo'
                );
            }
            $s->id_destinatario_rr_hh = (int) $dest;
        } elseif ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_INTERMEDIARIO) {
            $s->id_intermediario_user = Yii::$app->user->id;
        } elseif ($cfg->modo_comunicacion_medicos === EfectorTurnosConfig::MODO_MEDICOS_AUTO_ASIGNACION) {
            $candidato = $this->pickAutoDestinatario($idEfector, $idPes);
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
     * Heurística simple: primer otro profesional (PES) del mismo efector.
     */
    protected function pickAutoDestinatario($idEfector, $excludeStaffContextId)
    {
        $excludeStaffContextId = (int) $excludeStaffContextId;
        $excludePid = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($excludeStaffContextId);
        if ($excludePid === null || $excludePid <= 0) {
            $pesEx = ProfesionalEfectorServicio::findOne($excludeStaffContextId);
            $excludePid = $pesEx !== null ? (int) $pesEx->id_persona : 0;
        }
        $q = (new Query())
            ->select(['pes.id'])
            ->from(['pes' => ProfesionalEfectorServicio::tableName()])
            ->where([
                'pes.id_efector' => (int) $idEfector,
                'pes.deleted_at' => null,
            ])
            ->orderBy(['pes.id' => SORT_ASC])
            ->limit(1);
        if ($excludePid > 0) {
            $q->andWhere(['<>', 'pes.id_persona', $excludePid]);
        }

        $id = $q->scalar();

        return $id !== false && $id !== null ? (int) $id : null;
    }
}
