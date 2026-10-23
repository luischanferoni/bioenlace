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
}
