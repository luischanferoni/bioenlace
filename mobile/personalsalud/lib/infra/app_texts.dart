/// Textos de infraestructura de Personal de Salud (avisos de cliente y botones genéricos).
/// Los textos clínicos y de negocio llegan del backend, no se agregan acá.
class AppTexts {
  AppTexts._();

  static const logout = 'Cerrar sesión';
  static const back = 'Volver';
  static const confirm = 'Confirmar';
  static const userInfoTitle = 'Información del usuario';
  static const workAreaTitle = 'Área de trabajo';
  static const featureInDevelopment = 'Funcionalidad en desarrollo';
  static String timelineHistoryLoadError(String detail) =>
      'Error al cargar historia clínica: $detail';
  static String areaChangeFailed(Object e) => 'Error al cambiar área: $e';
  static const selectEffector = 'Seleccioná el efector';
  static const selectService = 'Seleccioná el servicio';
  static const selectArea = 'Seleccioná el área';
  static const scanDniTitle = 'Escanear DNI';
  static const contactPhoneRequired = 'Indicá un teléfono de contacto.';
  static const withoutDocumentNn = 'Sin documento / NN';
  static const triageReasonRequired = 'Indicá el motivo de consulta.';
  static String saveFailed(Object e) => 'No se pudo guardar: $e';
  static const triagePriorityTitle = 'Prioridad (Manchester)';
  static const vitalSignsOptionalTitle = 'Signos vitales (opcional)';
  static String dataLoadFailed(Object? e) => 'Error al cargar datos: $e';
  static const retry = 'Reintentar';
  static const userDataLoadFailed = 'No se pudieron cargar datos de usuario';
  static String screenNotImplemented(Object? id) => 'Pantalla no implementada: $id';
  static const enterUserAndPassword = 'Ingresá usuario y contraseña.';
  static const reenterUserAndPassword = 'Volvé a ingresar con usuario y contraseña.';
  static const appTitle = 'Personal de Salud';
  static const completeActivationFields = 'Completá usuario, código y contraseña.';
  static const passwordsMismatch = 'Las contraseñas no coinciden.';
  static const accountActivated = 'Cuenta activada. Ya podés ingresar.';
  static const activateAccount = 'Activar cuenta';
  static const biometricRequired = 'Biometría requerida';
  static String startAttendanceFailed(Object e) => 'No se pudo iniciar atención: $e';
  static const flowConfirmFromPreviousStep = 'Confirmá el envío desde el paso anterior del flujo.';
  static String flowStepUnavailable(String actionId) => 'No puedo abrir el paso ($actionId).';
  static const screenWithoutApiRoute = 'Pantalla sin ruta API.';
  static const screenTypeUnsupported = 'Tipo de pantalla no soportado en móvil médico.';
  static const exitFlow = 'Salir';
  static const sessionUnavailableForAction = 'Sesión no disponible para esta acción.';
  static const historyTemporarilyUnavailable = 'Historia clínica no disponible temporalmente.';
  static const encounterSaved = 'Consulta guardada';

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
