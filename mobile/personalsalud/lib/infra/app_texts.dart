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
  static const vitalsTriageHint = 'Enteros de 2–3 dígitos. TA sys 50–250 · TA dia 30–150 · FC 20–250.';
  static const pendingCapturesTitle = 'Notas pendientes en el teléfono';
  static const pendingCaptureItem = 'Nota pendiente';
  static const audioRecordingNotStarted =
      'El navegador no inició la grabación. Verificá el micrófono y el permiso de acceso.';
  static const dniScanHint = 'Apuntá la cámara al código de barras del dorso del DNI.';
  static String assistantGreeting(Object? name) => 'Hola, $name';
  static const assistantHowCanIHelp = '¿En qué puedo ayudarte?';
  static const messageEncounterTitle = 'Consulta clínica por mensaje';
  static String areaUpdated(Object? label) =>
      'Área actualizada: $label. Andá a Inicio para ver el cambio.';
  static String configUser(Object? name) => 'Usuario: $name';
  static const configNotifications = 'Notificaciones';
  static const configTheme = 'Tema';
  static String appointmentMode(Object? v) => 'Modalidad: $v';
  static const documentationEmpty = 'Sin datos registrados en esta consulta.';
  static const recentDiagnoses = 'Diagnósticos recientes';
  static const chronicConditions = 'Condiciones crónicas';
  static const vitalsCurrent = 'Signos vitales actuales';
  static String vitalsCurrentWithDate(Object? fecha) => 'Signos vitales actuales ($fecha)';
  static const vitalsEmptyEpisode =
      'Sin signos vitales. Se cargan en la captura de la consulta (texto/audio) o en el triage de admisión.';
  static String vitalsHistoryMeasurements(Object? n) => 'Historial ($n mediciones)';
  static String vitalsHistoryRecords(Object? n) => 'Historial ($n registros)';
  static const appointmentNext = 'Siguiente turno';
  static const appointmentsEmptyForDate = 'No hay turnos programados para esta fecha.';
  static String appointmentTime(Object? hora) => 'Hora: $hora';
  static String appointmentService(Object? servicio) => 'Servicio: $servicio';
  static const appointmentNoService = 'Sin servicio';
  static String appointmentNotes(Object? obs) => 'Observaciones: $obs';
  static String appointmentChatTitle(Object? nombre) => 'Chat con $nombre';
  static const timelineReasonsSummaryPending = 'Generando resumen… Actualizá en unos segundos.';
  static const timelineReasonsEmpty = 'Sin motivos registrados para esta consulta.';
  static const timelineReasonsGuidance = 'Orientación según motivos del paciente';
  static const timelineSummary = 'Resumen';
  static const timelineDiagnoses = 'Diagnósticos a considerar';
  static const timelinePractices = 'Prácticas / estudios';
  static const timelinePreconsult = 'Asistencia pre-consulta (cohorte)';
  static const voiceConfigLoading = 'La configuración de voz todavía se está cargando.';
  static const voiceConfigLoadFailed =
      'No se pudo cargar la configuración de voz. Dictado deshabilitado.';
  static const voiceListening = 'Escuchando…';
  static const voiceRecording = 'Grabando…';
  static const voiceNoSpeech = 'Sin voz detectada';
  static const voiceReady = 'Listo';
  static const voiceAudioReady = 'Audio listo';
  static const voiceAudioFailed = 'No se pudo capturar el audio.';
  static const voiceAudioNone = 'No se capturó audio.';
  static const voiceAudioNoneHint =
      'No se capturó audio. Conectá un micrófono y volvé a intentar.';
  static const voiceAudioPendingText = '(audio pendiente de transcribir)';
  static const microphonePermissionNeeded =
      'Para grabar el audio, habilitá el permiso de micrófono en la app.';
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
  static const timelineTitleLoaded = 'Consulta cargada';
  static const timelineTitleGuardia = 'Atención · guardia';
  static const timelineTitleInternacion = 'Evolución · internación';
  static const timelineTitleHistory = 'Historia clínica';
  static const timelineNoData = 'No hay datos disponibles';
  static const captureSaveFailed = 'No se pudo guardar';
  static const reviewNoteTitle = 'Nota de esta atención';
  static const reviewDuplicateNote =
      'Esta nota es casi idéntica a una evolución previa. Editá el texto para documentar los cambios.';
  static const reviewNoStructuredData =
      'Sin datos estructurados. Se guardará el texto.';
  static const reviewSuggestedByAi = 'Sugerido por IA';
  static const reviewOptionalStatus =
      'Opcional: indicá el estado al cerrar. Si no elegís, se mantienen.';
  static const reviewCompleteData = 'Completar datos';
  static const settingsNoSessionEffector =
      'No se encontró el efector y servicio de la sesión actual.';
  static const settingsEffectorDisabled =
      'El efector actual ya no está habilitado para esta sesión.';
  static const settingsCompleteInitialSetup =
      'Completá la configuración inicial (efector y servicio) para cambiar el área.';
  static const settingsAreaHint =
      'Según el área, Inicio mostrará turnos, pacientes internados o ingresos en guardia.';
  static const chatNoShortcuts = 'No hay atajos disponibles.';
  static const chatProcessError =
      'Error al procesar tu consulta. Por favor, intenta nuevamente.';
  static const chatLocationPermission =
      'Se necesita permiso de ubicación para listar centros de salud cercanos.';
  static const chatMissingService =
      'Falta el servicio elegido para listar centros de salud. Volvé a elegir el servicio en el paso anterior o iniciá de nuevo el trámite de turno.';
  static const chatFlowStartFailed = 'No se pudo iniciar el flujo.';
  static const chatActionWithoutClientOpen =
      'Acción sin client_open: no se puede abrir en esta versión.';
  static const chatLegacySubmitUnsupported =
      'Submit legacy no soportado. Abrí la pantalla desde client_open.';
  static const chatAdvanceFailed = 'No se pudo avanzar. Intentá de nuevo.';
  static const chatCompleteInfo = 'Completa la información';
  static const chatLoadReasons = 'Cargar motivos de la consulta';
  static const chatReasonsTitle = 'Motivos de la consulta';
  static const loginChooseRole = 'Elegí con qué rol entrar';
  static const loginBiometricUnavailable =
      'La huella no está disponible en este dispositivo.';
  static const loginSessionExpired =
      'Tu sesión expiró. Ingresá de nuevo con usuario y contraseña.';
  static const loginSessionCheckFailed =
      'No pudimos verificar tu sesión. Revisá la conexión e intentá de nuevo.';
  static const loginSessionIncomplete =
      'Sesión incompleta. Ingresá con usuario y contraseña.';
  static const loginFirstTime =
      'Primera vez: activá tu cuenta con el código de administración '
      'o ingresá si ya elegiste contraseña.';
  static const wizardNoContextToken =
      'El servidor no devolvió token de contexto operativo. '
      'No se puede usar la app sin completar efector, servicio y área.';
  static const wizardEffectorsNeedSetup =
      'Algunos efectores requieren configuración';
  static const wizardChooseService = 'Elegí el servicio donde vas a trabajar.';
  static const wizardNoServices = 'No hay servicios disponibles';
  static const wizardChooseEncounterType =
      'Elegí el tipo de atención (ambulatorio, guardia, internación, etc.).';
  static const wizardNoAreas =
      'Este efector no tiene áreas de atención habilitadas.';
  static const homeNoAreaMetadata =
      'El servidor no devolvió metadata para el área operativa seleccionada.';
  static const homeAreaWithoutCapabilities =
      'El área operativa seleccionada no tiene capacidades habilitadas.';
  static const sessionExpiredShort = 'Tu sesión expiró. Ingresá de nuevo.';
  static const homeNoGuardiaSchedule =
      'No tenés horario de guardia cargado. Para ver el tablero y atender, '
      'configurá tus horarios en el Asistente («Configurar mis horarios») o pedile a '
      'coordinación / administración del centro que te los asigne.';
  static const homeNoWardSchedule =
      'No tenés horario de piso cargado. Para ver internados, configurá tus '
      'horarios en el Asistente («Configurar mis horarios») o pedile a coordinación / '
      'administración del centro que te los asigne.';
  static const homeNoHomeScreen =
      'El área operativa no tiene una pantalla de inicio habilitada.';
  static const homeBedChange = 'Cambio de cama';
  static const homePendingTriage =
      'Pendiente de triage. Lo registra admisión o enfermería.';
  static const homeDoctorAttending =
      'El médico atiende el caso. Enfermería registra triage o una nota.';
  static const captureWriteEvolution = 'Escribí o dictá la evolución.';
  static const captureWriteNote = 'Escriba o dicte la consulta.';
  static const captureDictationEmpty =
      'No se obtuvo texto del dictado. El audio quedó guardado; escribí la consulta para continuar.';
  static const captureNothingToAnalyze = 'Sin texto para analizar.';
  static const captureSaveBlockedBySystemError =
      'No se puede guardar: el análisis tiene errores de sistema.';
  static const captureEditDuplicateNote =
      'Editá el texto: la nota es casi idéntica a una evolución previa del episodio.';
  static const captureResolveMissingData =
      'Completá los datos faltantes marcados antes de confirmar.';
  static const captureSavedIncomplete =
      'Encounter guardado con datos incompletos.';
  static const captureSavedIncompleteTitle = 'Guardado incompleto';
  static const captureDefineParent =
      'Defina contexto de consulta (parent) para captura con IA.';

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
