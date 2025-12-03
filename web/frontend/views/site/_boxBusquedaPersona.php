        <div class="col-lg-4">
                <div class="card portfolio-card bg-info">
                    <div class="card-header">
                        <div class="header-title">
                            <div class="row">
                                <div class="col-md-12">
                                    <h4>Busqueda de Personas</h4>
                                </div>                               
                            </div>                           
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <form class="form">
                                <div class="form-group row">
                                    <label class="control-label col-sm-3 align-self-center mb-0" for="documento">DNI:</label>
                                    <div class="col-sm-9">
                                    <input type="text" class="form-control" id="documento" placeholder="DNI">
                                    </div>
                                </div>  
                                <div class="form-group row">
                                    <label class="control-label col-sm-3 align-self-center mb-0" for="nombre">Nombre:</label>
                                    <div class="col-sm-9">
                                    <input type="text" class="form-control" id="nombre" placeholder="Nombre">
                                    </div>
                                </div> 
                                <div class="form-group row">
                                    <label class="control-label col-sm-3 align-self-center mb-0" for="apellido">Apellido:</label>
                                    <div class="col-sm-9">
                                    <input type="text" class="form-control" id="apellido" placeholder="Apellido">
                                    </div>
                                </div>                                
                                
                                <div class="form-group d-flex justify-content-end">
                                    <button type="button" id="btn_buscar" class="p-2 btn btn-primary">
                                        Buscar <svg class="ms-2" width="18" viewBox="0 0 18 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12.9785 3.53978L13.0276 12.0773L4.48926 12.0903" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.4" d="M13.0263 12.0773L2.38157 1.50895" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                    </button> 
                                    <!--button type="button" onclick="javascript:buscarPersona()" class="btn btn-primary">Buscar</button-->
                                </div>
                            </form>
                        </div>
                        <div id="personas-container"></div>                     

                    </div>
                    <div class="card-footer">
                        <div class="col-md-12">                           
                           <div class="iq-loader-box">
                              <div class="iq-loader-8"></div>
                           </div>
                        </div>
                    </div>
                </div> 
        </div>

        <?php

$url = yii\helpers\Url::toRoute('personas/buscarhome');

$this->registerJs('

$(".iq-loader-box").hide();

$("#documento").keypress(function (ev) {
    var keycode = (ev.keyCode ? ev.keyCode : ev.which);
    console.log(keycode);
    if (keycode == 13) {
        buscarPersona();
    }
});
$("#nombre").keypress(function (ev) {
    var keycode = (ev.keyCode ? ev.keyCode : ev.which);
    console.log(keycode);
    if (keycode == 13) {
        buscarPersona();
    }
});
$("#apellido").keypress(function (ev) {
    var keycode = (ev.keyCode ? ev.keyCode : ev.which);
    console.log(keycode);
    if (keycode == 13) {
        buscarPersona();
    }
});
function buscarPersona(){   
    var csrfToken = $(\'meta[name="csrf-token"]\').attr("content");
    
    var dni = $("#documento").val();
    var apellido = $("#apellido").val();
    var nombre = $("#nombre").val();

    let regDni = /^[\d]{1,3}?[\d]{3,3}?[\d]{3,3}$/;

    if(dni.length > 0 && !dni.match(regDni)){
        alert("Por favor, ingrese un dni valido.");
        $("#documento").focus();
        return false;
    }

    if(nombre.length>0 && nombre.length < 3){
        alert("Por favor, ingrese un nombre valido.");
        $("#nombre").focus();
        return false;

    }

    if(apellido.length>0 && apellido.length < 3){
        alert("Por favor, ingrese un apellido valido.");
        $("#apellido").focus();
        return false;

    }
    
    if (dni || apellido || nombre) {
        $(".iq-loader-box").show();
        $.ajax({
            type:"POST",
            cache:false,
            url: "'.$url.'",
            data:{
                documento: dni,
                nombre: nombre,
                apellido: apellido,
                _crsf: csrfToken,
            },
            success:function(data){           
                $("#personas-container").html(data);
                $(".iq-loader-box").hide();
            },
        });
    }
}

$("#btn_buscar").on("click", function () {
    buscarPersona();
  });

');
?>