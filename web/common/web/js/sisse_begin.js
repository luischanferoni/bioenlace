/**
 * Funciones cargadas previo a la carga del dom
 */

window.addEventListener('load', function() {
  const buttons = document.querySelectorAll("button[type='submit']");
  for (let i = 0; i < buttons.length; i++) {
    buttons[i].disabled = false;
    buttons[i].addEventListener("click", ()=>{ 
      buttons[i].classList.add('disabled');
    });
  }
});

function alertaFlotante(contenido, tipo) {
  let icon = '<i class="bi bi-check"></i> ';
  switch (tipo) {
    case "success":
      icon = '<i class="bi bi-check"></i> ';
      break;

    case "danger":
      icon = '<i class="bi bi-exclamation"></i> ';
      break;

    case "warning":
      icon = '<i class="bi bi-arrow-right-circle-fill"></i> ';
      break;
  }

  $(".modal-header").append(
    '<div class="alert alert-' +
      tipo +
      ' alert-dismissible fade show sisse-floating-alert position-fixed" role="alert">' +
      icon +
      contenido +
      "</div>"
  );
  window.setTimeout(function () {
    $(".sisse-floating-alert").alert("close");
  }, 6000);
}

function sweetAlertConfirm(title, text) {
  return Swal.fire({
    title: title,
    text: text,
    icon: "warning",
    showCancelButton: true,
    backdrop: `rgba(60,60,60,0.8)`,
    confirmButtonText: "Confirmar",
    confirmButtonColor: "#2185f4",
    cancelButtonText: "Cancelar",
  });
}
