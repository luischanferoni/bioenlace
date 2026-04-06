function getEventos(dia) {
  $("#eventos_maniana").html(
    '<div class="iq-loader-box"><div class="iq-loader-8"></div></div>'
  );
  $("#eventos_tarde").html(
    '<div class="iq-loader-box"><div class="iq-loader-8"></div></div>'
  );

  $.get(
    turnos_url_eventos,
    {
      dia: dia,
      id_servicio: turnos_id_servicio,
      id_rrhh_servicio_asignado: turnos_id_rrhh_sa,
    },
    function (data) {
      $("#eventos_maniana").html(data.turnos.maniana);
      $("#eventos_tarde").html(data.turnos.tarde);
      $("#todosTomados").val(data.turnos.todosTomados ? "1" : "");

      if (
        data.turnos.mensajeFeriado != "" &&
        data.turnos.mensajeFeriado != undefined
      ) {
        $("#mensaje_feriado").html(data.turnos.mensajeFeriado);
        //aqui tendria que cambiar la card de color para hacer notar que es un feriado
      } else {
        $("#mensaje_feriado").html("");
      }

      const tooltipTriggerList = document.querySelectorAll(
        '[data-bs-toggle="tooltip"]'
      );
      const tooltipList = [...tooltipTriggerList].map(
        (tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl)
      );
    }
  );
}

function bioHeaders() {
  if (typeof window.getBioenlaceApiClientHeaders === "function") {
    return window.getBioenlaceApiClientHeaders();
  }
  return {};
}

$(document).ready(function () {

  var hoy = new Date().toISOString().slice(0, 10);
  var slides = $(".weekday-slider").children();
  var startIndex = 0;
 
  slides.each(function(index, slide){

    var href = $(slide).find('a').attr('href');

    if(href == hoy){
        startIndex = index;
        return false;
    }

  });

  startIndex = startIndex - 30;
 
  var slider = tns({
    container: ".weekday-slider",
    controlsContainer: "#controles-personalizados",
    mouseDrag: true,
    autoplay: false,
    loop: false,
    slideBy: 7,
    swipeAngle: false,
    nav: false,
    speed: 400,
    startIndex: startIndex,
    responsive: {
      768: {
        items: 3,
      },
      1024: {
        items: 7,
      },
    },
  });

  // User clicks date on calendar:
  $(document).on("click", ".mostrar-turnos", function (e) {
    e.preventDefault();

    $(".card").removeClass("border border-primary");
    $(this).closest(".card").addClass("border border-primary");

    var dia = $(this).attr("href");
    getEventos(dia);

    $("#fecha_input").val(dia);
    $("#hora_input").val("");
  });

  //
  // Users click on turn slot in calendar
  //
  $(document).on("click", ".hora", function (e) {
    e.preventDefault();

    // reseteo de valores
    $("#id_turnos").val("");
    $("#motivo_cancelacion_div").hide();
    $("#msg_turno_atendido").hide();
    $("#btn_turno_create").show();
    $("#btn_turno_cancel").hide();
    $("#btn_turno_sobreturno").hide();

    // cambiamos el estilo de la hora seleccionada
    $(".hora").removeClass("bg-primary text-white");
    $(this).addClass("bg-primary text-white");

    // cambiamos el texto que va en el footer del modal
    if ($(this).closest("#eventos_maniana").length > 0) {
      $("#turno_a_guardar_hora").html("a las " + $(this).html() + " AM");
    } else {
      $("#turno_a_guardar_hora").html("a las " + $(this).html());
    }

    // ponemos el valor de la hora en un hidden
    $("#hora_input").val($(this).html());
    // deshabilitamos el boton de crear turno
    $("#btn_turno_create").prop("disabled", false);
    if ($("#todosTomados").val() === "1") {
      $("#btn_turno_sobreturno").show();
    }
    if ($(this).attr("id")) {
      if ($(this).attr("id-persona") == '".$persona->id_persona."') {
        $("#sobreturno_div").show();
      }

      if ($(this).attr("estado-turno") == "PENDIENTE") {
        // es para cancelar un turno
        $("#motivo_cancelacion_div").show();
        $("#btn_turno_cancel").show();
      } else {
        $("#msg_turno_atendido").show();
      }
      $("#id_turnos").val($(this).attr("id"));
      $("#btn_turno_create").hide();
    }
  });

  //
  // User clicks on cancel turno button.
  $(document).on("click", "#btn_turno_cancel", function (e) {
    e.preventDefault();

    let id_turno = $("#id_turnos").val();
    let motivo_cancelacion = $("#motivo_cancelacion").val();

    if (id_turno !== "") {
      if (motivo_cancelacion == "") {
        //$('#btn_turno_create').prop('disabled', false);
        return;
      }
      //$('#btn_turno_create').html('Cancelando turno..');

      $.ajax({
        url: turnos_url_cancelar_operativo_base + "/" + id_turno + "/cancelar-operativo",
        type: "POST",
        headers: bioHeaders(),
        data: {
          estado_motivo: motivo_cancelacion,
          canal: "web",
        },
      })
        .done(function (data) {
          if (data.success == true) {
            alertaFlotante("Listo", "success");
            $("#modal-general").modal("hide");
          } else {
            alertaFlotante(data.message, "danger");
          }
        })
        .fail(function () {
          alertaFlotante("Error inesperado", "danger");
          $("#modal-general").modal("hide");
        });
    }
  });

  $(document).on("click", "#btn_turno_sobreturno", function (e) {
    e.preventDefault();
    $("#btn_turno_sobreturno").prop("disabled", true);
    $.ajax({
      url: turnos_url_crear_sobreturno,
      type: "POST",
      headers: bioHeaders(),
      data: {
        id_persona: turnos_id_persona,
        fecha: $("#fecha_input").val(),
        hora: $("#hora_input").val(),
        id_rrhh_servicio_asignado: turnos_id_rrhh_sa,
        id_servicio_asignado: turnos_id_servicio,
        id_efector: turnos_id_efector,
      },
    })
      .done(function (data) {
        if (data.success == true) {
          alertaFlotante("Sobreturno registrado", "success");
          $("#modal-general").modal("hide");
        } else {
          alertaFlotante(data.message || "Error", "danger");
        }
        $("#btn_turno_sobreturno").prop("disabled", false);
      })
      .fail(function () {
        alertaFlotante("Error inesperado", "danger");
        $("#btn_turno_sobreturno").prop("disabled", false);
      });
  });

  //
  // User clicks on crear turno button.
  $(document).on("click", "#btn_turno_create", function (e) {
    e.preventDefault();

    $("#btn_turno_create").prop("disabled", true);
    // $('#btn_turno_create').html('Creando turno..');
    $.ajax({
      url: turnos_url_create,
      type: "POST",
      headers: bioHeaders(),
      data: {
        id_persona: turnos_id_persona,
        fecha: $("#fecha_input").val(),
        hora: $("#hora_input").val(),
        id_rrhh_servicio_asignado: turnos_id_rrhh_sa,
        id_servicio_asignado: turnos_id_servicio,
        id_efector: turnos_id_efector,
      },
    })
      .done(function (data) {
        if (data.success == true || data.id) {
          alertaFlotante("Listo", "success");
          $("#modal-general").modal("hide");
        } else {
          alertaFlotante(data.message || "Error", "danger");
          $("#btn_turno_create").html("Crear turno");
          $("#btn_turno_create").prop("disabled", false);
        }
      })
      .fail(function () {
        alertaFlotante("Error inesperado", "danger");
        $("#modal-general").modal("hide");
        $("#btn_turno_create").prop("disabled", false);
      });
  });

  // Setup Turnos dialog when is shown up.
  const turno_modal = document.getElementById("modal-general");
  if (turno_modal) {
    turno_modal.addEventListener("show.bs.modal", (event) => {
      // Button that triggered the modal
      const button = event.relatedTarget;
      // Extract info from data-bs-* attributes
      const recipient = button.getAttribute("data-title");

      turnos_id_rrhh_sa = button.getAttribute("data-sisse-id_rrhh_sa") ?? 0;
      turnos_id_servicio = button.getAttribute("data-sisse-id_servicio");

      // Update the modal's content.
      const modalTitle = turno_modal.querySelector(".modal-title");
      modalTitle.textContent = `${recipient}`;

      // Load turnos
      getEventos(null);

      $(".card").removeClass("border border-primary");

      const diaHoy = new Date().toISOString().slice(0, 10);

      $("a[href=" + diaHoy + "]")
        .closest(".card")
        .addClass("border border-primary");

      $("#fecha_input").val(diaHoy);
      $("#hora_input").val("");

      $("#motivo_cancelacion").val("");

      $("#btn_turno_create").show();
      $("#btn_turno_create").prop("disabled", true);

      $("#btn_turno_cancel").hide();
      $("#motivo_cancelacion_div").hide();
      $("#btn_turno_sobreturno").hide(); // Este boton no se utiliza todavia?
    });
  }
});
