    
var indiceC = $('#indiceC').val();
var indiceP = $('#indiceP').val();
var indiceO = $('#indiceO').val();
var indiceCPO =  0;

// la pieza seleccionada que lanza el modal
let piezaSeleccionada = null;

// los botones para seleccionar las caras
// de acuerdo al sector las caras cambian de nombre
let botonesCaras = [
    // arriba izquierda
    {'izq': 'distal', 'dere': 'mesial', 'arriba': 'vestibular', 'abajo': 'palatina'},
    // arriba derecha
    {'izq': 'mesial', 'dere': 'distal', 'arriba': 'vestibular', 'abajo': 'palatina'},
    // abajo derecha
    {'izq': 'mesial', 'dere': 'distal', 'arriba': 'lingual', 'abajo': 'vestibular'},
    // abajo izquierda
    {'izq': 'distal', 'dere': 'mesial', 'arriba': 'lingual', 'abajo': 'vestibular'},
    // TEMPORALES
    // arriba izquierda
    {'izq': 'distal', 'dere': 'mesial', 'arriba': 'vestibular', 'abajo': 'palatina'},
    // arriba derecha
    {'izq': 'mesial', 'dere': 'distal', 'arriba': 'vestibular', 'abajo': 'palatina'},
    // abajo derecha
    {'izq': 'mesial', 'dere': 'distal', 'arriba': 'lingual', 'abajo': 'vestibular'},
    // abajo izquierda
    {'izq': 'distal', 'dere': 'mesial', 'arriba': 'lingual', 'abajo': 'vestibular'},
];

// variable que establece los nombres de las caras de para todo el context
// se setea cuando se lanza el modal y queda disponible para su uso
let caras;

// la cara del centro cambia de nombre de acuerdo si se trata de un molar o no
let botonesCentroMolares = [
    // del 1 al 3
    'incisal',
    // del 4 al 8
    'oclusal'
];
// variable que establece el nombre del centro para todo el context
// se setea cuando se lanza el modal y queda disponible para su uso
let centro;

// estado seleccionado
let codigoEstadoSeleccionado;
// tipo de estado seleccionado, estado que representa un diagnostico 
// o un mero estado
let tipoEstadoSeleccionado;
// dentro del modal, la/s caras que va seleccionado
// para aplicar las practicas
let carasSeleccionadas = [];

// caras que aplican unicamente a los estados
let carasEstadosSeleccionadas = [];

// estados que se pueden convivir
let estadosMultiples = ['RE', 80967001];
let estadosQueAdmitenCaras = ['RE', 80967001];
let estadosQueGeneranDiagnosticoPracticas = ['IE', 80967001];

// para las practicas, el rgb del fill cambia si se trata de una practica actual o futura
const fillAzul = "rgb(8, 177, 186)";
const fillBlanco = "rgb(255, 255, 255)";
const fillGrey = "rgb(172, 164, 188)";
const fillRojo = "rgb(192, 50, 33)";

// la accion que desea aplicar sobre la pieza o cara/s
let accionSeleccionada = {
        'accion': null, // estado, diagnostico o practica ?
        'idAccion': null, // codigo o id del nomenclador
        'term': null, // si viene de snomed
        'soloPieza': true, // aplica solamente a toda la pieza ?
        'limiteCaras': 0
}

let diagnosticoSeleccionado = {    
    'codigo': null, // codigo snomed
    'term': null, // detalle del codigo snomed
    'soloPieza': true, // aplica solamente a toda la pieza ?
    'limiteCaras': 0,
    'caras': []
}

let diagnosticosSeleccionados = [];

// la accion que desea aplicar sobre la pieza o cara/s
let practicaSeleccionada = {
    'codigoDiagnostico': null, // el diagnostico con el que se asocia la practica
    'codigo': null, // codigo nomenclador
    'term': null, // detalle del codigo snomed si aplica
    'soloPieza': true, // aplica solamente a toda la pieza ?
    'limiteCaras': 0,
    'caras': []
}

let practicasSeleccionadas = [];

$( document ).ready(function() {

    // Para mostrar/ocultar la dentision temporal    
    $(document).on('click', '#boton_mostrar_temporal', function(e) {
        
        $(".hstack_temporal").toggleClass("hidden");
        if ($(".hstack_temporal").hasClass("hidden")) {
            $("#boton_mostrar_temporal").html("MOSTRAR DENTICIÓN TEMPORAL");
            $("#boton_mostrar_temporal").addClass("btn-success");
            $("#boton_mostrar_temporal").removeClass("btn-warning");
        } else {
            $("#boton_mostrar_temporal").html("OCULTAR DENTICIÓN TEMPORAL");
            $("#boton_mostrar_temporal").removeClass("btn-success");
            $("#boton_mostrar_temporal").addClass("btn-warning");            
        }
    });

    // el click en la pieza del odontograma, esto lanza al modal
    $(document).on('click', '.svg_pieza_chica', function(e) {
        e.preventDefault;

        piezaSeleccionada = $(this).data("id_pieza");

        var primerDigito = parseInt(piezaSeleccionada / 10);
        caras = botonesCaras[primerDigito - 1];

        var segundoDigito =  piezaSeleccionada % 10;
        
        if (segundoDigito <= 3) {
            centro = botonesCentroMolares[0];
        } else {
            centro = botonesCentroMolares[1];
        }

        limpiarDatos();

        // crea el html de la pieza para el tab de estados
        $("#div_pieza_estados").html(crearPieza("pieza_completa_estados"));
        // si esta pieza tiene guardado un estado, pintarEstado lo pinta
        pintarEstado(); 

        // crea el html de la pieza para el tab de diagnosticos
        $("#div_pieza_diagnosticos").html(crearPieza("pieza_diagnosticos"));

        let diagnosticosDeEstaConsulta = buscarDiagnosticos();

        for (let index = 0; index < diagnosticosDeEstaConsulta.length; index++) {
            agregarFormPracticas(diagnosticosDeEstaConsulta[index]);
        }

        const tooltipTriggerList = document.querySelectorAll(".pieza_parte");
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        $("#modal-pieza_completa").modal("show");
        $("#modal-pieza_completa-label").html("Pieza N° " + piezaSeleccionada);

        if (tieneContrapartidaDefinitiva(piezaSeleccionada)) {
            $(".msj_previo_guardar").html("La pieza ya tiene cargadas practicas/estados en su pieza definitiva");
            $(".msj_previo_guardar").removeClass("hidden");
        } else {
            $(".msj_previo_guardar").html("");
            $(".msj_previo_guardar").addClass("hidden");
        }
    });

    // ESTADOS
    // Pinta la pieza con el estado seleccionado, pero no confirma aun
    $(document).on('click', '.checkbox-estado', function(e) {
       
        // reseteo las caras
        /*for (let index = 0; index < estadosQueAdmitenCaras.length; index++) {
            carasEstadosSeleccionadas[estadosQueAdmitenCaras[index]] = [];        
        }*/

        carasEstadosSeleccionadas = [];

        $(".pieza_completa_estados path").attr("fill", fillBlanco);
        $(".pieza_completa_estados ellipse").attr("fill", fillBlanco);

        codigoEstadoSeleccionado = $(this).data("codigo-estado");
        tipoEstadoSeleccionado = (typeof codigoEstado["estadosDiagnostico"][codigoEstadoSeleccionado] == "undefined") ? "estadosEstados" : "estadosDiagnostico";

        let pideSeleccionDeCaras = estadosQueAdmitenCaras.some((el) => el == codigoEstadoSeleccionado);
        if (pideSeleccionDeCaras) {
            
            $(".pieza_completa_estados > #simboloAgregado").remove();
            
            $("#ayuda_estados").show();
            $("#ayuda_estados").html("Seleccione las caras");

            $("#btn-agregar-estado").html('Establecer estado');
            $("#btn-agregar-estado").removeClass('btn-danger');
            $("#btn-agregar-estado").addClass('btn-success');            
            $("#btn-agregar-estado").attr('disabled', true);
        } else {
            $("#btn-agregar-estado").html('Establecer estado');
            $("#btn-agregar-estado").removeClass('btn-danger');
            $("#btn-agregar-estado").addClass('btn-success');
            $("#btn-agregar-estado").attr('disabled', false);

            $("#div_botones_caras").html("");
            $("#ayuda_estados").hide();

            // el estado no requiere seleccion de cara
            agregarSimbolo(".pieza_completa_estados", 'simboloAgregado', codigoEstado[tipoEstadoSeleccionado][codigoEstadoSeleccionado].path);
        }        

        let found = buscarEstado(codigoEstadoSeleccionado);
        if (found !== false) {
            $("#btn-agregar-estado").html('Quitar Estado');
            $("#btn-agregar-estado").removeClass('btn-success');
            $("#btn-agregar-estado").addClass('btn-danger');
            let pideSeleccionDeCaras = estadosQueAdmitenCaras.some((el) => el == codigoEstadoSeleccionado);
            if (pideSeleccionDeCaras) {
                let rgb = codigoEstadoSeleccionado == 'RE' ? fillRojo : fillAzul;
                for (let index = 0; index < found.caras.length; index++) {
                    $(".pieza_completa_estados [data-parte="+found.caras[index].toLowerCase()+"]").attr("fill", rgb);                    
                }
            }
            $("#btn-agregar-estado").attr('disabled', false);
        }
    });

    /**
     * En estados, si tiene una seleccionada, luego puede hacer click en las caras
     */
    $(document).on('click', '.pieza_completa_estados .pieza_parte, .checkbox-boton-cara', function(e) {    
        e.preventDefault;

        if (piezaSeleccionada === null) return;

        if (codigoEstadoSeleccionado === null) return;
        // solo ciertos estados admiten la seleccion de caras
        let pideSeleccionDeCaras = estadosQueAdmitenCaras.some((el) => el == codigoEstadoSeleccionado);
        if (!pideSeleccionDeCaras) return;

        let yaEstaAgregado = buscarEstado(codigoEstadoSeleccionado);

        // si el estado ya estaba agregado no permito que pinte mas caras, que quite el estado y lo vuelva a agregar
        if (yaEstaAgregado !== false) return;

        const caraSeleccionada = $(this).data("parte");

        let yaEnOtroEstado = false;
        // para los estados que admiten seleccion de caras, que no sean el actualmente seleccionado
        // revisamos que la cara ya no este tomada
        for (let index = 0; index < estadosQueAdmitenCaras.length; index++) {
            if (estadosQueAdmitenCaras[index] !== codigoEstadoSeleccionado) {
                // otroEstadoConCaras objeto estado
                let otroEstadoConCaras = buscarEstado(estadosQueAdmitenCaras[index]);
                if (otroEstadoConCaras) {
                    let esta = otroEstadoConCaras.caras.some(c => c === caraSeleccionada);
                    if (esta) {
                        yaEnOtroEstado = true;
                    }
                }
            }
        }

        // la cara ya esta seleccionada para otro estado
        if (yaEnOtroEstado) return;

        let found = carasEstadosSeleccionadas.some(el => el === caraSeleccionada);

        if (found) {
            // si ya estaba la quito y pinto de blanco la cara
            $(this).attr("fill", fillBlanco);
            //carasEstadosSeleccionadas[codigoEstadoSeleccionado] = carasEstadosSeleccionadas[codigoEstadoSeleccionado].filter(el => el !== caraSeleccionada);
            carasEstadosSeleccionadas = carasEstadosSeleccionadas.filter(el => el !== caraSeleccionada);
        } else {
            // la agrego si no llega al limite
            $(this).attr("fill", (codigoEstadoSeleccionado == 'RE' ? fillRojo : fillAzul));
            //carasEstadosSeleccionadas[codigoEstadoSeleccionado].push(caraSeleccionada);
            arasEstadosSeleccionadas = carasEstadosSeleccionadas.push(caraSeleccionada);
        }
        // habilito/deshabilito el boton de agregar
        if (carasEstadosSeleccionadas.length > 0) {
            $("#btn-agregar-estado").attr('disabled', false);
        } else {
            $("#btn-agregar-estado").attr('disabled', true);
        }

    });

    // Confirma agregar el estado seleccionado
    $(document).on('click', '#btn-agregar-estado', function(e) {

        e.preventDefault;            
        
        if (tieneContrapartidaDefinitiva(piezaSeleccionada)) {
            return;
        } 

        let yaEstaAgregado = buscarEstado(codigoEstadoSeleccionado);

        let generoDiagnosticoPractica = estadosQueGeneranDiagnosticoPracticas.some((el) => el == codigoEstadoSeleccionado);
        let estadoMultiple = estadosMultiples.some((el) => el == codigoEstadoSeleccionado);

        // Pregunto si hace click en Quitar estado
        if (yaEstaAgregado !== false) {

            quitarEstado(yaEstaAgregado);
            
            // si este estado, al ser agregado genero diagnosticos y/o practicas, los quitamos tambien            
            if (generoDiagnosticoPractica) {
                let d = buscarDiagnostico(codigoEstado['estadosDiagnostico'][codigoEstadoSeleccionado]['diagnostico']);
                if (d !== false) {
                    quitarDiagnostico(d);
                    quitarPracticasPorDiagnostico(d);                
                }
            }

            // cambio el texto y color del boton
            $("#btn-agregar-estado").html('Establecer estado');
            $("#btn-agregar-estado").removeClass('btn-danger');
            $("#btn-agregar-estado").addClass('btn-success');

/*            $("span[data-span_pieza="+piezaSeleccionada+"]").html(piezaSeleccionada);
            $("span[data-span_pieza="+piezaSeleccionada+"]").removeClass("text-success");*/            
            actualizarHistorialDiagnosticos();
            actualizarHistorialPracticas();
            guardarDatos();
            return;
        }

        // * estados que pisan a los demas
        // * estados que se permiten junto con otros estados
        if (!estadoMultiple) {
            // el estado seleccionado es unico, quito todos los que existian antes            
            quitarEstadoParaConsulta();
         
            // agrego el nuevo seleccionado
          /*  estadosAgregados.push({
                'pieza' : piezaSeleccionada,
                'caras' : carasEstadosSeleccionadas,
                'codigo' : codigoEstadoSeleccionado,
                'tipo': 'PIEZA',
            });

            actualizarHistorialDiagnosticos();
            actualizarHistorialPracticas();
    
            $("#btn-agregar-estado").html('Quitar Estado');
            $("#btn-agregar-estado").removeClass('btn-success');
            $("#btn-agregar-estado").addClass('btn-danger');
    
            guardarDatos();
            return;*/
        }

        // hasta aqui llegan los estados que se permiten varios por consulta        
        estadosAgregados.push({
            'pieza' : piezaSeleccionada,
            'caras' : carasEstadosSeleccionadas,
            'codigo' : codigoEstadoSeleccionado,
            'tipo': 'PIEZA',
        });

        if (codigoEstadoSeleccionado == 80967001) {
            diagnosticos.push({
                'pieza': piezaSeleccionada,
                'caras': carasEstadosSeleccionadas,
                'codigo': codigoEstadoSeleccionado,
                'term':  codigoEstado['estadosDiagnostico'][80967001]['term'],
                'tipo': 'CARAS',
            });
            agregarFormPracticas(diagnosticos[diagnosticos.length - 1]);
        }
        if (codigoEstadoSeleccionado == 'IE') {
            diagnosticos.push({
                'pieza': piezaSeleccionada,
                'caras': [],
                'codigo': codigoEstado['estadosDiagnostico']['IE']['diagnostico'],                
                'term':  codigoEstado['estadosDiagnostico']['IE']['diagnostico_term'],
                'tipo': 'PIEZA',
            });
            practicas.push({
                'pieza': piezaSeleccionada,
                'caras': [],
                'codigo': codigoEstado['estadosDiagnostico']['IE']['practica'],
                'diagnostico': codigoEstado['estadosDiagnostico']['IE']['diagnostico'],
                'term':  codigoEstado['estadosDiagnostico']['IE']['practica_term'],
                'tipo': 'PIEZA',
                'tiempo': 'FUTURA'
            });
            agregarFormPracticas(diagnosticos[diagnosticos.length - 1]);
        }
    
        actualizarHistorialDiagnosticos();
        actualizarHistorialPracticas();

        $("#btn-agregar-estado").html('Quitar Estado');
        $("#btn-agregar-estado").removeClass('btn-success');
        $("#btn-agregar-estado").addClass('btn-danger');

        guardarDatos();
    });

    // DIAGNOSTICOS
    // Cada vez que selecciona una opcion del select2 de diagnosticos
    $(document).on('select2:select', '#select_diagnostico', function(e) {    
        
        // limpio las partes de la pieza
        $(".pieza_diagnosticos path").attr("fill", fillBlanco);
        $(".pieza_diagnosticos ellipse").attr("fill", fillBlanco);

        $("#btn-agregar-diagnostico").attr('disabled', true);        
        $("#btn-agregar-diagnostico").removeClass('btn-success');
        $("#btn-agregar-diagnostico").addClass('btn-light');

        $("#ayuda_diagnosticos").show();
        
        diagnosticoSeleccionado.codigo = e.params.data.id;
        diagnosticoSeleccionado.term = e.params.data.text;
        diagnosticoSeleccionado.caras = [];

        const found = buscarDiagnostico(diagnosticoSeleccionado.codigo);

        if (found) {
            $("#btn-agregar-diagnostico").attr('disabled', true);
            $("#btn-agregar-diagnostico").html('Ya agregada');
            $("#btn-agregar-diagnostico").removeClass('btn-success');
            $("#btn-agregar-diagnostico").addClass('btn-light');       
        } else {            
            $("#btn-agregar-diagnostico").html('Agregar diagnóstico');
        }
    });

    /**
     * Click en las partes de la pieza en diagnosticos
     */
    $(document).on('click', '.pieza_diagnosticos .pieza_parte', function(e) {
        e.preventDefault;
        
        if (piezaSeleccionada === null) return;

        const foundD = buscarDiagnostico(diagnosticoSeleccionado.codigo);        
        
        if (foundD) {
            // El diagnostico ya esta guardado, al tocar las caras no hacemos nada
            return;
        }

        //const caraIdPieza = e.currentTarget.id.split("-");
        const caraSeleccionada = $(this).data("parte");
       
        const found = diagnosticoSeleccionado.caras.some(el => el === caraSeleccionada);

        if (found) {
            // si ya estaba la quito y pinto de blanco la cara
            $(this).attr("fill", fillBlanco);
            diagnosticoSeleccionado.caras = diagnosticoSeleccionado.caras.filter(el => el !== caraSeleccionada);
        } else {
            $(this).attr("fill", fillGrey);
            diagnosticoSeleccionado.caras.push(caraSeleccionada);
        }
        // habilito/deshabilito el boton de agregar
        if (diagnosticoSeleccionado.caras.length > 0) {
            $("#btn-agregar-diagnostico").attr('disabled', false);
            $("#btn-agregar-diagnostico").addClass('btn-success');
            $("#btn-agregar-diagnostico").removeClass('btn-light');
        } else {
            $("#btn-agregar-diagnostico").attr('disabled', true);
            $("#btn-agregar-diagnostico").removeClass('btn-success');
            $("#btn-agregar-diagnostico").addClass('btn-light');
        }
    });

    $(document).on('click', '#btn-agregar-diagnostico', function(e) {    
        if (piezaSeleccionada === null) return;

        if (accionSeleccionada === null) return;
        
        if (tieneContrapartidaDefinitiva(piezaSeleccionada)) {
            return;
        }
    
        const found = buscarDiagnostico(diagnosticoSeleccionado.codigo);        

        // el diagnostico es nuevo
        if (found == false) {

            diagnosticos.push({
                'pieza': piezaSeleccionada,
                'caras': diagnosticoSeleccionado.caras,
                'codigo': parseInt(diagnosticoSeleccionado.codigo),
                'term':  diagnosticoSeleccionado.term,
                'tipo': 'CARAS',
            });

            // para cada diagnostico agregado va un form de practicas en la pestaña de practicas
            agregarFormPracticas(diagnosticoSeleccionado);

            // limpio las partes de la pieza
            $(".pieza_diagnosticos path").attr("fill", fillBlanco);
            $(".pieza_diagnosticos ellipse").attr("fill", fillBlanco);            
        }
    
        $("#select_diagnostico").val(null).trigger('change');
        
        $("#btn-agregar-diagnostico").attr('disabled', true);

        actualizarHistorialDiagnosticos();

        guardarDatos();
    });

    $(document).on('click', '.btn-quitar-diagnostico', function(e) {
        const codigo = $(this).parent().attr('id');

        // quito diagnostico
        let d = buscarDiagnostico(codigo);
        quitarDiagnostico(d);
        
        // quito practicas        
        quitarPracticasPorDiagnostico(d);

        $("#card_practica_" + codigo).remove(); 

        actualizarHistorialDiagnosticos();
        actualizarHistorialPracticas();
        
        $("#item_diagnostico_practica_" + codigo).remove();

        $("#btn-agregar-diagnostico").attr('disabled', false);
        $("#btn-agregar-diagnostico").html('Agregar diagnóstico');
        $("#btn-agregar-diagnostico").removeClass('btn-warning');
        $("#btn-agregar-diagnostico").removeClass('btn-light');
        $("#btn-agregar-diagnostico").addClass('btn-success');

        guardarDatos();
    });
    
    // PRACTICAS
    // Cada vez que selecciona una opcion del select2 de practicas
    $(document).on('select2:select', '.select_practicas', function(e) {

        // el diagnostico de este select
        let codigoDiagnostico = $(this).data('diagnostico');
        let btnAgregarPractica = $(".btn-agregar-practica[data-diagnostico=" + codigoDiagnostico + "]");

        if ($(this).val() == "") {
            btnAgregarPractica.attr('disabled', true);
            btnAgregarPractica.html('Agregar práctica');
            btnAgregarPractica.removeClass('btn-success');
            btnAgregarPractica.addClass('btn-light');

            return;
        }

        splitIdPracticaSeleccionada = e.params.data.id.split("-");
        console.log($(this).val());
        
        // de la opcion seleccionada obtenemos:
        // * id de practica,
        // * si aplica a toda la pieza o a caras
        // * si aplica a caras, el limite de caras que la practica acepta
        practicaSeleccionada.caras = [];
        practicaSeleccionada.limiteCaras = 0;
        practicaSeleccionada.codigoDiagnostico = codigoDiagnostico;
        practicaSeleccionada.codigo = e.params.data.id;
        practicaSeleccionada.term = e.params.data.text;
       /* let arrayLimiteCaras = splitIdPracticaSeleccionada[0].split("_");

        if (typeof(arrayLimiteCaras[1]) == "undefined") {
            practicaSeleccionada.soloPieza = arrayLimiteCaras[0] == 'pieza' ? true : false;
        } else {
            practicaSeleccionada.soloPieza = arrayLimiteCaras[1] == 'pieza' ? true : false;
            practicaSeleccionada.limiteCaras = arrayLimiteCaras[0];
        }*/
        
        const found = buscarPractica(practicaSeleccionada.codigo, codigoDiagnostico);

        //$(".pieza_completa_practicas path[data-diagnostico='" + codigoDiagnostico + "']").attr("fill", fillBlanco);
        //$(".pieza_completa_practicas ellipse[data-diagnostico='" + codigoDiagnostico + "']").attr("fill", fillBlanco);

        // TODO: buscar si ya esta en el array de practicas
        // si se encuentra colorear las caras que ya haya seleccionado
       /* if (practicaSeleccionada.soloPieza) {
            if (found) {                
                btnAgregarPractica.attr('disabled', true);
                btnAgregarPractica.html('Ya agregada');
                btnAgregarPractica.removeClass('btn-success');
                btnAgregarPractica.addClass('btn-light');
            } else {
                btnAgregarPractica.attr('disabled', false);
                btnAgregarPractica.html('Agregar práctica');
                btnAgregarPractica.removeClass('btn-warning');
                btnAgregarPractica.removeClass('btn-light');
                btnAgregarPractica.addClass('btn-success');
            }

            //$("#ayuda_practicas_" + codigoDiagnostico).html("La practica seleccionada aplica a toda la pieza");
            
            //$(".pieza_completa_practicas path[data-diagnostico='" + codigoDiagnostico + "']").attr("fill", fillGrey);
            //$(".pieza_completa_practicas ellipse[data-diagnostico='" + codigoDiagnostico + "']").attr("fill", fillGrey);
        } else {*/
            // la practica requiere seleccion de caras
            //$("#ayuda_practicas_" + codigoDiagnostico).html("Máximo " + practicaSeleccionada.limiteCaras + " caras");
            if (found !== false) {
                // si la práctica ya estaba agregada pintamos las caras guardadas
                btnAgregarPractica.attr('disabled', true);
                btnAgregarPractica.html('Ya agregada');
                btnAgregarPractica.removeClass('btn-success');
                btnAgregarPractica.addClass('btn-light');

                /*for (let index = 0; index < practicas.length; index++) {
                    if (parseInt(practicas[index].pieza) === piezaSeleccionada && practicas[index].codigo === practicaSeleccionada.codigo) {
                        carasSeleccionadas = practicas[index].caras.split("-");
                    }                        
                }*/
                /*for (let index = 0; index < carasSeleccionadas.length; index++) {
                    $(".pieza_completa_practicas path[data-diagnostico='" + codigoDiagnostico + "'][data-parte="+carasSeleccionadas[index]+"]").attr("fill", fillGrey);                    
                }*/

            } else {                
                btnAgregarPractica.html('Agregar práctica');
                btnAgregarPractica.removeClass('btn-warning');
                btnAgregarPractica.removeClass('btn-light');
                btnAgregarPractica.addClass('btn-success');                    
                btnAgregarPractica.attr('disabled', false);
            }
        //}

        practicasSeleccionadas[codigoDiagnostico] = practicaSeleccionada;
    });

    // confirma la asignacion de la practica seleccionada en el select
    $(document).on('click', '.btn-agregar-practica', function(e) {
        e.preventDefault;
        
        // el diagnostico de este select
        let codigoDiagnostico = $(this).data('diagnostico');

        agregarPractica(codigoDiagnostico);

        actualizarHistorialPracticas();

        guardarDatos();
    });

    $(document).on('click', '.btn-quitar-practica', function(e) {
        const codigo = $(this).parent().attr('id');
        const codigoDiagnostico = $(this).parent().data('diagnostico');
        
        let p = buscarPractica(codigo, codigoDiagnostico);
        quitarPractica(p);

        actualizarHistorialPracticas();

        let btnAgregarPractica = $(".btn-agregar-practica[data-diagnostico=" + p.diagnostico + "]");

        btnAgregarPractica.attr('disabled', false);
        btnAgregarPractica.html('Agregar práctica');
        btnAgregarPractica.removeClass('btn-warning');
        btnAgregarPractica.removeClass('btn-light');
        btnAgregarPractica.addClass('btn-success');

        guardarDatos();
    });

    /**
     * Se pasa la practica desde planificada a realizada y viceversa
     */
    $(document).on('click', '.btn-toggle-tiempo', function(e) {
        const codigo = $(this).parent().attr('id');
        const tiempo = $(this).data("tiempo");

        practicas = practicas.map(el => {
            if (parseInt(el.pieza) === piezaSeleccionada && el.codigo == codigo) {
                el.tiempo = tiempo;
            }
            return el;
        });

        actualizarHistorialPracticas();

        guardarDatos();
    });

    $(document).on('hidden.bs.modal', '#modal-pieza_completa', function(e) {
        pintarPiezasChicas();
    });        
});

// busca en el array de diagnosticos por codigo
// siempre relacionado a la consulta actual
// devuelve false o el elemento
function buscarEstado(codigo)
{
    var estEncontrado = estadosAgregados.find(function(el) {
        if (parseInt(el.pieza) === piezaSeleccionada && el.codigo == codigo) {
            // si idConsulta es 0 es una consulta nueva, entonces en este array el.id_consulta tiene que ser undefined
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta == el.id_consulta) {
                return true;
            }
        }
        return false;
    });

    return typeof(estEncontrado) == "undefined" ? false : estEncontrado;
}

// recibe como parametro un objeto estado
function quitarEstado(estado)
{
    estadosAgregados = estadosAgregados.filter(function(el) {
        if (parseInt(el.pieza) === piezaSeleccionada && el.codigo === estado.codigo) {
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta == el.id_consulta) {
                return false;
            }
        }
        return true;
    });
}

// quita todos los estados creados para esta consulta, para esta pieza
function quitarEstadoParaConsulta()
{
    estadosAgregados = estadosAgregados.filter(function(el) {
        if (parseInt(el.pieza) === piezaSeleccionada) {
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta == el.id_consulta) {
                return false;
            }

            for (let index = 0; index < estadosQueGeneranDiagnosticoPracticas.length; index++) {
                if (estadosQueGeneranDiagnosticoPracticas[index] == el.codigo) {
                    // estoy quitando todos los estados,
                    // si hay estados generadores de diagnosticos y practicas,
                    // los quito tambien
                    let d = buscarDiagnostico(codigoEstado['estadosDiagnostico'][el.codigo]['diagnostico']);
                    quitarDiagnostico(d);
                    quitarPracticasPorDiagnostico(d);                    
                }                
            }
            estadosQueAdmitenCaras.some(function(est) {

            });            
        }
        return true;
    });
}

// busca en el array de diagnosticos por codigo
// siempre relacionado a la consulta actual
// devuelve false o el elemento
function buscarDiagnostico(codigo)
{
    var diagEncontrado = diagnosticos.find(function(el) {
        if (parseInt(el.pieza) === piezaSeleccionada && parseInt(el.codigo) == codigo) {
            // si idConsulta es 0 es una consulta nueva, entonces en este array el.id_consulta tiene que ser undefined
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta == el.id_consulta) {
                return true;
            }
        }
        return false;
    });

    return typeof(diagEncontrado) == "undefined" ? false : diagEncontrado;
}

// recibe como parametro un objeto diagnostico
// quita del array y tambien quita el form creado en la pestaña de practicas
function quitarDiagnostico(diagnostico)
{
    diagnosticos = diagnosticos.filter(function(el) {
        if (parseInt(el.pieza) === piezaSeleccionada && el.codigo === diagnostico.codigo) {
            // si se da alguna de las siguientes condiciones, puede eliminar
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta == el.id_consulta) {
                return false;
            }
        }
        return true;
    });

    $("#card_practica_" + diagnostico.codigo).remove();
}

// busca en el array de practicas por codigo
// siempre relacionado a la consulta actual
// devuelve false o el elemento
function buscarPractica(codigo, d)
{
    var practicaEncontrada = practicas.find(function(el) {
        // la practica para la pieza, con cierto codigo para cierto diagnostico
        if (parseInt(el.pieza) === piezaSeleccionada && parseInt(el.codigo) == codigo && parseInt(el.diagnostico) == d) {
            // si idConsulta es 0 es una consulta nueva, entonces en este array el.id_consulta tiene que ser undefined
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta == el.id_consulta) {
                return true;
            }
        }
        return false;
    });

    return typeof(practicaEncontrada) == "undefined" ? false : practicaEncontrada;
}

// recibe como parametro un objeto practica
function quitarPractica(practica)
{
    practicas = practicas.filter(function(el) {
        if (parseInt(el.pieza) === piezaSeleccionada && el.codigo === practica.codigo && parseInt(el.diagnostico) == practica.diagnostico) {
            // si se da alguna de las siguientes condiciones, puede eliminar
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta == el.id_consulta) {
                return false;
            }
        }
        return true;
    });
}

function quitarPracticasPorDiagnostico(diagnostico)
{
    practicas = practicas.filter(function(el) {
        if (parseInt(el.pieza) === piezaSeleccionada && parseInt(el.diagnostico) == diagnostico.codigo) {
            // si se da alguna de las siguientes condiciones, puede eliminar
            if (idConsulta === 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return false;
            }

            if (idConsulta == el.id_consulta) {
                return false;
            }
        }
        return true;
    });
}

// devuelve un array de diagnosticos pertenecientes a la consulta actual
// siempre tambien, para la pieza actual
function buscarDiagnosticos()
{
    return diagnosticos.filter(function(el) {
        // quito la seleccionada controlando que se trate de una nueva con typeof(el.id_consulta) === "undefined"
        if (parseInt(el.pieza) === piezaSeleccionada) {
            if (idConsulta === 0 && typeof(el.id_consulta) === "undefined") {
                return true;
            }

            if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
                return true;
            }

            if (idConsulta == el.id_consulta) {
                return true;
            }
        }
        return false;
    });
}

function agregarPractica(codigoDiagnostico)
{
    if (piezaSeleccionada === null) return;

    if (accionSeleccionada === null) return;
    
    if (tieneContrapartidaDefinitiva(piezaSeleccionada)) {
        return;
    }

    let practicaSeleccionada = practicasSeleccionadas[codigoDiagnostico];

    let d = buscarDiagnostico(codigoDiagnostico);

    const found = buscarPractica(practicaSeleccionada.codigo, codigoDiagnostico);
    let btnAgregarPractica = $(".btn-agregar-practica[data-diagnostico=" + codigoDiagnostico + "]");
    // la practica es nueva
    if (found === false) {
        practicas.push({
            'pieza' : piezaSeleccionada,
            'caras' : d.caras,
            'codigo' : practicaSeleccionada.codigo,
            'term': practicaSeleccionada.term,
            'diagnostico': codigoDiagnostico,
            'tipo': (practicaSeleccionada.caras.length > 0) ? 'CARAS' : 'PIEZA',
            'tiempo': 'FUTURA'
        });

        btnAgregarPractica.attr('disabled', true);
        btnAgregarPractica.html('Agregar práctica');        
        btnAgregarPractica.removeClass('btn-light');
        btnAgregarPractica.addClass('btn-success');

    } else {
        // la practica ya estaba agregada, tal vez es una actualizacion de las caras
        // si no es actualizacion de caras, no hacemos nada
        /*if (practicaSeleccionada.soloPieza === false) {

            practicas = practicas.map(obj => {
                if (parseInt(obj.pieza) === piezaSeleccionada && obj.codigo === practicaSeleccionada.codigo) {
                    obj.caras = (practicaSeleccionada.caras.length > 0) ? practicaSeleccionada.caras.join('-') : '';
                }

                return obj;
            });
        }*/

        btnAgregarPractica.attr('disabled', true);
        btnAgregarPractica.html('Ya agregada');            
        btnAgregarPractica.removeClass('btn-success');        
        btnAgregarPractica.addClass('btn-light');        
    }

}

function pintarEstado()
{
    const estado = estadosAgregados.findLast(el => parseInt(el.pieza) === piezaSeleccionada);

    $('.checkbox-estado').prop('checked', false);

    if (typeof estado != "undefined") {

        let pideSeleccionDeCaras = estadosQueAdmitenCaras.some((el) => el == codigoEstadoSeleccionado);
        if (pideSeleccionDeCaras) {
            for (let index = 0; index < estado.caras.length; index++) {
                $(".pieza_completa_estados [data-parte="+estado.caras[index].toLowerCase()+"]").attr("fill", fillRojo);
            }
        } else {
            tipoEstadoSeleccionado = (typeof codigoEstado["estadosDiagnostico"][estado.codigo] == "undefined") ? "estadosEstados" : "estadosDiagnostico";
            agregarSimbolo(".pieza_completa_estados", 'simboloAgregado', codigoEstado[tipoEstadoSeleccionado][estado.codigo].path);
        }
        
        $("#btn-agregar-estado").attr('disabled', true);
        $("#btn-agregar-estado").html('Establecer estado');        
        $("#btn-agregar-estado").removeClass('btn-danger');
        $("#btn-agregar-estado").removeClass('btn-light');
        $("#btn-agregar-estado").addClass('btn-success');

        let rgb = estado.codigo == 'IE' ? fillAzul : fillRojo;
        $("#simboloAgregado").attr("fill", rgb);        
    }

    /*else {
        $('.checkbox-estado').prop('checked', false);
        $(".pieza_completa_estados > #simboloAgregado").remove();

        $("#btn-agregar-estado").attr('disabled', true);
        $("#btn-agregar-estado").html('Agregar estado');
        $("#btn-agregar-estado").removeClass('btn-warning');
        $("#btn-agregar-estado").removeClass('btn-light');
        $("#btn-agregar-estado").addClass('btn-success');             
    }*/
}

function mostrarBotonesCaras()
{
    let botones = '<div class="btn-group mt-1 checkboxradio" id="radios_botones_caras" role="group">';

    botones += '<input class="btn-check checkbox-boton-cara" data-parte="left" type="checkbox" name="flexRadioDefault" id="boton-cara-'+caras.arriba+'">';
    botones += '<label for="boton-cara-'+caras.arriba+'" class="btn btn-outline-warning text-center">' + caras.arriba + '</label>';
    botones += '<input class="btn-check checkbox-boton-cara" data-parte="right" type="checkbox" name="flexRadioDefault" id="boton-cara-'+caras.dere+'">';
    botones += '<label for="boton-cara-'+caras.dere+'" class="btn btn-outline-warning text-center">' + caras.dere + '</label>';
    botones += '<input class="btn-check checkbox-boton-cara" type="radio" name="flexRadioDefault" id="boton-cara-'+caras.abajo+'">';
    botones += '<label for="boton-cara-'+caras.abajo+'" class="btn btn-outline-warning text-center">' + caras.abajo + '</label>';
    botones += '<input class="btn-check checkbox-boton-cara" data-parte="external" type="checkbox" name="flexRadioDefault" id="boton-cara-'+caras.izq+'">';
    botones += '<label for="boton-cara-'+caras.izq+'" class="btn btn-outline-warning text-center">' + caras.izq + '</label>';
    botones += '<input class="btn-check checkbox-boton-cara" data-parte="internal" type="checkbox" name="flexRadioDefault" id="boton-cara-'+centro+'">';
    botones += '<label for="boton-cara-'+centro+'" class="btn btn-outline-warning text-center">' + centro + '</label>';

    botones += '</div>';

    $("#div_botones_caras").html(botones);
}

function agregarFormPracticas(diagnostico)
{
    if ($("#diagnosticos_practicas > .card").length == 0) {        
        $("#diagnosticos_practicas").html("");
    }

    practicasXDiagnostico = 
        `<div class="card mb-3" id="card_practica_` + diagnostico.codigo + `">
            <div class="card-header bg-soft-primary pt-2 pb-2 text-uppercase">` + diagnostico.term + `</div>
                <div class="card-body">                
                    ` +  sumarItemFormPractica(diagnostico.codigo)  + `            
                </div>
        </div>`;

    $("#diagnosticos_practicas").append(practicasXDiagnostico);

    // pintamos la cara de la pieza en el form de practicas

    if (!Array.isArray(diagnostico.caras)) {
        if (diagnostico.caras == "") {
            diagnostico.caras = [];
        } else {
            diagnostico.caras = diagnostico.caras.split("-");
        }
    }
    for (let index = 0; index < diagnostico.caras.length; index++) {
        $(".pieza_completa_practicas .pieza_parte[data-diagnostico='" + diagnostico.codigo + "'][data-parte="+diagnostico.caras[index].toLowerCase()+"]").attr("fill", fillGrey);                    
    }

    console.log(baseUrl);

    $('.select_practicas').each(function() {
        if(!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({
                dropdownParent: '#card_practica_' + diagnostico.codigo, 
                allowClear: true, 
                width: "100%",
                minimumInputLength: 4,
                placeholder: "Escriba la practica",  
                ajax: {
                    url: baseUrl + 'snowstorm/practicas-odontologia',
                    dataType: 'json',
                    delay: 500,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    "cache": true                    
                }                
            });
        }                
    });

    $(document).on('select2:open', () => {
        document.querySelector('.select2-search__field').focus();
    });

    const tooltipTriggerList = document.querySelectorAll(".pieza_parte");
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}

function sumarItemFormPractica(codigoDiagnostico)
{
    return `<div class="row">
        <div class="col-1 text-center">
            ` + crearPiezaPractica(codigoDiagnostico) + `
        </div>    
        <div class="col-6">
            <select class="form-control select_practicas" data-diagnostico="` + codigoDiagnostico + `" name="select_pieza_completa" aria-hidden="true">
            </select>            
        </div>

        <div class="col-5 text-center">
            <button class="btn btn-success btn-agregar-practica" data-diagnostico="` + codigoDiagnostico + `" disabled>Agregar práctica</button>
        </div>
    </div>`;
}

// crea el html de la pieza
function crearPieza(claseSvg, codigoDiagnostico)
{
    let dataDiagnostico = typeof codigoDiagnostico == "undefined" ? "" : "data-diagnostico=" + codigoDiagnostico;
    
    return `<svg cursor="pointer" pointerEvents="all" class="` + claseSvg + `" width="85px" height="90px">
        <path class="pieza_parte" data-parte="` + caras.dere + `"
            data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="` + caras.dere.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 412.22px 301.78px;" transform="matrix(2.756088972092, 2.66152715683, -2.605391979218, 2.697963237762, -340.833059961603, -258.80341564815)" d="M 407 296.56 A 10.44 10.44 0 0 1 417.44 307 L 413.011 307 A 6.011 6.011 0 0 0 407 300.989 Z" bx:shape="pie 407 307 6.011 10.44 0 90 1@80c69b43"/>
        <path class="pieza_parte" data-parte="` + caras.abajo + `" 
            data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="` + caras.abajo.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 412.22px 312.22px;" transform="matrix(2.756091117859, 2.661525011063, -2.605392932892, 2.697964191437, -368.23305867873, -241.243399767955)" d="M 417.44 307 A 10.44 10.44 0 0 1 407 317.44 L 407 313.011 A 6.011 6.011 0 0 0 413.011 307 Z" bx:shape="pie 407 307 6.011 10.44 90 180 1@158268b1"/>
        <path class="pieza_parte" data-parte="` + caras.izq + `" 
            data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="` + caras.izq.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 401.78px 312.22px;" transform="matrix(2.756089925766, 2.66152715683, -2.605391025543, 2.697963237762, -386.792990155397, -269.243448226996)" d="M 407 317.44 A 10.44 10.44 0 0 1 396.56 307 L 400.989 307 A 6.011 6.011 0 0 0 407 313.011 Z" bx:shape="pie 407 307 6.011 10.44 180 270 1@22bfc712"/>
        <path class="pieza_parte" data-parte="` + caras.arriba + `" 
            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="` + caras.arriba.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 401.78px 301.78px;" transform="matrix(2.756088972092, 2.661527156829, -2.605389118195, 2.697962284089, -359.393064639937, -287.003383541312)" d="M 396.56 307 A 10.44 10.44 0 0 1 407 296.56 L 407 300.989 A 6.011 6.011 0 0 0 400.989 307 Z" bx:shape="pie 407 307 6.011 10.44 270 360 1@19797b45"/>
        <ellipse class="pieza_parte" data-parte="` + centro + `" 
            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="` + centro.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" style="stroke-width: 2px; transform-origin: 20px 23px;" cx="20" cy="23" rx="15.5" ry="15.5" transform="matrix(0.71933889389, 0.694659113884, -0.694659113884, 0.71933889389, 22.917160704609, 19.316783857956)"/>
    </svg>`;
}

// crea el svg de la pieza para practicas
function crearPiezaPractica(codigoDiagnostico)
{
    let dataDiagnostico = typeof codigoDiagnostico == "undefined" ? "" : "data-diagnostico=" + codigoDiagnostico;

    return `<svg cursor="pointer" pointerEvents="all" class="pieza_completa_practicas" width="45px" height="38px">
        <path class="pieza_parte" data-parte="` + caras.dere + `"
            data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="` + caras.dere.toUpperCase() + `"            
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 412.22px 301.78px;" transform="matrix(1.102435946465, 1.0646109581, -1.042155981064, 1.079185962677, -379.505396645908, -282.373403807815)" d="M 407 296.56 A 10.44 10.44 0 0 1 417.44 307 L 413.011 307 A 6.011 6.011 0 0 0 407 300.989 Z" bx:shape="pie 407 307 6.011 10.44 0 90 1@80c69b43"/>                        
        <path class="pieza_parte" data-parte="` + caras.abajo + `" 
            data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="` + caras.abajo.toUpperCase() + `"            
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 412.22px 312.22px;" transform="matrix(1.102437019348, 1.064610004425, -1.042158007622, 1.079187035561, -390.104356551564, -281.834052788433)" d="M 417.44 307 A 10.44 10.44 0 0 1 407 317.44 L 407 313.011 A 6.011 6.011 0 0 0 413.011 307 Z" bx:shape="pie 407 307 6.011 10.44 90 180 1@158268b1"/>                        
        <path class="pieza_parte" data-parte="` + caras.izq + `" 
            data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="` + caras.izq.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 401.78px 312.22px;" transform="matrix(1.102435946464, 1.064610958099, -1.044648051262, 1.081766009331, -390.920336503824, -293.104543806116)" d="M 407 317.44 A 10.44 10.44 0 0 1 396.56 307 L 400.989 307 A 6.011 6.011 0 0 0 407 313.011 Z" bx:shape="pie 407 307 6.011 10.44 180 270 1@22bfc712"/>
        <path class="pieza_parte" data-parte="` + caras.arriba + `" 
            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="` + caras.arriba.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px" style="transform-origin: 404.536px 299.061px;" transform="matrix(1.10243499279, 1.064612030983, -1.04215502739, 1.079185009003, -377.269272124898, -290.826734756223)" d="M 396.56 307 A 10.44 10.44 0 0 1 407 296.56 L 407 300.989 A 6.011 6.011 0 0 0 400.989 307 Z" aria-describedby="tooltip988149" bx:shape="pie 407 307 6.011 10.44 270 360 1@19797b45"/>            
        <ellipse class="pieza_parte" data-parte="` + centro + `" 
            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="` + centro.toUpperCase() + `"
            ` + dataDiagnostico + `
            stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" style="stroke-width: 2px; transform-origin: 20px 23px;" cx="20" cy="23" rx="5.5" ry="5.5" transform="matrix(0.71933889389, 0.694659113884, -0.694659113884, 0.71933889389, 1.763851507178, -3.580375563229)"/>            
    </svg>`;
}

function actualizarHistorialPracticas()
{
    let practicasAgregadas = practicas.filter(el => parseInt(el.pieza) === piezaSeleccionada);

    let table = "<table class='table'><thead><tr><th>Fecha</th><th>Aplica a</th><th>Practica</th><th>Diagnostico</th><th></th></tr></thead><tbody>"

    let practicaTr = "";
    const btnEliminar = `<button type="button" class="btn btn-sm btn-outline-link rounded-pill float-xl-end btn-quitar-practica ms-1" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar Esta practica">
        Quitar
        </button>`;

    // el boton que pasa la practica de planificada a realizada
    const btnPasarARealizada = `<button type="button" 
                        class="btn btn-sm btn-outline-danger rounded-pill float-xl-end btn-toggle-tiempo" data-tiempo="PRESENTE" 
                        data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Establecer esta practica como realizada">                        
                        Establecer como realizada        
                    </button>`;

    const btnPasarAPlanificada = `<button type="button" 
                        class="btn btn-sm btn-outline-info rounded-pill float-xl-end btn-toggle-tiempo"  data-tiempo="FUTURA" 
                        data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Establecer esta practica como planificada">                        
                        Establecer como planificada
                    </button>`;

    for (let index = practicasAgregadas.length - 1; index >= 0; index--) {

        // para las consultas viejas, en donde no existia el diagnostico controlamos que no sea undefined mas adelante
        let diagnosticoDePractica = diagnosticos.find(item => item.codigo === practicasAgregadas[index].diagnostico);

        let bCambiarEstado = '';
        if (typeof(practicasAgregadas[index].id_consulta) === "undefined" || idRrHh == parseInt(practicasAgregadas[index].id_rr_hh)) {
            bCambiarEstado = (practicasAgregadas[index].tiempo === 'FUTURA' ? btnPasarARealizada : btnPasarAPlanificada);
        } else {
            // permitimos cambiar el estado
            if (idRrHh !== parseInt(practicasAgregadas[index].id_rr_hh) && practicasAgregadas[index].tiempo === 'FUTURA') {
                bCambiarEstado = (practicasAgregadas[index].tiempo === 'FUTURA' ? btnPasarARealizada : btnPasarAPlanificada);
            }
        }

        bEliminar = '';
        if (typeof(practicasAgregadas[index].id_consulta) === "undefined" || idConsulta === parseInt(practicasAgregadas[index].id_consulta)) {
            bEliminar = btnEliminar;
        }

        if (!Array.isArray(practicasAgregadas[index].caras)) {
            if (practicasAgregadas[index].caras == "") {
                practicasAgregadas[index].caras = [];
            } else {
                practicasAgregadas[index].caras = practicasAgregadas[index].caras.split("-");
            }
        }
        let aplicaA = practicasAgregadas[index].tipo == 'PIEZA' ? "Pieza completa" : "Caras: " + practicasAgregadas[index].caras;
        practicaTr = practicaTr + 
                    "<tr><td>Hoy</td><td>" + aplicaA + "</td><td>" +
                    practicasAgregadas[index].codigo + "<br>" + 
                    (practicasAgregadas[index].tiempo === 'FUTURA' ? "<span class='badge text-bg-info'>PLANIFICADA</span>" : "<span class='badge text-bg-danger'>REALIZADA</span>") + 
                    "</td><td class='text-wrap'>" +  (typeof(diagnosticoDePractica) == "undefined" ? "-" : diagnosticoDePractica.term) + "</td>"+
                    "<td id='" +practicasAgregadas[index].codigo+"' data-diagnostico='" +practicasAgregadas[index].diagnostico+"'>" + bEliminar + " " + bCambiarEstado + "</td></tr>";            
    }

    table = table + ((practicaTr === "") ? '<td colspan="4">Sin historial</td>' : practicaTr) + "</tbody></table>";        
    
    $("#historial-practicas").html(table);

    const tooltipTriggerList = document.querySelectorAll("#historial-practicas .btn");
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}

function actualizarHistorialDiagnosticos()
{
    let diagnosticosAgregados = diagnosticos.filter(item => parseInt(item.pieza) === piezaSeleccionada);

    let table = "<table class='table'><thead><tr><th>Fecha</th><th>Aplica a</th><th>Diagnóstico</th><th></th></tr></thead><tbody>"

    let diagnosticoTr = "";
    let btnEliminar = `<button type="button" class="btn btn-sm btn-outline-link rounded-pill float-xl-end btn-quitar-diagnostico ms-1" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar Esta practica">
        Quitar
        </button>`;

    for (let index = diagnosticosAgregados.length - 1; index >= 0; index--) {
        if (typeof(diagnosticosAgregados[index].id_consulta) !== "undefined" && idConsulta !== parseInt(diagnosticosAgregados[index].id_consulta)) {
            btnEliminar = '';
        }

        if (!Array.isArray(diagnosticosAgregados[index].caras)) {
            if (diagnosticosAgregados[index].caras == "") {
                diagnosticosAgregados[index].caras = [];
            } else {
                diagnosticosAgregados[index].caras = diagnosticosAgregados[index].caras.split("-");
            }
        }        
        let aplicaA = diagnosticosAgregados[index].tipo == 'PIEZA' ? "Pieza completa" : "Caras: " + diagnosticosAgregados[index].caras;
        diagnosticoTr = diagnosticoTr + 
                    "<tr><td>Hoy</td><td>" + aplicaA + "</td><td>" + diagnosticosAgregados[index].term + "</td>" + 
                    "<td id='" +diagnosticosAgregados[index].codigo+"'>"+btnEliminar+"</td></tr>";
    }

    table = table + ((diagnosticoTr === "") ? '<td colspan="4">Sin historial</td>' : diagnosticoTr) + "</tbody></table>";        
    
    $("#historial-diagnosticos").html(table);
}

function limpiarDatos()
{
    carasSeleccionadas = [];

    /*for (let index = 0; index < estadosQueAdmitenCaras.length; index++) {
        carasEstadosSeleccionadas[estadosQueAdmitenCaras[index]] = [];        
    }*/
    carasEstadosSeleccionadas = [];

    $("#ayuda_estados").hide();
    $("#ayuda_diagnosticos").hide();

    // limpio y actualizo diagnosticos
    actualizarHistorialDiagnosticos();

    // limpio y actualizo practicas
    actualizarHistorialPracticas();
    
    //$("#select_practicas").val(null).trigger('change');;
    
    $("#diagnosticos_practicas").html("<div class='text-center'>Las practicas requieren un diagnóstico</div>");
    /*$(".btn-agregar-practica").attr('disabled', true);
    $(".btn-agregar-practica").html('Agregar práctica');
    $(".btn-agregar-practica").removeClass('btn-warning');
    $(".btn-agregar-practica").removeClass('btn-light');
    $(".btn-agregar-practica").addClass('btn-success');*/

    $("#btn-agregar-diagnostico").attr('disabled', true);
    $("#btn-agregar-diagnostico").html('Agregar diagnóstico');
    $("#btn-agregar-diagnostico").removeClass('btn-warning');
    $("#btn-agregar-diagnostico").removeClass('btn-light');
    $("#btn-agregar-diagnostico").addClass('btn-success');

    $("#pieza_completa_practicas path").attr("fill", "rgb(255, 255, 255)");
    $("#pieza_completa_practicas ellipse").attr("fill", "rgb(255, 255, 255)");
}

/*
    Controla si la pieza (en caso de ser temporal) tiene una contrapartida en su pieza definitiva
 */
function tieneContrapartidaDefinitiva(piezaSeleccionada)
{
    // si no es undefined, la pieza es temporal. contraPartidas es pieza_temporal=>pieza_definitiva
    if (typeof contraPartidas[piezaSeleccionada] !== 'undefined') {
        piezaDefinitiva = contraPartidas[piezaSeleccionada];
        const foundPractica = practicas.some(el => parseInt(el.pieza) === piezaDefinitiva);
        const foundEstado = estadosAgregados.some(el => parseInt(el.pieza) === piezaDefinitiva);
        // existen practicas para la pieza definitiva, no permitimos ahora practicas para la temporal
        if (foundPractica || foundEstado) {
            return true;
        } else {
            return false;
        }
    }
}

/**
 * Guardamos en el hidden a donde van las practicas y estados
 */
function guardarDatos()
{
    $("#estados_a_guardar").val(JSON.stringify(estadosAgregados));    

    $("#diagnosticos_a_guardar").val(JSON.stringify(diagnosticos));

    $("#practicas_a_guardar").val(JSON.stringify(practicas));
}