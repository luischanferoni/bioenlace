<?php

namespace common\components\Domain\Integrations\Mpi;

use Yii;
use yii\base\Component;
use common\components\Domain\Integrations\Mpi\MpiCapability;

/**
 * Cliente HTTP para MPI/SEIPA (empadronamiento, RENAPER, coberturas, domicilio).
 */
class MpiApiClient extends Component
{
    private const URL = 'https://esalud.msaludsgo.gov.ar/seipa/web/api/v1/';

    private const URL_TEST = 'http://190.30.242.228/seipa/web/api/v1/';

    /**
     * @param array<string, mixed>|string|null $json
     * @return array<string, mixed>|null
     */
    public function call(string $metodo, $json = '{}', string $verb = 'GET'): ?array
    {
        $token = MpiJwtTokenService::buildBearerToken(
            'https://sisse.msalsgo.gob.ar',
            'https://esalud.msaludsgo.gov.ar/seipa/web/api/v1'
        );

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $payload = is_string($json) ? $json : json_encode($json);
        $url = (YII_ENV_PROD ? self::URL : self::URL_TEST) . $metodo;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        Yii::warning($url, 'mpi');

        $resp = curl_exec($ch);
        Yii::warning($resp, 'mpi');
        $respuesta = json_decode((string) $resp, true);
        Yii::warning($respuesta, 'mpi');

        return is_array($respuesta) ? $respuesta : null;
    }

    /** @deprecated Compatibilidad con código legacy. */
    public function caller_mpi(string $metodo, $json = '{}', string $verb = 'GET'): ?array
    {
        return $this->call($metodo, $json, $verb);
    }

    /** Alias para {@see PersonRepresentationMpiService} y componente Yii `mpi`. */
    public function caller(string $metodo, $json = '{}', string $verb = 'GET'): ?array
    {
        return $this->call($metodo, $json, $verb);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function traerPaciente($id_persona = null, string $fuente = 'local'): ?array
    {
        if (!MpiCapability::isEnabled(MpiCapability::TRAER_PACIENTE)) {
            return null;
        }

        return $this->call("pacientes?fuente=$fuente&identificador=$id_persona", '{}', 'GET');
    }

    /**
     * @param array<string, mixed> $parametros
     * @return array<string, mixed>|null
     */
    public function candidatos(array $parametros): ?array
    {
        if (!MpiCapability::isEnabled(MpiCapability::CANDIDATOS)) {
            return null;
        }

        $patientJson = $this->loadSchema('pivote-schema.json');
        $texto = str_replace('@tipo_documento', (string) $parametros['tipo_doc'], $patientJson);
        $texto = str_replace('@nro_documento', (string) $parametros['documento'], $texto);
        $texto = str_replace('@apellido', (string) $parametros['apellido'], $texto);
        $texto = str_replace('@nombre', (string) $parametros['nombre'], $texto);
        $texto = str_replace('@genero', (string) $parametros['sexo'], $texto);
        $texto = str_replace('@fecha_nacimiento', (string) $parametros['fecha_nacimiento'], $texto);

        return $this->call('pacientes/candidatos', $texto, 'POST');
    }

    /**
     * @param array<string, mixed> $parametros
     * @return array<string, mixed>|null
     */
    public function empadronar(array $parametros): ?array
    {
        if (!MpiCapability::isEnabled(MpiCapability::EMPADRONAR)) {
            return null;
        }

        $fijo = $this->devolverTelefonos($parametros['telefonos'], 1);
        $celular = $this->devolverTelefonos($parametros['telefonos'], 2);
        $mails = $this->devolverMails($parametros['mails']);
        $datos_calle = $this->devolverCalle($parametros);
        $band_g = $band_sb = false;
        $array_identidad = [0 => 'false', 1 => 'true'];

        if (isset($parametros['genero']) && $parametros['genero'] != '') {
            $genero = $parametros['genero'];
        } else {
            $genero = 0;
            $band_g = true;
        }
        if (isset($parametros['sexo_biologico']) && $parametros['sexo_biologico'] != '') {
            $sexo_biologico = $parametros['sexo_biologico'];
        } else {
            $sexo_biologico = 0;
            $band_sb = true;
        }

        $patientJson = $this->loadSchema('patient_set_ampliado.json');
        $texto = str_replace('@identificador', (string) $parametros['id_persona'], $patientJson);
        $texto = str_replace('@tipo_documento', (string) $parametros['id_tipodoc'], $texto);
        $texto = str_replace('@nro_documento', (string) $parametros['documento'], $texto);
        $texto = str_replace('@apellido', (string) $parametros['apellido'], $texto);
        $texto = str_replace('@otro_apellido', (string) $parametros['otro_apellido'], $texto);
        $texto = str_replace('@ape_materno', (string) $parametros['apellido_materno'], $texto);
        $texto = str_replace('@ape_paterno', (string) $parametros['apellido_paterno'], $texto);
        $texto = str_replace('@nombre', (string) $parametros['nombre'], $texto);
        $texto = str_replace('@otros_nombres', (string) $parametros['otro_nombre'], $texto);
        $texto = str_replace('@sexo_biologico', (string) $sexo_biologico, $texto);
        $texto = str_replace('@genero', (string) $genero, $texto);
        $texto = str_replace('@fecha_nacimiento', (string) $parametros['fecha_nacimiento'], $texto);
        $texto = str_replace('@acredita_identidad', $array_identidad[$parametros['acredita_identidad']], $texto);
        $texto = str_replace('@celular', "[\"$celular\"]", $texto);
        $texto = str_replace('@fijo', "[\"$fijo\"]", $texto);
        $texto = str_replace('@email', "[\"$mails\"]", $texto);
        $texto = str_replace('@provincia', json_encode(['id' => $parametros['provincia']->cod_indec]), $texto);
        $texto = str_replace(
            '@departamento',
            json_encode(['id' => $parametros['provincia']->cod_indec . $parametros['departamento']->cod_indec]),
            $texto
        );
        $texto = str_replace('@localidad', json_encode(['id' => $parametros['localidad']->cod_bahra]), $texto);
        $texto = str_replace('@calle', $datos_calle, $texto);

        $numero = (isset($parametros['numero']) && $parametros['numero'] != '') ? $parametros['numero'] : '0';
        $texto = str_replace('@numero', "\"$numero\"", $texto);
        $texto = str_replace('@latitud', '"-"', $texto);
        $texto = str_replace('@longitud', '"-"', $texto);

        $arreglo_json = json_decode($texto);
        unset($arreglo_json->paciente->set_ampliado->residencia->geoposicion);
        if ($band_sb) {
            unset($arreglo_json->paciente->set_minimo->sexo_biologico);
        }
        if ($band_g) {
            unset($arreglo_json->paciente->set_minimo->genero);
        }

        return $this->call('pacientes', json_encode($arreglo_json), 'POST');
    }

    /**
     * @param array<string, mixed> $parametros
     * @return array<string, mixed>|null
     */
    public function asociar(array $parametros): ?array
    {
        if (!MpiCapability::isEnabled(MpiCapability::ASOCIAR)) {
            return null;
        }

        $fijo = $this->devolverTelefonos($parametros['telefonos'], 1);
        $celular = $this->devolverTelefonos($parametros['telefonos'], 2);
        $mails = $this->devolverMails($parametros['mails']);
        $datos_calle = $this->devolverCalle($parametros);
        $patientJson = $this->loadSchema('asociar-paciente-schema.json');

        $texto = str_replace('@mpi', (string) $parametros['mpi'], $patientJson);
        $texto = str_replace('@local_id', (string) $parametros['local_id'], $texto);
        $texto = str_replace('@celular', "[\"$celular\"]", $texto);
        $texto = str_replace('@fijo', "[\"$fijo\"]", $texto);
        $texto = str_replace('@email', "[\"$mails\"]", $texto);
        $texto = str_replace('@provincia', json_encode(['id' => $parametros['provincia']->cod_indec]), $texto);
        $texto = str_replace(
            '@departamento',
            json_encode(['id' => $parametros['provincia']->cod_indec . $parametros['departamento']->cod_indec]),
            $texto
        );
        $texto = str_replace('@localidad', json_encode(['id' => $parametros['localidad']->cod_bahra]), $texto);
        $texto = str_replace('@calle', $datos_calle, $texto);

        $numero = (isset($parametros['numero']) && $parametros['numero'] != '') ? $parametros['numero'] : '0';
        $texto = str_replace('@numero', "\"$numero\"", $texto);
        $texto = str_replace('@latitud', '"-"', $texto);
        $texto = str_replace('@longitud', '"-"', $texto);

        $arreglo_json = json_decode($texto);
        unset($arreglo_json->paciente->set_ampliado->residencia->geoposicion);

        return $this->call('pacientes', json_encode($arreglo_json), 'PATCH');
    }

    public function tokenPrueba(): string
    {
        return MpiJwtTokenService::buildBearerToken(
            'http://sisse.redes-sgo.gob.ar',
            'https://esalud.msaludsgo.gov.ar/seipa/web/api/v1'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_cobertura_social(string $dni, string $sexo, bool $include_exceptions = false): array
    {
        if (!MpiCapability::isEnabled(MpiCapability::COBERTURAS)) {
            return $include_exceptions ? [[0 => 'Capacidad coberturas MPI deshabilitada']] : [];
        }

        $cmd = sprintf('coberturas?dni=%s&sexo=%s', $dni, $sexo);
        $coberturas = [];

        try {
            $response = $this->call($cmd, '{}');
            $consulta_api_ok = false;
            if ($response) {
                $consulta_api_ok = (
                    ($response['successful'] ?? 0) == 1
                    && ($response['statusCode'] ?? 0) == 200
                );
            }

            if ($consulta_api_ok) {
                $data = $response['data'];
                foreach ($data as $row) {
                    if (isset($row['cobertura']) && isset($row['rnos'])) {
                        $label = sprintf('%s (S: %s)', $row['cobertura'], $row['servicio']);
                        $id_cobertura = $row['rnos'];
                        if ($id_cobertura == 'SUMAR') {
                            $id_cobertura = 996001;
                        }
                        $coberturas[] = [
                            'codigo' => $id_cobertura,
                            'nombre' => $label,
                        ];
                    } elseif ($include_exceptions) {
                        $coberturas[] = [0 => 'API ERROR'];
                    }
                }
            } elseif ($include_exceptions) {
                $coberturas[] = [0 => 'No se encuentra la persona.'];
            }
        } catch (\Throwable $e) {
            if ($include_exceptions) {
                $coberturas[] = [0 => 'SIN RESULTADOS - Error de conexión'];
            }
        }

        return $coberturas;
    }

    /**
     * Domicilio declarado vía MPI (endpoint dedicado, no renaper?).
     *
     * @return array<string, mixed>|null Fila normalizada para {@see RenaperDomicilioPersisterService}
     */
    public function getDomicilio(string $dni, string $sexo): ?array
    {
        if (!MpiCapability::isEnabled(MpiCapability::DOMICILIO)) {
            return null;
        }

        $cmd = sprintf(
            'domicilio?dni=%s&sexo=%s',
            rawurlencode($dni),
            rawurlencode($sexo)
        );

        try {
            $response = $this->call($cmd, '{}');

            return MpiDomicilioNormalizer::normalizeResponse($response);
        } catch (\Throwable $e) {
            Yii::error('MPI domicilio: ' . $e->getMessage(), 'mpi');

            return null;
        }
    }

    private function loadSchema(string $filename): string
    {
        $bases = array_filter([
            Yii::$app->params['mpiSchemaPath'] ?? null,
            dirname(Yii::getAlias('@frontend')) . '/api_mpi',
            dirname(Yii::getAlias('@frontend')) . '/../api_mpi',
        ]);

        foreach ($bases as $base) {
            $path = rtrim((string) $base, '/\\') . DIRECTORY_SEPARATOR . $filename;
            if (is_readable($path)) {
                $content = file_get_contents($path);

                return $content !== false ? $content : '';
            }
        }

        throw new \RuntimeException('Schema MPI no encontrado: ' . $filename);
    }

    /**
     * @param iterable<object> $lista
     */
    private function devolverTelefonos(iterable $lista, int $tipo): string
    {
        $array_tels = [];
        foreach ($lista as $telefono) {
            if ($telefono->id_tipo_telefono == $tipo) {
                $array_tels[] = $telefono->numero;
            }
        }

        return implode('","', $array_tels);
    }

    /**
     * @param iterable<object> $lista
     */
    private function devolverMails(iterable $lista): string
    {
        $array_mails = [];
        foreach ($lista as $mail) {
            $array_mails[] = $mail->mail;
        }

        return implode('","', $array_mails);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    private function devolverCalle(array $parametros): string
    {
        if (isset($parametros['calle']) && $parametros['calle'] != '') {
            return (string) $parametros['calle'];
        }

        if (
            isset($parametros['domicilio']['manzana']) && $parametros['domicilio']['manzana'] != ''
            && isset($parametros['domicilio']['lote']) && $parametros['domicilio']['lote'] != ''
            && isset($parametros['barrio']) && $parametros['barrio'] != ''
        ) {
            $datos_calle = 'Mza ' . $parametros['domicilio']['manzana'];
            $datos_calle .= ' Lote ' . $parametros['domicilio']['lote'];
            $datos_calle .= ' Barrio ' . $parametros['barrio'];

            return $datos_calle;
        }

        return 'S/N';
    }
}
