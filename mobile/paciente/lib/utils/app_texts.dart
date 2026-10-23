/// Textos de infraestructura de la app Paciente (avisos y etiquetas genéricas del cliente).
/// Los textos clínicos y de negocio vienen del backend.
class AppTexts {
  static const signupBiometricPending =
      'Registramos tu cuenta, pero falta activar la huella del dispositivo.';
  static const signupComplete = 'Completá tu registro';
  static const signupUploadPhotos = 'Subí las fotos requeridas para verificar tu identidad.';
  static const guardianshipPendingVerification =
      'Hay solicitudes de tutela en verificación. Un operador del centro debe confirmar el vínculo antes de que puedas operar por el menor.';
  static const noGuardianYet =
      'Nadie a cargo todavía. Las delegaciones activas y las solicitudes de tutela aparecerán acá.';
  static const minorRegistrationHint =
      'Si el documento no está en el sistema, usamos estos datos para dar de alta al menor.';
  static const provinceContextHint =
      'Esta provincia define qué servicios y respuestas verás en la app. '
      'No cambia tu domicilio registrado.';
  static const carePlanNoInstructions = 'No hay indicaciones cargadas en este plan.';
  static const carePlanChooseNeed = 'Elegí qué necesitás sobre este tratamiento.';
  static const noAlerts = 'No tienes alertas.';
  static const alertConfirmAttendance = 'Confirmar asistencia';
  static const alertAdvanceAppointment = 'Adelantar mi turno';
  static const sessionExpired = 'Tu sesión expiró. Ingresá de nuevo.';
  static const withdrawRequestConfirm =
      '¿Querés retirar esta solicitud? Solo podés hacerlo mientras el equipo aún no la atiende.';
  static const appointmentInResolution = 'En resolución';
  static const appointmentInResolutionHint =
      'Este turno está en resolución: no podés preparar la consulta hasta elegir un nuevo horario.';
  static const noPendingAppointments =
      'No tienes turnos pendientes. Puede pedir uno desde el asistente.';
  static const logout = 'Cerrar sesión';
  static const comingSoon = 'Próximamente';
  static const sectorUpdateFailed = 'No se pudo actualizar el sector';
  static const loginToSendComplaint = 'Iniciá sesión para enviar una queja';
  static const prescriptionsTitle = 'Recetas';
  static const labResultsTitle = 'Resultados de laboratorio';
  static const ordersTitle = 'Pedidos e indicaciones';
  static const audioSavedUploading = 'Audio guardado en el teléfono. Subiendo…';
  static const pendingMessageDeleted = 'Mensaje pendiente eliminado';
  static String screenNotImplemented(Object? id) => 'Pantalla no implementada: $id';
  static const biometricRequired = 'Biometría requerida';
  static const cameraFocusFirst = 'Primero valida la posición presionando el botón de enfoque';
  static const imageCaptureFailed = 'Error al capturar la imagen';
  static String appointmentMovedUp(String detail) => 'Turno adelantado.$detail';
  static String userLabel(String name) => 'Usuario: $name';
  static const loadEncounterReasons = 'Cargar motivos de la consulta';
  static String professionalLabel(String name) => 'Profesional: $name';
  static const noProvincesAvailable = 'No hay provincias disponibles.';
  static String unexpectedError(Object e) => 'Error inesperado: $e';
  static const withdrawRequestTitle = 'Retirar solicitud';
  static const withdrawRequestBody = '¿Querés retirar esta solicitud? Solo podés hacerlo mientras el equipo aún no la atiende.';
  static String protocolLabel(String protocol) => 'Protocolo: $protocol';
  static const messageEncounters = 'Consultas por mensaje';
  static const followUpControl = 'Control y seguimiento';
  AppTexts._();

  static const minorDocumentRequired = 'Ingresá el documento del menor.';
  static const minorDataRequired = 'Completá nombre, apellido y fecha de nacimiento del menor.';
  static const guardianshipPendingExists = 'Ya hay una solicitud de tutela en verificación para este documento.';
  static String guardianshipExists(String statusLabel) =>
      'Ya existe un vínculo de tutela ($statusLabel) para este documento.';
  static const requestSent = 'Solicitud enviada';
  static const requestInProgress = 'Ya hay una solicitud o vínculo en curso para este menor.';
  static const representativeDocumentRequired = 'Ingresá el documento del representante.';
  static const representativeDesignated = 'Representante designado';
  static const designateFailed = 'No se pudo designar';
  static const revokeFailed = 'No se pudo revocar';
  static const preferencesNotSaved = 'No se guardaron las preferencias';
  static String contextLabel(String label) => 'Contexto: $label';
  static const patientFallback = 'paciente';
  static const notifyRepresentativeActs = 'Avisame cuando un representante actúe';
  static const operate = 'Operar';
  static const inReview = 'En revisión';
  static String statusLine(String status) => 'Estado: $status';
  static const requestMinorGuardianship = 'Solicitar tutela de menor';
  static const designateRepresentative = 'Designar representante';
  static const father = 'Padre';
  static const mother = 'Madre';
  static const legalGuardian = 'Tutor legal';
  static const male = 'Masculino';
  static const female = 'Femenino';
  static const spouse = 'Cónyuge';
  static const child = 'Hijo/a';
  static const other = 'Otro';

  static const withdrawRequest = 'Retirar solicitud';
  static const no = 'No';
  static const withdraw = 'Retirar';
  static const treatmentRequests = 'Solicitudes del tratamiento';
  static const previousRequests = 'Solicitudes anteriores';
  static const indications = 'Indicaciones';
  static const followUp = 'Control y seguimiento';
  static const attendanceConfirmed = 'Asistencia confirmada';
  static const upcomingAppointments = 'Próximos turnos';
  static const yourConditions = 'Tus condiciones';
  static const yourTreatment = 'Tu tratamiento';
  static const chatNoShortcuts = 'No hay atajos disponibles.';
  static const settingsYourAccount = 'Tu cuenta';
  static const settingsHealthSector = 'Sector de salud';
  static const settingsContextProvince = 'Provincia de contexto';
  static const settingsVerifyingAddress =
      'Estamos verificando tu domicilio, te avisaremos cuando esté listo.';
  static const settingsSendComplaint = 'Enviar queja';
  static const settingsComplaintHint =
      'Problemas con la app, turnos o la atención recibida';
  static const chatMissingService =
      'Falta el servicio elegido para listar centros de salud. Volvé a elegir el servicio en el paso anterior o iniciá de nuevo el trámite de turno.';
  static const chatAdvanceFailed = 'No se pudo avanzar. Intentá de nuevo.';
  static const chatCompleteInfo = 'Completa la información';
  static const chatReasonsTitle = 'Motivos de la consulta';
}
