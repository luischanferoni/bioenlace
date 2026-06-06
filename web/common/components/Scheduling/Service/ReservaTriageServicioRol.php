<?php

namespace common\components\Scheduling\Service;

/**
 * Roles lógicos de servicio al reservar turno (vinculados a filas {@see \common\models\Servicio} vía {@see ReservaTriageServicioMapService}).
 *
 * Reglas triage_codigo → rol: tabla {@see \common\models\ReservaTriageCodigoServicioRol} + {@see BUILTIN_CODIGO_ROL}.
 */
final class ReservaTriageServicioRol
{
    public const MEDICINA_CLINICA = 'medicina_clinica';
    public const TRAMITE_ADMIN = 'tramite_admin';
    public const OFTALMOLOGIA = 'oftalmologia';
    public const DERMATOLOGIA = 'dermatologia';
    public const TRAUMATOLOGIA = 'traumatologia';
    public const GINECOLOGIA = 'ginecologia';
    public const PEDIATRIA = 'pediatria';
    public const CARDIOLOGIA = 'cardiologia';
    public const NEUMONOLOGIA = 'neumonologia';
    public const PSIQUIATRIA = 'psiquiatria';
    public const GASTROENTEROLOGIA = 'gastroenterologia';
    public const NEUROLOGIA = 'neurologia';
    public const ENDOCRINOLOGIA = 'endocrinologia';
    public const UROLOGIA = 'urologia';
    public const ODONTOLOGIA = 'odontologia';
    public const PSICOLOGIA = 'psicologia';

    /**
     * Respaldo embebido si la tabla aún no tiene filas (migración / entornos nuevos).
     * Clave: código triage; valor: rol lógico.
     *
     * @var array<string, string>
     */
    public const BUILTIN_CODIGO_ROL = [
        // Raíz
        'sintoma_nuevo' => self::MEDICINA_CLINICA,
        'control_cronico' => self::MEDICINA_CLINICA,
        'tramite_admin' => self::MEDICINA_CLINICA,
        // Zona
        'zona_cabeza_cuello' => self::MEDICINA_CLINICA,
        'zona_pecho' => self::MEDICINA_CLINICA,
        'zona_abdomen' => self::MEDICINA_CLINICA,
        'zona_espalda' => self::TRAUMATOLOGIA,
        'zona_brazo_mano' => self::TRAUMATOLOGIA,
        'zona_pierna_pie' => self::TRAUMATOLOGIA,
        'zona_piel' => self::DERMATOLOGIA,
        'zona_general' => self::MEDICINA_CLINICA,
        // Detalle
        'det_cabeza_dolor' => self::MEDICINA_CLINICA,
        'det_cabeza_mareo' => self::NEUROLOGIA,
        'det_pecho_dolor' => self::CARDIOLOGIA,
        'det_pecho_tos' => self::NEUMONOLOGIA,
        'det_abd_dolor' => self::GASTROENTEROLOGIA,
        'det_abd_nauseas' => self::GASTROENTEROLOGIA,
        'det_espalda_dolor' => self::TRAUMATOLOGIA,
        'det_musculo_esfuerzo' => self::TRAUMATOLOGIA,
        'det_musculo_esfuerzo_brazo' => self::TRAUMATOLOGIA,
        'det_musculo_esfuerzo_pierna' => self::TRAUMATOLOGIA,
        'det_extremidad_hinchazon' => self::MEDICINA_CLINICA,
        'det_piel_erupcion' => self::DERMATOLOGIA,
        'det_general_fiebre' => self::MEDICINA_CLINICA,
        'det_general_otro' => self::MEDICINA_CLINICA,
    ];

    public static function rolBuiltinParaCodigo(string $codigo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        return self::BUILTIN_CODIGO_ROL[$codigo] ?? null;
    }
}
