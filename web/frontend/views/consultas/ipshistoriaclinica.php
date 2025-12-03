<style>
      .body-spin {
        margin-top: 10px;
        text-align: center;
      }

      .spin {
        display: inline-block;
        width: 50px;
        height: 50px;
        border: solid 5px #cfd0d1;
        border-bottom-color: #1c87c9;
        border-radius: 50%;
        border-top-color: #1c87c9;
        animation: spin 1s ease-in-out infinite;
        -webkit-animation: spin 1s ease-in-out infinite;
      }
      @keyframes spin {
        to {
          -webkit-transform: rotate(360deg);
        }
      }
      @-webkit-keyframes spin {
        to {
          -webkit-transform: rotate(360deg);
        }
      }
    </style>

<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Consulta;
use yii\bootstrap5\Tabs;
use yii\helpers\Url;

$this->title = 'Historia Clinica IPS';
$this->params['breadcrumbs'][] = $this->title;
$cons = new Consulta;
?>

<script>
// En el onload
$(function() {
    verDominios(<?php echo Yii::$app->getRequest()->getQueryParam('id');?>);
});
</script>
<div id="spinner" class="body-spin" ><div class="spin"></div></div>
<div class="consulta-index">
<!--<br>
    <button onclick="verDominios(<?php //echo Yii::$app->getRequest()->getQueryParam('id');?>)">Dominios</button>
<br>-->
</div>

    <!-- DOMINIOS -->
    <div id="dominios" style="display:none;">
        <div class="alert alert-info" role="alert">
            <div class="row">
                <div class="col-md-12">Dominios registrados</div>
            </div>
        </div>
        <div id="dominios-list" >
        </div>
    </div>

    <!-- MENSAJE -->
    <div id="mensaje" style="display:none;">
    </div>

    <!-- BOTON VOLVER DOMINIO -->

    <div>
        <button id="btn-volver" style="display:none;" onclick="document.getElementById('dominios').style.display = 'block'; document.getElementById('history').style.display = 'none'; document.getElementById('btn-volver').style.display = 'none'; document.getElementById('mensaje').style.display = 'none'">Volver a dominios</button>
    </div>


    <!-- HISTORIA CLINICA -->
    <!--<div id="history" style="display:none;">-->
    <div id="history" style="display:none;">
        <!--RESUMEN-->
        <div class="alert alert-info" role="alert"><div class="row">
            <div class="col-md-12" id="composition-body"></div>
        </div></div>

        <!-- DATOS PACIENTE-->
        <div class="row">
            <div class="col-md-12" id="patient-body">
            </div>
        </div>

        <div class ="row">
            <!-- ROW #2 --->    
            <div class="row col-md-12" style="margin: 15px;">
                <!-- ALERGIAS -->
                <div class="col-md-6" id="Allergies">
                    <header>
                        <span><i class="glyphicon glyphicon-eye-open"></i>&nbsp;<b>Alergias e Intolerancias</b></span>
                    </header>
                    <div id="Allergies-body" style="display:none;">
                    </div>
                </div>
                
                <!-- PROBLEMAS --->
                <div class="col-md-6 output" id="Problems">
                    <header>
                        <span><i class="glyphicon glyphicon-heart"></i>&nbsp;<b> Diagnósticos / Problemas activos</b></span>
                    </header>
                    <div id="problems-body" style="display:none;">
                    </div>
                </div>
            </div>

            <!-- ROW #3 --->    
            <div class="row col-md-12" style="margin: 15px;">
                <div class="col-md-12 output" id="Medications">
                    <header>
                        <span><i class="glyphicon glyphicon-barcode"></i>&nbsp;<b>Medicamentos activos</b></span>
                    </header>
                    <div id="medications-body" style="display:none;">
                    </div>
                </div>
            </div>
            
            <!-- ROW #4 --->        
            <div class="row col-md-12" style="margin: 15px;">

            <!--INMUNIZACIONES -->
            <div class="col-md-12 output" id="Immunizations">
                <header>
                    <span><i class="glyphicon glyphicon-pushpin"></i>&nbsp;<b>Vacunas</b></span>
                </header>
                <div id="immunizations-body" style="display:none;">
                </div>
            </div>
            </div>
        </div>
    </div>



<script type="text/javascript">
function verDominios(id){
    $.ajax({
        type: "POST",
        url: '<?php echo Url::to(['consultas/ips-patient-location']); ?>?id=' + id,
        success: function(data)
        {            
            var content = JSON.parse(data);
            console.log(content);

            var url = "--";
            var html = '';
          //  document.getElementById('spinner').style.display = 'none';
            if (content.statusCode == 404) {

                html = '<div class="alert alert-info" role="alert"><div class="row">';
                html = html + '<div class="col-md-12">No se encuentra empadronado el paciente para consultar IPS</div></div></div>';
                document.getElementById('mensaje').style.display = 'block';
                $('#mensaje').html(html);
            }
            else if (content.total == 0) {
                html = '<div class="alert alert-info" role="alert"><div class="row">';
                html = html + '<div class="col-md-12">No se encontraron datos del paciente</div></div></div>';
                document.getElementById('mensaje').style.display = 'block';
                $('#mensaje').html(html);

            }
            else if (typeof content.issue !== "undefined" && content.issue[0].severity == "error") {
                html = '<div class="alert alert-info" role="alert"><div class="row">';
                html = html + '<div class="col-md-12">Se produjo un error al consultar los dominios</div></div></div>';
                document.getElementById('mensaje').style.display = 'block';
                $('#mensaje').html(html);
            }
            else  {
                html = '<ul class="list-group">';
                //RECORREMOS DOMINIOS
                $.each(content.entry, function(id,value){  
                    $.each(value.resource.identifier, function(id,list){
                        if (list.system == "https://federador.msal.gob.ar/uri") {
                            console.log(list);
                            url = list.value;
                        }
                    });                
                    console.log(value.resource.name);

                    //armamos html 
                    html = html + '<li class="list-group-item">' + value.resource.name;
                    html = html + '<button class="badge badge-primary" onclick="verHistoriaClinica(' + "'" + url + "'" + ')">' + url + '</button></li>' ;

                });

                html = html + '</ul>';
                document.getElementById('dominios').style.display = 'block';
                $('#dominios-list').html(html);
            }


        }
    });
    return false; // Evitar ejecutar el submit del formulario.
}
</script>          

<script type="text/javascript">
function verHistoriaClinica(dominio){

  document.getElementById('dominios').style.display = 'none';
  //document.getElementById('spinner').style.display = 'block';
  $.ajax({
            type: "POST",
            url: '<?php echo Url::to(['consultas/ips-document-reference'])?>/412/' + dominio,
          //data:{ id:1,dominio:'com'},
            success: function(data)
            {
                console.log(data);
                var content = JSON.parse(data);

                if (content==null || typeof content.entry=="undefined") {
                    html = '<div class="alert alert-info" role="alert"><div class="row">';
                    html = html + '<div class="col-md-12">No se puedo obtener la historia clinica del paciente</div></div></div>';
                    document.getElementById('mensaje').style.display = 'block';
                    $('#mensaje').html(html);
                    //document.getElementById('spinner').style.display = 'none';
                    document.getElementById('btn-volver').style.display = 'block';

                    return false;
                }

                console.log(content.entry);
                console.log(content.entry[0].resource.content[0].attachment.url);
                var attach_url = content.entry[0].resource.content[0].attachment.url;
    

                $.ajax({
                    type: "POST",
                    url: '<?php echo Url::to(['consultas/ips-bundle']);?>&content='+ attach_url ,
                    //data:{ id:1,dominio:'com'},
                    success: function(data)
                    {                
                        var titulo = "";
                        var fecha = "";
                        var dominio = "";
                        var identificadores = "";
                        var tel="";
                        var vacunas= "";
                        var problemas = "";
                        var medicaciones = "";
                        var tcom = "";
                        var tpos = "";

                        var content = JSON.parse(data);
                        console.log(content.entry);

                        //document.getElementById('spinner').style.display = 'none';
                        document.getElementById('btn-volver').style.display = 'block';
                        
                        //RECORREMOS HISTORIA CLINICA
                        $.each(content.entry, function(id,value){
                            console.log(value);
                        
                            //Composition
                            if (value.resource.resourceType == 'Composition') {
                                titulo = value.resource.title ;
                                fecha = value.resource.date;
                                dominio = 'Hospital';
                            }

                            //Organization
                            if (value.resource.resourceType == 'Organization') {
                                dominio = value.resource.name;
                                if (typeof value.resource.address !== 'undefined') {
                                    dominio = dominio +', ' + value.resource.address[0].city;
                                    dominio = dominio +', ' + value.resource.address[0].country;                            
                                }
                            }

                            //Patient
                            if (value.resource.resourceType == 'Patient'){
                                fecnac = value.resource.birthDate;
                                nombre = value.resource.name[0].text;
                                console.log(value.resource.telecom );
                                if (typeof value.resource.telecom !== 'undefined')
                                    tel = value.resource.telecom[0].value;
                                $.each(value.resource.identifier, function(id,ident){
                                    identificadores = identificadores + ident.system + ': ' + ident.value+'<br>';
                                });
                            }

                            //Immunization
                            if (value.resource.resourceType == 'Immunization'){
                            $.each(value.resource.vaccineCode.coding, function(id,list){
                                //<!--<span class="badge badge-primary">undefined</span>-->
                                vacunas = vacunas + list.display + '<br>';
                                });
                            }

                            //Allergies
                            if (value.resource.resourceType == 'AllergyIntolerance'){
                                if (value.resource.criticality=='high') 
                                    alergias = "<span class='badge badge-danger'>Criticidad: Alta</span><br>";
                                else 
                                    alergias = "";
                                //    alergias = "<span class='badge badge-primary'>allergy - medication - Criticidad: " + value.resource.criticality +"</span><br>";
                                $.each(value.resource.code.coding, function(id,list){
                                    alergias = alergias + list.display + ' ('+ list.code + ')<br>';
                                });
                            }
                        
                            //Condition
                            if (value.resource.resourceType == 'Condition'){
                                $.each(value.resource.code.coding, function(id,list){
                                    problemas = problemas + list.display + ' ('+ list.code + ')<br>';
                            });

                            }

                            //Medications
                            if ((value.resource.resourceType == 'Medication')  || (value.resource.resourceType == 'MedicationStatement')){
                                if (typeof value.resource.medicationCodeableConcept !=='undefined') { //validamos primero si existe esta seccion

                                    $.each(value.resource.medicationCodeableConcept.coding, function(id,list){
                                        medicaciones = medicaciones + list.display + ' ('+ list.code + ')<br>';
                                    });
                                    if (typeof value.resource.medicationCodeableConcept.ingredient !== 'undefined'){  //existe??
                                        //tabla composicion
                                        tcom = '<table class="table table-bordered table-sm"><thead><tr><th colspan="5">Composición</th></tr><tr>';
                                        tcom = tcom + '<th scope="col">Ingrediente</th><th scope="col">Numerador Cantidad</th><th scope="col">Numerador Unidad</th>';
                                        tcom = tcom + '<th scope="col">Denominador Cantidad</th><th scope="col">Denominador Unidad</th></tr></thead><tbody><tr>';

                                        $.each(value.resource.code.ingredient.code.coding, function(id,list){
                                            ingredientes = ingregientes + list.display + '<br>';
                                        });
                                        
                                        tcom = tcom + '<td>' + ingredientes + '</td>'
                                        tcom = tcom + '<td>200</td>';
                                        tcom = tcom + '<td>mcg</td>';
                                        tcom = tcom + '<td>1</td>';
                                        tcom = tcom + '<td>disparo</td></tr></tbody></table>';

                                        //tabla posologia
                                        tpos = '<table class="table table-bordered table-sm"><thead><tr><th colspan="5">Posología</th></tr><tr>';
                                        tpos = tpos + '<th scope="col">Via de administración</th><th scope="col">Cantidad</th><th scope="col">Unidad</th>';
                                        tpos = tpos + '<th scope="col">Frecuencia cantidad</th><th scope="col">Frecuencia período</th></tr></thead><tbody><tr>';
                                        tpos = tpos + '<td>vía de administración en el tracto respiratorio (calificador)</td>';
                                        tpos = tpos + '<td>1</td>';
                                        tpos = tpos + '<td>disparo (unidad de presentación)</td>';
                                        tpos = tpos + '<td>1</td>';
                                        tpos = tpos + '<td>d</td></tr></tbody></table>';
                                    }
                                } else {
                                    medicaciones = "Sin informacion";
                                }

                            }

                        });

                        document.getElementById('history').style.display = 'block';

                        //armamos html encabezado
                        htmlResumen =  '<strong>' + titulo + '</strong><br>';
                        htmlResumen = htmlResumen + '<strong>Fecha del Resumen: </strong>' + fecha + '&nbsp;&nbsp;&nbsp;';
                        htmlResumen = htmlResumen + '<strong>Dominio: </strong>' + dominio ;                
                        $('#composition-body').html(htmlResumen);

                        //armamos html patient
                        /*html = '<h5>' + nombre + '</h5><p>Fecha de Nacimiento: '+ fecnac + '<br>';
                        if (tel !=="")
                            html = html + 'Contacto: <i class="glyphicon glyphicon-earphone"></i>' + tel + '<br>';
                        html = html + 'Identificadores:<br><span class="small">';
                        html = html + identificadores + '</span></p>';    */          

                        html = '<table id="w0" class="table table-striped table-bordered detail-view"><tbody>';                
                        html = html + '<tr><th>Nombre</th><td>' + nombre +'</td></tr>';
                        html = html + '<tr><th>Fecha Nacimiento</th><td>' + fecnac +'</td></tr>';
                        if (tel !=="")
                            html = html + '<tr><th>Contacto</th><td><i class="glyphicon glyphicon-earphone"></i>' + tel +'</td></tr>';
                        html = html + '<tr><th>Identificadores</th><td><span class="small">' + identificadores +'</span></td></tr>';
                        html = html + '</tbody></table>';

                        $('#patient-body').html(html);

                        //armamos html inmunizations
                        html =  "<ul class='list-group'><li class='list-group-item'>" + vacunas + "</li></ul>";
                        $('#immunizations-body').html(html);
                        
                        //armamos html allergies
                        html =  "<ul class='list-group'><li class='list-group-item'>" + alergias + "</li></ul>";
                        $('#Allergies-body').html(html);

                        //armamos html condition
                        html =  "<ul class='list-group'><li class='list-group-item'>" + problemas + "</li></ul>";
                        $('#problems-body').html(html);

                        //armamos html medicamentos
                        html = '<ul class="list-group"><li class="list-group-item">';//<span class="badge badge-primary">http://snomed.info/sct</span>';
                        html = html + medicaciones;
                        html = html + tcom + tpos;
                        html = html + '</li></ul>';
                        $('#medications-body').html(html);



                        //habilitamos los bloques
                        document.getElementById('medications-body').style.display = 'block';
                        document.getElementById('Allergies-body').style.display = 'block';
                        document.getElementById('problems-body').style.display = 'block';
                        document.getElementById('immunizations-body').style.display = 'block';
                        document.getElementById('btn-volver').style.display = 'block';

                    }
                });
            }
    });
  return false; // Evitar ejecutar el submit del formulario.
}
</script>