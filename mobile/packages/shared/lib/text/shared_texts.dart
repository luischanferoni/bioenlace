/// Textos de infraestructura del paquete shared (avisos y etiquetas genéricas del cliente).
class SharedTexts {
  SharedTexts._();

  static String unsupportedWidget(Object? id) => 'Widget no soportado: $id';
  static const noOptions = 'Sin opciones disponibles';
  static const selectOption = 'Seleccioná una opción';
  static const noResults = 'Sin resultados.';
  static String completeField(Object? label) => 'Complete: $label';
  static const retry = 'Reintentar';
  static const invalidUi = 'UI inválida';
  static String pdfDownloadFailed(String reason) => 'No se pudo descargar el PDF: $reason';
  static const invalidUiMissingDraftField = 'UI inválida: falta draft_field';
  static const confirm = 'Confirmar';
  static const cancel = 'Cancelar';
  static const save = 'Guardar';
  static String selected(Object? label) => 'Seleccionado: $label';
  static const noResultsMinimalSignup = 'Sin resultados. Completá el alta mínima si es un paciente nuevo.';

  static const firstDoseTime = 'Hora de la primera toma';
  static const dailyReminders = 'Recordatorios del día';
  static const reminders = 'Recordatorios';
  static const treatmentReminders = 'Recordatorios de este tratamiento';
  static String remindersCustom(String times) => 'Recordatorios: $times';
  static const chooseFirstDoseTime = 'Elegí la hora de la primera toma';
  static const chooseFirstDoseTimeButton = 'Elegir hora de la primera toma';

  static const privacyPolicyOpenFailed = 'No se pudo abrir la política de privacidad';
  static const institutionalSignupOpenFailed = 'No se pudo abrir el alta de consultorio';
  static const reviewAccessTitle = 'Acceso para revisión';
  static const hcSummaryInAssistantTitle = 'Resumen de historia en el asistente';
  static const yourAccount = 'Tu cuenta';
  static const treatmentRemindersTitle = 'Recordatorios de tratamiento';
  static String pdfReady(String name) => 'PDF listo: $name';
  static const calculatingAppointmentImpact = 'Calculando impacto en turnos…';
  static const searching = 'Buscando...';
  static const previous = 'Anterior';
  static String professionalLabel(String name) => 'Profesional: $name';
  static String enableBiometric(String type) => 'Activar $type';
  static const notNow = 'Ahora no';
  static const verificationCancelled = 'Verificación cancelada por el usuario';
  static String authenticationFailed(Object e) => 'Error en la autenticación: $e';}
