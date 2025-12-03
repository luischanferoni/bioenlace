<?php
namespace frontend\controllers;

use Yii;

/**
 * 
 */
class MpiApiController
{
    const URL = 'https://esalud.msaludsgo.gov.ar/seipa/web/api/v1/';
    const URL_TEST = 'http://190.30.242.228/seipa/web/api/v1/';

	function caller_mpi($metodo, $json, $verb="GET") {

       /* $token="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9lc2FsdWQubXNhbHVkc2dvLmdvdi5hciIsImlhdCI6MTU3NzExODk0OCwiZG9taW5pb19pZCI6IjEifQ.0jsOojk2gMRJxr64HhjgpuHRZn7tYB83XEgu2VGRTtc";
        $headers  =  array(    
            'x-api-key: '.$token,        
            'Content-Type: application/json',
        );
        $ch = curl_init('http://esalud.msaludsgo.gov.ar/api/v1/'.$metodo);*/
        
	/*
        $token="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9lc2FsdWQubXNhbHVkc2dvLmdvdi5hciIsImlhdCI6MTU3Njc4NTQ4OSwiZG9taW5pb19pZCI6IjQ1NiJ9.NQK3wjCCp_rS2QHp_AF_4hYYsj62yh3xNPcUoNmJWQ8";
        
        $headers  =  array(    
        	'x-api-key: '.$token,         
        	'Content-Type: application/json',
        );
	*/	

        $jwt = Yii::$app->jwt;
        $signer = $jwt->getSigner('HS512');
        $key = $jwt->getKey();
        $time = time();

        $token = $jwt->getBuilder()
                ->issuedBy('https://sisse.msalsgo.gob.ar') // Configures the issuer (iss claim)
                ->permittedFor('https://esalud.msaludsgo.gov.ar/seipa/web/api/v1') // Configures the audience (aud claim)
                ->issuedAt($time) // Configures the time that the token was issue (iat claim)
                ->expiresAt($time + 15) // Configures the expiration time of the token (exp claim)
                ->withClaim('id_cliente', "sisse") 
                ->getToken($signer, $key); // Retrieves the generated token

        $headers = [
                'Authorization: Bearer '.$token,         
                'Content-Type: application/json',
            ];

        $ch = curl_init((YII_ENV_PROD ? self::URL : self::URL_TEST).$metodo);

        //var_dump($metodo);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                                                             
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        Yii::warning((YII_ENV_PROD ? self::URL : self::URL_TEST).$metodo, "mpi");
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);      
        $resp = curl_exec($ch); 
        Yii::warning($resp, "mpi");
        $respuesta = json_decode($resp,true);
        Yii::warning($respuesta, "mpi");
        return $respuesta;
    }

    public function traerPaciente($id_persona = null, $fuente = 'local'){
        //echo "pacientes?fuente=$fuente&id=$id_persona"; die();
    	$resultado=$this->caller_mpi("pacientes?fuente=$fuente&identificador=$id_persona","GET");
    	return $resultado;
    }

    public function candidatos($parametros) {

        $patient_json = file_get_contents('../api_mpi/pivote-schema.json', true);
        $texto=str_replace("@tipo_documento", $parametros['tipo_doc'], $patient_json);
        $texto=str_replace("@nro_documento", $parametros['documento'], $texto);
        $texto=str_replace("@apellido", $parametros['apellido'], $texto);
        $texto=str_replace("@nombre", $parametros['nombre'], $texto);
        $texto=str_replace("@genero", $parametros['sexo'] , $texto);
        $texto=str_replace("@fecha_nacimiento", $parametros['fecha_nacimiento'] , $texto);

        $respuesta = $this->caller_mpi("pacientes/candidatos",$texto,"POST");
        
        return $respuesta;
    }
    
    public function empadronar($parametros){
        $fijo = $this->devolverTelefonos($parametros['telefonos'],1);
        $celular = $this->devolverTelefonos($parametros['telefonos'],2);
        $mails = $this->devolverMails($parametros['mails']);
        $datos_calle = $this->devolverCalle($parametros);
        $band_g = $band_sb = false;
        $array_identidad = [0 => 'false', 1 => 'true'];

        if(isset($parametros['genero']) && $parametros['genero'] != ''){
            $genero = $parametros['genero'];
        } else {
            $genero = 0;
            $band_g = true;
        }
        if(isset($parametros['sexo_biologico']) && $parametros['sexo_biologico'] != ''){
            $sexo_biologico = $parametros['sexo_biologico'];
        } else {
            $sexo_biologico = 0;
            $band_sb = true;
        }

        $patient_json = file_get_contents('../api_mpi/patient_set_ampliado.json', true);
        $texto=str_replace("@identificador", $parametros['id_persona'], $patient_json);
        $texto=str_replace("@tipo_documento", $parametros['id_tipodoc'], $texto);
        $texto=str_replace("@nro_documento", $parametros['documento'], $texto);
        $texto=str_replace("@apellido", $parametros['apellido'], $texto);
        $texto=str_replace("@otro_apellido", $parametros['otro_apellido'], $texto);        
        $texto=str_replace("@ape_materno", $parametros['apellido_materno'], $texto);
        $texto=str_replace("@ape_paterno", $parametros['apellido_paterno'], $texto);
        $texto=str_replace("@nombre", $parametros['nombre'], $texto);
        $texto=str_replace("@otros_nombres", $parametros['otro_nombre'], $texto);
        $texto=str_replace("@sexo_biologico", $sexo_biologico, $texto);
        $texto=str_replace("@genero", $genero, $texto);
        $texto=str_replace("@fecha_nacimiento", $parametros['fecha_nacimiento'], $texto);
        $texto=str_replace("@acredita_identidad",$array_identidad[$parametros['acredita_identidad']] , $texto);
        $texto=str_replace("@celular", "[\"$celular\"]", $texto);
        $texto=str_replace("@fijo", "[\"$fijo\"]", $texto);
        $texto=str_replace("@email", "[\"$mails\"]", $texto);
        $texto=str_replace("@provincia", json_encode(['id' => $parametros['provincia']->cod_indec]), $texto);
        $texto=str_replace("@departamento", json_encode(['id' => $parametros['provincia']->cod_indec.$parametros['departamento']->cod_indec]), $texto);
        $texto=str_replace("@localidad", json_encode(['id' => $parametros['localidad']->cod_bahra]), $texto);
        $texto=str_replace("@calle", $datos_calle, $texto);
        
        if(isset($parametros["numero"]) && $parametros["numero"]!=''){
            $numero = $parametros["numero"];
        } else {
            $numero = "0";
        }
        $texto=str_replace("@numero", "\"$numero\"", $texto);
        $texto=str_replace("@latitud", "\"-\"", $texto);
        $texto=str_replace("@longitud", "\"-\"", $texto);

        $arreglo_json=json_decode($texto);
        
        unset($arreglo_json->paciente->set_ampliado->residencia->geoposicion);
        if ($band_sb == true) {
            unset($arreglo_json->paciente->set_minimo->sexo_biologico);
        }
        if ($band_g) {
            unset($arreglo_json->paciente->set_minimo->genero);
        }
        $texto = json_encode($arreglo_json);
        $resultado = $this->caller_mpi("pacientes",$texto,"POST"); 
       
         return $resultado;
    }

    public function asociar($parametros)
    {
        $fijo = $this->devolverTelefonos($parametros['telefonos'],1);
        $celular = $this->devolverTelefonos($parametros['telefonos'],2);
        $mails = $this->devolverMails($parametros['mails']);
        $datos_calle = $this->devolverCalle($parametros);
        $patient_json = file_get_contents('../api_mpi/asociar-paciente-schema.json', true);

        $texto=str_replace("@mpi", $parametros['mpi'], $patient_json);
        $texto=str_replace("@local_id", $parametros['local_id'], $texto);

        $texto=str_replace("@celular", "[\"$celular\"]", $texto);
        $texto=str_replace("@fijo", "[\"$fijo\"]", $texto);
        $texto=str_replace("@email", "[\"$mails\"]", $texto);
        $texto=str_replace("@provincia", json_encode(['id' => $parametros['provincia']->cod_indec]), $texto);
        $texto=str_replace("@departamento", json_encode(['id' => $parametros['provincia']->cod_indec.$parametros['departamento']->cod_indec]), $texto);
        $texto=str_replace("@localidad", json_encode(['id' => $parametros['localidad']->cod_bahra]), $texto);
        $texto=str_replace("@calle", $datos_calle, $texto);
        if(isset($parametros["numero"]) && $parametros["numero"]!=''){
            $numero = $parametros["numero"];
        } else {
            $numero = "0";
        }
        $texto=str_replace("@numero", "\"$numero\"", $texto);
        $texto=str_replace("@latitud", "\"-\"", $texto);
        $texto=str_replace("@longitud", "\"-\"", $texto);

        $arreglo_json=json_decode($texto);
        unset($arreglo_json->paciente->set_ampliado->residencia->geoposicion);
        $texto=json_encode($arreglo_json);    
        
        $resultado=$this->caller_mpi("pacientes",$texto,"PATCH");

       /* echo "<br><br><br>--------asociar-------<br><br><br>";
        var_dump($texto);
        echo "<br><br><br>---------------<br><br><br>";
        var_dump($resultado); die();*/
        return $resultado;
    }

    public function tokenPrueba()
    {
        $jwt = Yii::$app->jwt;
        $signer = $jwt->getSigner('HS512');
        $key = $jwt->getKey();
        $time = time();
    
        $token = $jwt->getBuilder()
                ->issuedBy('http://sisse.redes-sgo.gob.ar') // Configures the issuer (iss claim)
                ->permittedFor('https://esalud.msaludsgo.gov.ar/seipa/web/api/v1') // Configures the audience (aud claim)
                ->issuedAt($time) // Configures the time that the token was issue (iat claim)
                ->expiresAt($time + 15) // Configures the expiration time of the token (exp claim)
                ->withClaim('id_cliente', "sisse") 
                ->getToken($signer, $key); // Retrieves the generated token

        return $token;
    }
    
    private function transformarFecha($fecha_nacimiento){
        $fn = strtotime($fecha_nacimiento);
        return date('d/m/Y',$fn);
    }

    private function devolverTelefonos($lista, $tipo){
        $array_tels = [];
        foreach ($lista as $telefono) {
            if($telefono->id_tipo_telefono == $tipo){
                $array_tels[]=$telefono->numero;
            }
        }
        return implode('","', $array_tels);
    }
    private function devolverMails($lista){
        $array_mails = [];
        foreach ($lista as $mail) {           
            $array_mails[]=$mail->mail;            
        }

        return implode('","', $array_mails);
    }
     private function devolverCalle($parametros){
        if(isset($parametros['calle']) && $parametros['calle'] != ""){
            $datos_calle = $parametros['calle'];
        } else {
            if (isset($parametros['domicilio']['manzana']) && $parametros['domicilio']['manzana'] != "" && isset($parametros['domicilio']['lote']) && $parametros['domicilio']['lote'] != "" && isset($parametros['barrio']) && $parametros['barrio'] != "") {
                $datos_calle = "Mza ". $parametros['domicilio']['manzana'];            
                $datos_calle .= " Lote ". $parametros['domicilio']['lote'];
                $datos_calle .= " Barrio ". $parametros['barrio'];
            } else {
                $datos_calle = "S/N";
            }

        }

        return $datos_calle;
    }

    /*
    returns: Arreglo asociativo de coberturas sociales codigo=>nombre
    */
    public function get_cobertura_social(
            $dni, 
            $sexo, 
            $include_exceptions=False
    ) {
        $cmd = sprintf("coberturas?dni=%s&sexo=%s", $dni, $sexo);

        $coberturas = [];
        try {
            $response = $this->caller_mpi($cmd, '{}');
            //Yii::error($response);
            // print_r($response);
            $consulta_api_ok = false;
            if($response){
                $consulta_api_ok = (
                    $response['successful'] == 1 
                    && $response['statusCode'] == 200
                );
            }

            if ($consulta_api_ok) {
                $data = $response['data'];
                foreach($data as $row) {
                    if(isset($row['cobertura']) && isset($row['rnos'])) {
                        $label = sprintf("%s (S: %s)", $row['cobertura'], $row['servicio']);
                        $id_cobertura = $row['rnos'];

                        // Por ahora sumar no tiene codigo en RNOS
                        if($id_cobertura == 'SUMAR') {
                            $id_cobertura = 996001;
                        }
                        $coberturas[] = [
                            'codigo' => $id_cobertura,
                            'nombre' => $label
                        ];
                    } elseif($include_exceptions) {
                        $coberturas[] = [ 0 => 'API ERROR'];
                    }
                    
                }
            }
            elseif($include_exceptions) {
                $coberturas[] = [0 => "No se encuentra la persona."];
            }
            
        } catch (Exception $e) {
            if ($include_exceptions)
                $coberturas[] = [0 => "SIN RESULTADOS - Error de conexión"];
        }
        
        return $coberturas;
    }
}
