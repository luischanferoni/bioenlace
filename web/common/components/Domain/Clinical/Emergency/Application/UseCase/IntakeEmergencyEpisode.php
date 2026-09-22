<?php

namespace common\components\Domain\Clinical\Emergency\Application\UseCase;

use common\components\Domain\Clinical\Emergency\Domain\BoardState;
use common\components\Domain\Clinical\Emergency\Domain\BoardEventType;
use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyBoardService;
use common\components\Domain\Person\Identity\Application\Service\PersonaBusquedaAsistenteUiService;
use common\components\Domain\Person\Identity\Application\UseCase\CreatePendingIdentity;
use common\components\Domain\Person\Identity\Application\UseCase\ResolvePersonIdentity;
use common\components\Platform\Ui\Home\Service\HomePanelManifest;
use common\models\Clinical\Emergency\EmergencyEpisode;
use common\models\Organization\InfraestructuraCama;
use common\models\Organization\InfraestructuraPiso;
use common\models\Organization\InfraestructuraSala;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\Clinical\InpatientStay;
use Yii;
use yii\db\Query;

final class IntakeEmergencyEpisode
{
    /** @var EmergencyBoardService */
    private $circuito;

    public function __construct(?EmergencyBoardService $circuito = null)
    {
        $this->circuito = $circuito ?? new EmergencyBoardService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function ingresar(array $body, int $idEfector): array
    {
        $this->assertIdentidadDniPermitida($body);
        $pendiente = $this->usarIdentidadPendiente($body);
        $idPersona = $pendiente
            ? (int) (new CreatePendingIdentity())->crearPlaceholder()->id_persona
            : $this->resolverIdPersona($body);

        $ingresaEn = (string) ($body['ingresa_en'] ?? 'deambula');
        $ingresaCon = (string) ($body['ingresa_con'] ?? 'solo');
        if (!isset(EmergencyEpisode::INGRESO_EN[$ingresaEn])) {
            throw new \InvalidArgumentException('ingresa_en inválido.');
        }
        if (!isset(EmergencyEpisode::INGRESO_CON[$ingresaCon])) {
            throw new \InvalidArgumentException('ingresa_con inválido.');
        }

        $model = new EmergencyEpisode();
        $model->scenario = EmergencyEpisode::INGRESO_PACIENTE;
        $model->id_persona = $idPersona;
        $model->identidad_pendiente = $pendiente ? 1 : 0;
        $model->id_efector = $idEfector;
        $model->ingresa_en = $ingresaEn;
        $model->ingresa_con = $ingresaCon;
        $model->cobertura = isset($body['cobertura']) ? (string) $body['cobertura'] : null;
        $model->situacion_al_ingresar = isset($body['situacion_al_ingresar'])
            ? (string) $body['situacion_al_ingresar']
            : null;
        $model->datos_contacto_tel = (string) ($body['datos_contacto_tel'] ?? '');
        $model->fecha = date('d/m/Y');
        $model->hora = date('H:i');

        $pes = EmergencyEfectorAccess::resolvePesId(
            isset($body['id_profesional_efector_servicio'])
                ? (int) $body['id_profesional_efector_servicio']
                : null
        );
        if ($pes !== null) {
            $model->id_profesional_efector_servicio = $pes;
        }

        if (!$model->validate()) {
            throw new \InvalidArgumentException(
                'Datos de ingreso inválidos: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE)
            );
        }
        if (!$model->save(false)) {
            throw new \RuntimeException('No se pudo registrar el ingreso a guardia.');
        }

        $this->circuito->afterIngreso($model);

        return [
            'id' => (int) $model->id,
            'id_persona' => (int) $model->id_persona,
            'identidad_pendiente' => (int) $model->identidad_pendiente === 1,
            'id_efector' => (int) $model->id_efector,
            'circuito_estado' => BoardState::ESPERA_TRIAGE,
            'estado' => $model->estado,
            'ingreso_at' => $model->ingreso_at,
            'fecha' => $model->fecha,
            'hora' => $model->hora,
        ];
    }

    /**
     * Vincula DNI/Didit/`id_persona` a un episodio NN. No fusiona MPI.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function vincularIdentidad(int $guardiaId, array $body, int $idEfector): array
    {
        $this->assertIdentidadDniPermitida($body);
        $guardia = EmergencyEpisode::findOne(['id' => $guardiaId, 'id_efector' => $idEfector]);
        if ($guardia === null) {
            throw new \InvalidArgumentException('No se encontró el episodio de guardia.');
        }
        if ((int) ($guardia->identidad_pendiente ?? 0) !== 1) {
            throw new \InvalidArgumentException('Este episodio ya tiene identidad vinculada.');
        }

        $idPlaceholder = (int) $guardia->id_persona;
        $idDefinitiva = (new ResolvePersonIdentity())->resolver($body);
        if ($idDefinitiva <= 0) {
            throw new \InvalidArgumentException('No se pudo resolver la persona definitiva.');
        }

        $otra = EmergencyEpisode::find()
            ->where([
                'id_persona' => $idDefinitiva,
                'estado' => EmergencyEpisode::ESTADO_PENDIENTE,
                'id_efector' => $idEfector,
            ])
            ->andWhere(['<>', 'id', (int) $guardia->id])
            ->one();
        if ($otra !== null) {
            throw new \InvalidArgumentException('Esa persona ya se encuentra en la guardia de este efector.');
        }

        if ($idDefinitiva !== $idPlaceholder) {
            (new CreatePendingIdentity())->retargetEpisodioGuardia(
                (int) $guardia->id,
                $idPlaceholder,
                $idDefinitiva
            );
            $guardia->id_persona = $idDefinitiva;
        }

        $guardia->identidad_pendiente = 0;
        if (!$guardia->save(false)) {
            throw new \RuntimeException('No se pudo vincular la identidad al episodio.');
        }

        $this->circuito->recordEvent((int) $guardia->id, BoardEventType::IDENTIDAD_VINCULADA, null, [
            'id_persona' => $idDefinitiva,
            'id_persona_placeholder' => $idPlaceholder,
        ]);

        return [
            'id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'identidad_pendiente' => false,
        ];
    }

    /**
     * Paciente conocido (`id_persona`), Didit (`verification_id`) o DNI (código de barras / documento+sexo → RENAPER).
     * No acepta ficha tipeada (apellido/nombre/fecha): mismo núcleo que el alta staff.
     *
     * @param array<string, mixed> $body
     */
    private function resolverIdPersona(array $body): int
    {
        try {
            return (new ResolvePersonIdentity())->resolver($body);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(
                'Elegí un paciente de la búsqueda, identificá uno con DNI (documento y sexo, o código de barras), con foto del DNI (Didit) o como identidad pendiente (NN).',
                0,
                $e
            );
        }
    }

    /**
     * Clientes autorizados para identificar con DNI escaneado (`web` / `mobile`).
     *
     * @param array<string, mixed> $body
     */
    private function assertIdentidadDniPermitida(array $body): void
    {
        if ((new HomePanelManifest())->allowsEmergencyIngresoDniForCurrentClient()) {
            return;
        }
        if (self::looksLikeDiditIdentity($body) || self::looksLikeDniIdentity($body)) {
            throw new \InvalidArgumentException(
                'Para identificar con DNI usá la app Personal de Salud.'
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function usarIdentidadPendiente(array $body): bool
    {
        if (!self::looksLikePendingIdentity($body)) {
            return false;
        }
        if ((int) ($body['id_persona'] ?? 0) > 0) {
            return false;
        }
        if (self::looksLikeDiditIdentity($body) || self::looksLikeDniIdentity($body)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function looksLikePendingIdentity(array $body): bool
    {
        $v = $body['identidad_pendiente'] ?? false;

        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function looksLikeDiditIdentity(array $body): bool
    {
        return ResolvePersonIdentity::looksLikeDiditIdentity($body);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function looksLikeDniIdentity(array $body): bool
    {
        return ResolvePersonIdentity::looksLikeDniIdentity($body);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function opcionesIngresaEn(): array
    {
        return $this->mapConstOptions(EmergencyEpisode::INGRESO_EN);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function opcionesIngresaCon(): array
    {
        return $this->mapConstOptions(EmergencyEpisode::INGRESO_CON);
    }

    /**
     * Pacientes para admisión: búsqueda por q, o conocidos del efector si q vacío.
     * Excluye quienes ya están en guardia pendiente de este efector.
     *
     * @return list<array{value: string, label: string}>
     */
    public function buscarCandidatos(int $idEfector, ?string $q, int $limit = 30): array
    {
        if ($idEfector <= 0) {
            return [];
        }
        if ($limit < 1) {
            $limit = 30;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $pendientes = $this->idsPersonaGuardiaPendiente($idEfector);
        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $items = PersonaBusquedaAsistenteUiService::buscar($q, $limit);
            $out = [];
            $skipNn = $this->idsPlaceholder(array_map(static function ($it) {
                return (int) ($it['id'] ?? 0);
            }, $items));
            foreach ($items as $it) {
                $id = (int) ($it['id'] ?? 0);
                if ($id <= 0 || isset($pendientes[$id]) || isset($skipNn[$id])) {
                    continue;
                }
                $out[] = [
                    'value' => (string) $id,
                    'label' => (string) ($it['name'] ?? ''),
                ];
            }

            return $out;
        }

        $ids = $this->idsPersonaConocidasEnEfector($idEfector, $pendientes, $limit);
        if ($ids === []) {
            return [];
        }

        /** @var Persona[] $personas */
        $personas = Persona::find()
            ->where(['id_persona' => $ids])
            ->orderBy(['apellido' => SORT_ASC, 'nombre' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($personas as $persona) {
            if (CreatePendingIdentity::esPlaceholder($persona)) {
                continue;
            }
            $out[] = [
                'value' => (string) (int) $persona->id_persona,
                'label' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
            ];
        }

        return $out;
    }

    /**
     * @param array<int|string, string> $map
     * @return list<array{value: string, label: string}>
     */
    private function mapConstOptions(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return array<int, true>
     */
    private function idsPlaceholder(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $rows = Persona::find()
            ->select(['id_persona', 'documento', 'acredita_identidad'])
            ->where(['id_persona' => $ids])
            ->asArray()
            ->all();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id_persona'] ?? 0);
            $doc = trim((string) ($row['documento'] ?? ''));
            if ($id > 0 && (int) ($row['acredita_identidad'] ?? 0) === 0 && $doc === '') {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /**
     * @return array<int, true>
     */
    private function idsPersonaGuardiaPendiente(int $idEfector): array
    {
        $ids = EmergencyEpisode::find()
            ->select('id_persona')
            ->andWhere([
                'id_efector' => $idEfector,
                'estado' => EmergencyEpisode::ESTADO_PENDIENTE,
            ])
            ->column();
        $set = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $set[$id] = true;
            }
        }

        return $set;
    }

    /**
     * @param array<int, true> $excluir
     * @return list<int>
     */
    private function idsPersonaConocidasEnEfector(int $idEfector, array $excluir, int $limit): array
    {
        $guardiaIds = EmergencyEpisode::find()
            ->select('id_persona')
            ->andWhere(['id_efector' => $idEfector])
            ->column();
        $turnoIds = (new Query())
            ->select('id_persona')
            ->from(Turno::tableName())
            ->where(['id_efector' => $idEfector])
            ->column();
        $internacionIds = (new Query())
            ->select('i.id_persona')
            ->from(['i' => InpatientStay::tableName()])
            ->innerJoin(['cama' => InfraestructuraCama::tableName()], 'cama.id = i.id_cama')
            ->innerJoin(['sala' => InfraestructuraSala::tableName()], 'sala.id = cama.id_sala')
            ->innerJoin(['piso' => InfraestructuraPiso::tableName()], 'piso.id = sala.id_piso')
            ->where(['piso.id_efector' => $idEfector])
            ->column();

        $out = [];
        foreach (array_merge($guardiaIds, $turnoIds, $internacionIds) as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($excluir[$id]) || isset($out[$id])) {
                continue;
            }
            $out[$id] = $id;
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_values($out);
    }
}
