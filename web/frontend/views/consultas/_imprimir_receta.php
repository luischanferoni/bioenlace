<?php
use yii\helpers\Html;

 ?>
<div class="consulta-view">	
    <table style="width: 100%;">    			
    	<tbody>
	    	<tr>
	    		<th><?=$model->turno->efector->nombre?></th>
	    		<td colspan="2" style="width: 50%; text-align:center;">
			      <?= '<h1>Receta</h1><p>
			           <b>N°: </b>'. $model->id_consulta.'</p><p><b>Fecha de emisión: </b>'.$model->turno->fecha .'<p>'?>
			    </td>
	        </tr>
		     <tr>
		      <td >
		      <?= '<p><b>'.$model->turno->persona->tipoDocumento->nombre.': </b>'.$model->turno->persona->documento .'</p>
		           <p><b>Apellido y Nombre: </b>'.$model->getPaciente($model->id_turnos).'<p>'?>
		       </td>
		       
		       <td></td>
		     </tr>
		</tbody>
    </table>

<h2>Medicamentos</h2>
     <table style="border: 1px solid #000;">
     	<thead style="background-color: blue; color:white;">
     		<th style="width: 10%">N°</th>  
     		<th style="width: 40%">Medicación</th>
     		<th style="width: 15%">Cantidad</th>
            <th style="width: 10%">Dosis Diaria</th>            
     	<thead>
        <tbody style="border: 1px solid #000;">                     

            <?php
            	$i = 0;
                foreach ($model->medicamentosConsultas as $row) {

                    if ($row['id_medicamento'] !== 0) {
                        $diagnostico_mostrar = common\models\Medicamento::findOne(['id_medicamento'=>$row['id_medicamento']])->generico;
                        $codigo = $row['id_medicamento'];
                    } else {
                        $diagnostico = common\models\snomed\SnomedMedicamentos::findOne(['conceptId'=>$row['id_snomed_medicamento']]);
                        if (is_object($diagnostico)) {
                            $diagnostico_mostrar = $diagnostico->term;
                            $codigo = $row['id_snomed_medicamento'];
                        }
                    }
                    $i++;
                    echo '<tr>';
                    echo '<td>'.$codigo.'</td>';
                    echo '<td>'.$diagnostico_mostrar.'</td>';
                    echo '<td style="text-align: center">'.$row['cantidad'].'</td>';
                    echo '<td  style="text-align: center">'.$row['dosis_diaria'].'</td>'; 

                    echo '</tr>';
                }
            

            ?>
    
        </tbody>
    </table>

<h2>Diagnosticos</h2>
        <table style="width: 100%;">
        <tbody>
            <thead style="background-color: lightblue; color:white;">
            	<th style="width: 20%">Código</th>
            	<th style="width: 40%">Diagnostico</th>
            	<th style="width: 10%">Tipo</th>
            </thead>
           <tbody >   

                <?php
                foreach ($model->diagnosticoConsultas as $row) {
                    $diagnostico = common\models\Cie10::findOne(['codigo'=>$row['codigo']]);
                    if (is_object($diagnostico)) {
                        $diagnostico_mostrar = $diagnostico->diagnostico;
                    } else {
                        $diagnostico = common\models\snomed\SnomedProblemas::findOne(['conceptId'=>$row['codigo']]);
                        if (is_object($diagnostico)) {
                            $diagnostico_mostrar = $diagnostico->term;
                        }
                    }
                    echo '<tr>';
                    echo '<td>'.$row['codigo'].'</td>';
                    echo '<td>'.$diagnostico_mostrar.'</td>';
                    if($row['tipo_diagnostico']==='P'){
                    	echo '<td> Primario</td>';
                    }elseif($row['tipo_diagnostico']==='S'){
                        echo '<td> Secundario</td>';
                    }else{
                           echo '<td> Sin Especificar</td>';
                        }
                    echo '</tr>';

                }

                ?>


                <tr>
                	<td></br></br></br><b>Profesional:</b><?= $model->getProfesional($model->id_turnos); ?></td>
                </tr>
                <tr>
                	<td><b>Matricula:</b>...............</td>
                </tr>
   
   				<tr>
                	<td colspan="2" style="text-align: right;"><b>Firma: </b>_____________________</td>
                </tr>
        </tbody>
    </table>
 
<script type="text/javascript">
	window.print();
</script>