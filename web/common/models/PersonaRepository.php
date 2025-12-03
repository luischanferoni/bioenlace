<?php
namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\db\Query;

/**
 * Description of ConsultaRepository
 *
 * @author aautalan
 */
class PersonaRepository {
    
    public static function getPercentileData(
            $ptype,
            $sexo,
            $max_year=null
    ) {
        $sql = <<<EOL
SELECT 
JSON_ARRAYAGG(
    if(tipo_edad = 'semanas',
        edad/52.143, 
        if(tipo_edad ='meses', edad/12, edad))
    ) as edad_y,
JSON_ARRAYAGG(percentilo_1) as P1,
JSON_ARRAYAGG(percentilo_2) as P2,
JSON_ARRAYAGG(percentilo_3) as P3,
JSON_ARRAYAGG(percentilo_4) as P4,
JSON_ARRAYAGG(percentilo_5) as P5,
JSON_ARRAYAGG(percentilo_6) as P6,
JSON_ARRAYAGG(percentilo_7) as P7
from percentilos p 
where nombre=:ptype and sexo=:sexo
EOL;
        $params = [
            ':ptype' => $ptype,
            ':sexo'=> $sexo
        ];
        if($max_year !== null) {
            $sql_max = <<<EOL
 and if(tipo_edad = 'semanas',
        edad/52.143, 
        if(tipo_edad ='meses', edad/12, edad)
     ) <= :max_year
EOL;
            $sql = $sql . $sql_max;
            $params[':max_year'] = intval($max_year) + 1;
        }
        
        $cmd = Persona::getDb()->createCommand($sql, $params);
        //print_r($cmd->rawSql);die;
        $rs = $cmd->queryOne();
        return $rs;
    }
    
    public static function getPercentilosPeso(Persona $p, $max_year=null) {
        return self::getPercentileData('Peso', $p->sexoCrecimiento, $max_year);
    }
    
    public static function getPercentilosTalla(Persona $p, $max_year=null) {
        return self::getPercentileData('Talla', $p->sexoCrecimiento, $max_year);
    }
    
    public static function getPercentilosPCefalico(Persona $p, $max_year=null) {
        return self::getPercentileData(
                'Perímetro Cefálico', $p->sexoCrecimiento, $max_year);
    }
    
    public static function getPercentilosIMC(Persona $p, $max_year=null) {
        return self::getPercentileData('IMC', $p->sexoCrecimiento, $max_year);
    }
    
    public static function getMaxEdadConDatosCargados(Persona $p) {
        $sql = <<<EOL
select max(
    TIMESTAMPDIFF(MONTH, per.fecha_nacimiento, ae.fecha_creacion)/12
    ) as max_year
  from personas per
  join atenciones_enfermeria ae on per.id_persona = ae.id_persona
  where per.id_persona = :persona_id
  and NOT (
	if(JSON_VALUE(datos , '$.peso'),
        JSON_VALUE(datos , '$.peso'), 
        JSON_VALUE(datos , '$.162879003p')) IS NULL
	AND if(JSON_VALUE(datos , '$.talla'),
        JSON_VALUE(datos , '$.talla'),
        JSON_VALUE(datos , '$.162879003t')) IS NULL
	AND if(JSON_VALUE(datos , '$.perimetro_cefalico'),
        JSON_VALUE(datos , '$.perimetro_cefalico'),
        JSON_VALUE(datos , '$.363812007')) IS NULL
	)
;
EOL;
        $params = [':persona_id' => $p->id_persona];
        $cmd = Persona::getDb()->createCommand($sql, $params);
        //print_r($cmd->rawSql); die;
        $rs = $cmd->queryScalar();
        //print_r($rs);die;
        return $rs;
    }


    public static function getDatosCrecimiento(Persona $p) {
        $sql = <<<EOL
SELECT
  JSON_ARRAYAGG(
  	CAST(
        if(JSON_VALUE(datos , '$.peso'),
            JSON_VALUE(datos , '$.peso'),
            JSON_VALUE(datos , '$.162879003p'))
        as float)
  	) as peso, 
  JSON_ARRAYAGG(
    CAST(
        if(JSON_VALUE(datos , '$.talla'),
            JSON_VALUE(datos , '$.talla'),
            JSON_VALUE(datos , '$.162879003t'))
        as float)
    ) as talla,
  JSON_ARRAYAGG(
    ROUND(
      if(JSON_VALUE(datos , '$.peso'), 
        JSON_VALUE(datos , '$.peso'), 
        JSON_VALUE(datos , '$.162879003p'))
      /
      POW(
        if(JSON_VALUE(datos , '$.talla'),  
          JSON_VALUE(datos , '$.talla'), 
          JSON_VALUE(datos , '$.162879003t'))
          * 0.01,
        2
      ),
      3 )
    ) as imc,
  JSON_ARRAYAGG(
    CAST(
        if(JSON_VALUE(datos , '$.perimetro_cefalico'),
            JSON_VALUE(datos , '$.perimetro_cefalico'),
            JSON_VALUE(datos , '$.363812007'))
        as float)
    ) as perimetro_cefalico,
  JSON_ARRAYAGG(
    TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, ae.fecha_creacion)
    ) as edad_atencion_m,
  JSON_ARRAYAGG(
    TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, ae.fecha_creacion)/12
    ) as edad_atencion_y
FROM atenciones_enfermeria as ae
join personas p on ae.id_persona = p.id_persona 
where
  ae.id_persona = :persona_id
and NOT (
	if(JSON_VALUE(datos , '$.peso'),
        JSON_VALUE(datos , '$.peso'), 
        JSON_VALUE(datos , '$.162879003p')) IS NULL
	AND if(JSON_VALUE(datos , '$.talla'),
        JSON_VALUE(datos , '$.talla'),
        JSON_VALUE(datos , '$.162879003t')) IS NULL
	AND if(JSON_VALUE(datos , '$.perimetro_cefalico'),
        JSON_VALUE(datos , '$.perimetro_cefalico'),
        JSON_VALUE(datos , '$.363812007')) IS NULL
	)
order by fecha_creacion 
;
EOL;
        $cmd = Persona::getDb()->createCommand($sql);
        $rs = $cmd->bindValue(':persona_id', $p->id_persona)
                ->queryOne();
        //print_r($rs);die;
        return $rs;
    }
    
    public static function getDatosSignosVitales(Persona $p) {
        $sql = <<<EOL
SELECT
	ae.id as atencion_id,
	DATE_FORMAT(ae.fecha_creacion, "%d/%m/%Y") as fecha_atencion,
  	CAST(
        if(JSON_VALUE(datos , '$.peso'),
            JSON_VALUE(datos , '$.peso'),
            JSON_VALUE(datos , '$.162879003p'))
        as float)
  	as peso,
    CAST(
        if(JSON_VALUE(datos , '$.talla'),
            JSON_VALUE(datos , '$.talla'),
            JSON_VALUE(datos , '$.162879003t'))
        as float)
    as talla,
    ROUND(
      if(JSON_VALUE(datos , '$.peso'), 
        JSON_VALUE(datos , '$.peso'), 
        JSON_VALUE(datos , '$.162879003p'))
      /
      POW(
        if(JSON_VALUE(datos , '$.talla'),  
          JSON_VALUE(datos , '$.talla'), 
          JSON_VALUE(datos , '$.162879003t'))
          * 0.01,
        2
      ),
      3 )
    as imc,
    if(JSON_VALUE(datos , '$.TensionArterial1.271649006'),
            JSON_VALUE(datos , '$.TensionArterial1.271649006'),
            JSON_VALUE(datos , '$.sistolica'))
	as ta1_sistolica,
	if(JSON_VALUE(datos , '$.TensionArterial1.271650006'),
	            JSON_VALUE(datos , '$.TensionArterial1.271650006'),
	            JSON_VALUE(datos , '$.diastolica'))
	as ta1_diastolica,
	if(JSON_VALUE(datos , '$.TensionArterial2.271649006'),
	            JSON_VALUE(datos , '$.TensionArterial2.271649006'),
	            Null)
	as ta2_sistolica,
	if(JSON_VALUE(datos , '$.TensionArterial2.271650006'),
	            JSON_VALUE(datos , '$.TensionArterial2.271650006'),
	            Null)
	as ta2_diastolica,
    TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, ae.fecha_creacion)/12
    as edad_atencion_y
FROM atenciones_enfermeria as ae
join personas p on ae.id_persona = p.id_persona 
where
  ae.id_persona = :persona_id
order by fecha_creacion desc
Limit 10
;
EOL;
        $cmd = Persona::getDb()->createCommand($sql);
        $rs = $cmd->bindValue(':persona_id', $p->id_persona)
                ->queryAll();
        return $rs;
    }
    
    public static function getUltimosSignosVitales($datos_sv) {
      # del array devuleto por getDatosSignosVitales
      # obtener el ultimo registro valido para cada campo
      $result = [
          'peso' => [
              'value' => '',
              'fecha' => '',
          ],
          'talla' => [
              'value' => '',
              'fecha' => '',
          ],
          'imc' => [
              'value' => '',
              'fecha' => '',
          ],
          'ta' => [
              'sistolica' => '',
              'diastolica' => '',
              'fecha' => '',
          ],
      ];
      $imc_found = false;
      $ta_found = false;
      foreach($datos_sv as $row) {
          $imc = $row['imc'];
          $ta1s = $row['ta1_sistolica'];
          $fecha = $row['fecha_atencion'];
         
          if($imc && $imc_found == false) {
            foreach (['imc', 'peso', 'talla'] as $key) {
              $result[$key]['value'] = $row[$key];
              $result[$key]['value'] = $row[$key];
              $result[$key]['value'] = $row[$key];
              $result[$key]['fecha'] = $fecha;
              $imc_found = True;
            }
          }
          if($ta1s && $ta_found == false) {
            $result['ta']['sistolica'] = $row['ta1_sistolica'];
            $result['ta']['diastolica'] = $row['ta1_diastolica'];
            $result['ta']['fecha'] = $fecha;
            $ta_found = true;
          }
          if($imc_found && $ta_found) break;
      }
      return $result;
    }
}
