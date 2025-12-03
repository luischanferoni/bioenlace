<?php

//Obtenemos la IP del visitante y la hora actual.
$ip=$_SERVER['REMOTE_ADDR'];
//$dia=date('Y-m-d');
$hora=time();
$existe=0;
$grabar='';
$grabar_hist='';
$array_dia=[];
$array_historico=[];


//Tiempo que tardar� en actualizarse el contador (60=1 minuto, 86400=un dia)
$sesion=$hora-86400;

$archivo_dia="visitas/contar_usuarios.dat";
$archivo_historico="visitas/historico.dat";

$ar=file($archivo_dia);

//Se abre el archivo de texto para eliminar ips expiradas y crear nuevo array con las vigentes.
//Se crea un buqle para recorrer el archivo y leer su contenido
foreach($ar as $pet){
	$ele=explode('-',$pet);
	
	if((trim($ele[1]) == $ip) && (trim($ele[0]) > $sesion)){
		$existe=1;
	}
	
	if(trim($ele[0]) > $sesion){
		$array_dia[]=implode('-',$ele);
	}
	
}

$ar=file($archivo_historico);

//Se abre el archivo de texto para eliminar ips expiradas y crear nuevo array con las vigentes.
//Se crea un buqle para recorrer el archivo y leer su contenido
foreach($ar as $pet){
	$ele=explode('-',$pet);
	
	$array_historico[]=implode('-',$ele);
	
}


//Se abre el archivo para guardar los datos nuevos.
//Se crea un buqle para recorrer el archivo y leer su contenido
$p=fopen($archivo_dia,"w+");


if($existe == 0){
	$array_dia[]=$hora."-".$ip."\n";	
	$array_historico[]=$hora."-".$ip."\n";	
}



foreach($array_dia as $eoeo){
	$grabar.=trim($eoeo)."\n";
}

fwrite($p,$grabar);
fclose($p);

$p_hist=fopen($archivo_historico,"w+");

foreach($array_historico as $eoeo){
	$grabar_hist.=trim($eoeo)."\n";
}

fwrite($p_hist,$grabar_hist);
fclose($p_hist);



$con=file($archivo_dia);
$hist=file($archivo_historico);

//Se guarda en una variable el n�mero de usuarios �nicos visitando la web
$n_usuarios=count($con);
$n_historico=count($hist);

//Se muestran los datos formateados en color rojo
echo " &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<div> &nbsp;&nbsp; </div>
<div align='center' STYLE='font-family: Arial, Helvetica, Sans Serif; font-size:14px;font-weight: bold; color:#008000'>
<img border='0' src='".Yii ::getAlias('@web')."/visitas/logo_visitas.jpg' width='35' height='35'>
Visitas Diarias : $n_usuarios   -   Visitas Historicas: $n_historico
</div>";

?>