/// Textos de infraestructura de Personal de Salud (avisos de cliente y botones genéricos).
/// Los textos clínicos y de negocio llegan del backend, no se agregan acá.
class AppTexts {
  AppTexts._();

  static const cancel = 'Cancelar';

  static const timelineLoadedData = 'Datos cargados';
  static const noData = 'Sin datos';
  static const timelineWeight = 'Peso';
  static const timelineHeight = 'Altura';
  static const timelineBmi = 'IMC';
  static const timelineBloodPressure = 'Tensión arterial';
  static const timelineCurrentCondition = 'Condición actual';
  static const timelineIntakeTitle = 'Preguntas previas al chat';
  static const timelineGuidance = 'Orientación';
  static const timelinePatientAnswers = 'Respuestas del paciente';
  static const timelineEpisodeRecord = 'Registro del episodio';
  static const timelineActionNoScreen = 'La acción no tiene una pantalla compatible.';
  static const timelineReasonsTitle = 'Motivos de esta consulta';
  static const timelineSummary = 'Resumen';
  static const timelineDiagnoses = 'Diagnósticos a considerar';
  static const timelinePractices = 'Prácticas / estudios';
  static const timelinePreconsult = 'Asistencia pre-consulta (cohorte)';
  static const dictationDisabled = 'Dictado por voz deshabilitado.';
  static const micDisabledTitle = 'Micrófono no habilitado';
  static const close = 'Cerrar';
  static const captureDeleteFailed = 'Error al eliminar del servidor';
  static const captureDeleted = 'Eliminada';
  static const captureMissingParent = 'Falta contexto de atención (parent).';
  static const captureNoText = 'No hay texto para analizar.';
  static const captureAnalyzeFirst = 'Analizá antes de guardar.';
  static const understood = 'Entendido';
  static const captureAnalyzing = 'Analizando…';
  static const captureRegisteredText = 'Texto registrado';
  static const captureProcessedText = 'Texto procesado';
  static const captureProcessingResult = 'Resultado del procesamiento';
  static const captureOpenProblems = 'Problemas y tratamientos abiertos';
  static const captureCancelEdit = 'Cancelar edición';

  static String timelineWeightKg(Object v) => '$timelineWeight: $v kg';
  static String timelineHeightCm(Object v) => '$timelineHeight: $v cm';
  static String timelineBmiValue(Object v) => '$timelineBmi: $v';
  static String timelineBloodPressureValue(Object v) =>
      '$timelineBloodPressure: $v';
  static String timelineActionMethodUnsupported(Object method) =>
      'Método no compatible para esta acción: $method';
  static String dictationStartFailed(Object e) => 'No se pudo iniciar dictado: $e';
  static String recordingFinishFailed(Object e) =>
      'No se pudo finalizar la grabación: $e';

  static const guardiaMissingPatient = 'Falta el paciente para el ingreso.';
  static const guardiaBedRequested = 'Solicitud de cama registrada';
  static const guardiaCaseAssigned = 'Caso asignado';
  static const guardiaReferralRegistered = 'Derivación registrada';
  static const guardiaNoScheduleTitle = 'Sin horario';
  static const guardiaNoScheduleBody =
      'No tenés horario de guardia cargado. '
      'Para atender, configurá tus horarios en el Asistente («Configurar mis horarios») '
      'o pedile a coordinación / administración del centro que te los asigne. '
      'Podés intentar igual, pero el servidor puede rechazar la asignación.';
  static const guardiaTryAnyway = 'Intentar igual';
  static const guardiaReferTitle = 'Derivar paciente';
  static const guardiaReferAction = 'Derivar';
  static const guardiaReferTargetLabel = 'Efector destino';
  static const guardiaReferReasonLabel = 'Condiciones / motivo';
  static const guardiaReferRequestBed = 'Solicitar internación (cama)';

  static String errorWithDetail(Object error) => 'Error: $error';
  static String guardiaLoadEffectorsFailed(Object error) =>
      'Error al cargar efectores: $error';
  static String guardiaReferFailed(Object error) =>
      'No se pudo derivar: $error';
}
