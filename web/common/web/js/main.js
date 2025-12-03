$(function () {

  // on click de cualquier link con la class linkaModalGeneral abre un modal
  $(".linkaModalGeneral").click(function (event) {
    event.preventDefault();
    $("#modal-general").find(".modal-title").text($(this).attr("data-title"));

    var modal_content = $("#modal-general")
      .modal("show")
      .find(".modal-body");
    // limpio el body, el load demora
    modal_content.html('<div id="spinner" class="body-spin" ><div class="spin"></div></div>');
    modal_content.load($(this).attr("href"));
  });

    $(function() {
        $('#modal-general').on('submit', 'form', function(e) {
            e.preventDefault();

            var form = $(this);
            var fd = new FormData(this);
             
            $('#modal-general .modal-body').html('<div class=\"iq-loader-box\"><div class=\"iq-loader-8\"></div></div>');

            $.ajax({
                url: form.attr('action'),
                type: form.attr('method'),
                processData: false,
                contentType: false,
                data: fd,
                success: function (data) {
                    if(typeof(data.success) == 'undefined') {
                        $('#modal-general .modal-body').html(data);
                        return;
                    }
                    
                    alertaFlotante(data.msg, data.success ? 'success' : 'danger');

                    if(data.success) {                        
                        $('#modal-general').modal('hide');
                        return;
                    }
                },
                error: function () {
                    $('#modal-general .modal-body').append('<div class=\"alert alert-success\" role=\"alert\">'
                        +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>');
                    window.setTimeout(function() { $('.alert').alert('close'); }, 6000);
                }
            });
            
        });
    });

  $("#modalButtonAntec,.modal-button-antec").click(function () {
    $("#modalAntec")
      .modal("show")
      .find("#modalContentAntec")
      .load($(this).attr("value"));
  });

  //PUCO
  $("#modalButtonPuco").click(function () {
    $("#modalPuco")
      .modal("show")
      .find("#modalContentPuco")
      .load($(this).attr("value"));
    document.getElementById("modalHeader").innerHTML =
      "<h4>" + $(this).attr("title") + "</h4>";
  });

  $(".custom_buttonPuco").click(function () {
    $("#modalPuco")
      .modal("show")
      .find("#modalContentPuco")
      .load($(this).attr("value"));
    //dynamiclly set the header for the modal
    document.getElementById("modalHeader").innerHTML =
      "<h4>" + $(this).attr("title") + "</h4>";
  });

  $(".alerta-cambio-efector").click(function (event) {
    event.preventDefault();
    Swal.fire({
      title: "¿Esta seguro de cambiar de efector?",
      text: "",
      icon: "warning",
      showCancelButton: true,
      backdrop: `rgba(60,60,60,0.8)`,
      confirmButtonText: "Confirmar",
      confirmButtonColor: "#2185f4",
    }).then((result) => {
      if (result.isConfirmed) {
        $(window).attr('location',document.querySelector(".alerta-cambio-efector").getAttribute("href"))
      }
    });
  });

  $('.ajax-delete').on('click', function(e) {
      e.preventDefault();
      let url = $(this).attr('href');
      $.ajax({
          url: url,
          type: 'POST',
          success: function (data) {
              if(data.error == false) {
                  location.reload();
              } else {
                let messages = [];
                for (const [key, value] of Object.entries(data.message)) {
                  messages.push(key + ': ' + value);
                }
                alertaFlotante(messages.join('<br>') , 'danger');                       
              }
          },
          error: function (data) {
            alertaFlotante('Error inesperado', 'danger');
          }
      });
  });

	/**
	 * ajax-sweet-pjax usa un sweet alert, pasandole como parametro
	 * la url data-url y el div (container) a recargar data-pjax-div
	 * si data-pjax-div no existe hace un reload de toda la pagina
	 */
	$(document).on('click', '.ajax-sweet-pjax', function(e) {
		e.preventDefault();
		
		sweetAlertConfirm($(this).attr('data-sweet_title'))
			.then((result) => {
				if (result.isConfirmed) {
					let url = yii.getBaseCurrentUrl() + $(this).data('url');
					let container = $(this).data('container');
					$.ajax({
						url: url,
						type: 'POST',                            
						success: function (data) {
								if(typeof(data.error) == 'undefined') {
									alertaFlotante('Resultado desconocido', 'danger');
									return;
								}
								if(data.error == true) {
									alertaFlotante(data.msg, 'danger');
								} else {
									alertaFlotante(data.msg, 'success');
									if (typeof container == 'undefined') {
										location.reload();
									} else {
										$.pjax.reload({container: container});
									}
								}
						},
						error: function () {
								alertaFlotante('Ocurrió un error', 'danger');
						}
					});
				}
			});
	});

  flatpickr("#cal-lista-espera", {
    dateFormat: "Y-m-d",
    onChange: function(selectedDates, dateStr, instance) {      
      
      rrhh = $('#cal-lista-espera').data('rrhh')
      urlRrhh = ""
      if(rrhh){
        urlRrhh = "&rrhh="+rrhh
      }
      $(window).attr('location', baseUrl + "turnos/espera?fecha=" + dateStr + urlRrhh)
    }
});

});
