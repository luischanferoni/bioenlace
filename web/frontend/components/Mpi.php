<?php
namespace frontend\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
/**
 * 
 */
class Mpi extends Component
{
	function caller($metodo, $json,  $verb="GET") {

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

	    $headers = [
        	'Authorization: Bearer '.$token,         
        	'Content-Type: application/json',
        ];

        
        //$ch = curl_init('http://200.81.126.13:9200/seipa/web/api/v1/'.$metodo);
        $ch = curl_init('https://esalud.msaludsgo.gov.ar/seipa/web/api/v1/'.$metodo);
        //var_dump($metodo);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                                                             
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);      
        // var_dump($ch);
        // echo "<br>";
        // var_dump($headers);
        // echo "<br>";
        $resp = curl_exec($ch); 
        // var_dump($resp);
        // echo "<br>";die;
        // Yii::trace($resp);
        $respuesta = json_decode($resp,true);
        
        //Yii::trace($respuesta);
        return $respuesta;
    }

    function traerPaciente($id_persona = null, $fuente = 'local') {
    	$resultado = $this->caller("pacientes?fuente=$fuente&identificador=$id_persona","GET");
    	return $resultado;
    }

    function candidatos($parametros) {

        $patient_json = file_get_contents('../api_mpi/pivote-schema.json', true);
        $texto=str_replace("@tipo_documento", $parametros['tipo_doc'], $patient_json);
        $texto=str_replace("@nro_documento", $parametros['documento'], $texto);
        $texto=str_replace("@apellido", $parametros['apellido'], $texto);
        $texto=str_replace("@nombre", $parametros['nombre'], $texto);
        $texto=str_replace("@genero", $parametros['sexo'] , $texto);
        $texto=str_replace("@fecha_nacimiento", $parametros['fecha_nacimiento'] , $texto);

        $respuesta = $this->caller("pacientes/candidatos",$texto,"POST");
        
        return $respuesta;
    }
    
    function empadronar($parametros) {
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
        $resultado = $this->caller("pacientes",$texto,"POST"); 
       
         return $resultado;
    }

    function asociar($parametros)
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
        
        $resultado=$this->caller("pacientes",$texto,"PATCH");

       /* echo "<br><br><br>--------asociar-------<br><br><br>";
        var_dump($texto);
        echo "<br><br><br>---------------<br><br><br>";
        var_dump($resultado); die();*/
        return $resultado;
    }

    function transformarFecha($fecha_nacimiento){
        $fn = strtotime($fecha_nacimiento);
        return date('d/m/Y',$fn);
    }

    function devolverTelefonos($lista, $tipo){
        $array_tels = [];
        foreach ($lista as $telefono) {
            if($telefono->id_tipo_telefono == $tipo){
                $array_tels[]=$telefono->numero;
            }
        }
        return implode('","', $array_tels);
    }

    function devolverMails($lista){
        $array_mails = [];
        foreach ($lista as $mail) {           
            $array_mails[]=$mail->mail;            
        }

        return implode('","', $array_mails);
    }

    function devolverCalle($parametros){
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
}
