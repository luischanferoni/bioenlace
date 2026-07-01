<?php

namespace common\components\Domain\Person\Service;

use common\models\Person\Persona;
use common\models\Person\PersonaPacienteContexto;
use common\models\Provincia;
use Yii;

/**
 * Contexto operativo persistente del paciente (sector + provincia de contexto).
 */
final class PacienteContextoService
{
    public const MENSAJE_VERIFICANDO = 'Estamos verificando tu domicilio, te avisaremos cuando esté listo.';

    public const MENSAJE_FALLIDO_MANUAL = 'No pudimos verificar tu domicilio. Podés establecer manualmente tu provincia de contexto (no modifica tu domicilio registrado).';

    public function getOrCreate(int $idPersona): PersonaPacienteContexto
    {
        $row = PersonaPacienteContexto::findOne($idPersona);
        if ($row instanceof PersonaPacienteContexto) {
            return $row;
        }

        $now = date('Y-m-d H:i:s');
        $row = new PersonaPacienteContexto();
        $row->id_persona = $idPersona;
        $row->sector_salud = PersonaPacienteContexto::SECTOR_SALUD_PUBLICO;
        $row->domicilio_estado = PersonaPacienteContexto::DOMICILIO_PENDIENTE;
        $row->domicilio_verificacion_inicio = $now;
        $row->domicilio_intentos = 0;
        $row->provincia_contexto_manual = false;
        $row->created_at = $now;
        $row->updated_at = $now;
        $row->save(false);

        return $row;
    }

    public function inicializarTrasRegistro(Persona $persona): PersonaPacienteContexto
    {
        return $this->getOrCreate((int) $persona->id_persona);
    }

    /**
     * @return array<string, mixed>
     */
    public function export(PersonaPacienteContexto $ctx): array
    {
        $provincia = null;
        if ($ctx->id_provincia_contexto) {
            $p = Provincia::findOne((int) $ctx->id_provincia_contexto);
            if ($p instanceof Provincia) {
                $provincia = [
                    'id_provincia' => (int) $p->id_provincia,
                    'nombre' => (string) $p->nombre,
                    'cod_indec' => (string) $p->cod_indec,
                ];
            }
        }

        return [
            'sector_salud' => strtolower((string) $ctx->sector_salud),
            'id_provincia_contexto' => $ctx->id_provincia_contexto ? (int) $ctx->id_provincia_contexto : null,
            'provincia' => $provincia,
            'domicilio_estado' => strtolower((string) $ctx->domicilio_estado),
            'provincia_contexto_manual' => (bool) $ctx->provincia_contexto_manual,
            'puede_operar' => $ctx->puedeOperarApp(),
            'banner' => $this->buildBanner($ctx),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildBanner(PersonaPacienteContexto $ctx): ?array
    {
        if ($ctx->domicilio_estado === PersonaPacienteContexto::DOMICILIO_PENDIENTE) {
            return [
                'kind' => 'domicilio_pendiente',
                'message' => self::MENSAJE_VERIFICANDO,
                'severity' => 'info',
            ];
        }
        if ($ctx->domicilio_estado === PersonaPacienteContexto::DOMICILIO_REQUIERE_PROVINCIA_MANUAL
            && !$ctx->tieneProvinciaOperativa()) {
            return [
                'kind' => 'domicilio_requiere_provincia',
                'message' => self::MENSAJE_FALLIDO_MANUAL,
                'severity' => 'warning',
                'action' => 'configurar_provincia_contexto',
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function actualizar(int $idPersona, array $input): array
    {
        $ctx = $this->getOrCreate($idPersona);
        $now = date('Y-m-d H:i:s');

        if (isset($input['sector_salud'])) {
            $sector = strtoupper(trim((string) $input['sector_salud']));
            if (!in_array($sector, PersonaPacienteContexto::sectorSaludValues(), true)) {
                throw new \InvalidArgumentException('sector_salud inválido.');
            }
            $ctx->sector_salud = $sector;
        }

        if (isset($input['id_provincia_contexto'])) {
            $idProvincia = (int) $input['id_provincia_contexto'];
            if ($idProvincia <= 0) {
                throw new \InvalidArgumentException('id_provincia_contexto inválido.');
            }
            if (Provincia::findOne($idProvincia) === null) {
                throw new \InvalidArgumentException('Provincia inexistente.');
            }
            // La provincia de contexto no modifica el domicilio MPI; el paciente puede
            // elegirla desde Configuración mientras la verificación sigue en curso (CTX-13).
            // Si MPI confirma después, marcarDomicilioVerificado respeta provincia_contexto_manual.
            $ctx->id_provincia_contexto = $idProvincia;
            $ctx->provincia_contexto_manual = true;
        }

        $ctx->updated_at = $now;
        $ctx->save(false);

        return $this->export($ctx);
    }

    public function marcarDomicilioVerificado(PersonaPacienteContexto $ctx, int $idProvincia): void
    {
        $ctx->domicilio_estado = PersonaPacienteContexto::DOMICILIO_VERIFICADO;
        if (!$ctx->provincia_contexto_manual || !$ctx->tieneProvinciaOperativa()) {
            $ctx->id_provincia_contexto = $idProvincia;
            $ctx->provincia_contexto_manual = false;
        }
        $ctx->updated_at = date('Y-m-d H:i:s');
        $ctx->save(false);
    }

    public function marcarRequiereProvinciaManual(PersonaPacienteContexto $ctx): void
    {
        if ($ctx->domicilio_estado === PersonaPacienteContexto::DOMICILIO_VERIFICADO) {
            return;
        }
        $ctx->domicilio_estado = PersonaPacienteContexto::DOMICILIO_REQUIERE_PROVINCIA_MANUAL;
        $ctx->updated_at = date('Y-m-d H:i:s');
        $ctx->save(false);
    }

    public function registrarIntento(PersonaPacienteContexto $ctx): void
    {
        $ctx->domicilio_intentos = (int) $ctx->domicilio_intentos + 1;
        $ctx->domicilio_ultimo_intento = date('Y-m-d H:i:s');
        $ctx->updated_at = date('Y-m-d H:i:s');
        $ctx->save(false);
    }
}
