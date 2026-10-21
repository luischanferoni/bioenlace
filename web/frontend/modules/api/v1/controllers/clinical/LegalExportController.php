<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\LegalExport\Application\Authorization\LegalExportAccessService;
use common\components\Domain\Clinical\LegalExport\Application\UseCase\RequestLegalExport;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\models\Clinical\LegalExportAudit;
use common\models\Clinical\LegalExportRequest;
use common\models\Person\Persona;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\Response;

/**
 * Expediente legal amplio (solo staff, generación async).
 *
 * Id público RBAC/URL: `legal-record-export` (alias en Module controllerMap).
 *
 * POST /api/v1/clinical/legal-record-export/solicitar  body: id_persona, id_efector?
 * GET  /api/v1/clinical/legal-record-export/listar-mis-solicitudes
 * GET  /api/v1/clinical/legal-record-export/ver-estado?request_id=
 * GET  /api/v1/clinical/legal-record-export/descargar?request_id=
 */
class LegalExportController extends BaseController
{
    private RequestLegalExport $requests;
    private LegalExportAccessService $access;

    public function init()
    {
        parent::init();
        $this->requests = new RequestLegalExport();
        $this->access = new LegalExportAccessService();
    }

    public function actionSolicitar(): array
    {
        $body = Yii::$app->request->post();
        $idPersona = (int) ($body['id_persona'] ?? $body['subject_persona_id'] ?? 0);
        $idEfector = (int) ($body['id_efector'] ?? 0);

        if ($idPersona <= 0) {
            throw new ApiException('request_invalid', ['detail' => 'Se requiere id_persona del paciente.']);
        }

        try {
            $row = $this->requests->createRequest($idPersona, $idEfector > 0 ? $idEfector : null);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException('request_invalid', ['detail' => $e->getMessage()], [], $e);
        }

        return $this->ok(
            $this->requests->serializeRequest($row),
            [
                'message' => $row->estado === LegalExportRequest::ESTADO_PENDIENTE
                    ? 'Solicitud encolada. Recibirás aviso cuando el PDF esté listo.'
                    : 'Ya existe una solicitud en curso.',
            ]
        );
    }

    public function actionListarMisSolicitudes(): array
    {
        $limit = (int) Yii::$app->request->get('limit', 30);
        $offset = (int) Yii::$app->request->get('offset', 0);

        return $this->ok([
            'items' => $this->requests->listForCurrentUser($limit, $offset),
        ], ['message' => 'Solicitudes de expediente legal']);
    }

    public function actionVerEstado(): array
    {
        $requestId = (int) Yii::$app->request->get('request_id', 0);
        if ($requestId <= 0) {
            throw new ApiException('request_invalid', ['detail' => 'Se requiere request_id.']);
        }

        $row = $this->requests->getForCurrentUser($requestId);
        if ($row === null) {
            throw new ApiException('not_found_detail', ['detail' => 'Solicitud no encontrada.']);
        }

        $data = $this->requests->serializeRequest($row);
        $persona = Persona::findOne(['id_persona' => (int) $row->subject_persona_id]);
        if ($persona !== null) {
            $data['subjectNombre'] = method_exists($persona, 'getNombreCompleto')
                ? $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : trim($persona->nombre . ' ' . $persona->apellido);
            $data['subjectDocumento'] = $persona->documento;
        }

        return $this->ok($data, ['message' => 'Estado de solicitud']);
    }

    /**
     * Descarga PDF cuando estado = LISTO (solo solicitante).
     */
    public function actionDescargar()
    {
        $requestId = (int) Yii::$app->request->get('request_id', 0);
        if ($requestId <= 0) {
            throw new ApiException('request_invalid', ['detail' => 'request_id requerido.']);
        }

        $row = $this->requests->getForCurrentUser($requestId);
        if ($row === null) {
            throw new ApiException('not_found_detail', ['detail' => 'Solicitud no encontrada.']);
        }

        try {
            $this->access->assertUserCanDownload($row);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException('not_found_detail', ['detail' => $e->getMessage()], [], $e);
        }

        if ($row->estado !== LegalExportRequest::ESTADO_LISTO) {
            throw new ApiException('not_found_detail', ['detail' => 'El expediente aún no está listo para descarga.']);
        }

        $path = (string) ($row->file_path ?? '');
        if ($path === '' || !is_file($path)) {
            throw new ApiException('not_found_detail', ['detail' => 'Archivo no disponible.']);
        }

        $now = date('Y-m-d H:i:s');
        if ($row->downloaded_at === null) {
            $row->downloaded_at = $now;
            $row->downloaded_by_user_id = (int) Yii::$app->user->id;
            $row->updated_at = $now;
            $row->save(false);
        }

        LegalExportAudit::registrar(
            (int) $row->id,
            LegalExportAudit::EVENT_DESCARGADO,
            ['file_size' => $row->file_size]
        );

        $persona = Persona::findOne(['id_persona' => (int) $row->subject_persona_id]);
        $slug = $persona && $persona->documento
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $persona->documento)
            : (string) $row->subject_persona_id;
        $filename = 'expediente-legal-' . $slug . '-' . $requestId . '.pdf';

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->content = file_get_contents($path);

        return $response;
    }
}
