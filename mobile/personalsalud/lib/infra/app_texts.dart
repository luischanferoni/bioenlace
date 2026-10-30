/// Textos de infraestructura de Personal de Salud (avisos de cliente y botones genéricos).
/// Los textos clínicos y de negocio llegan del backend, no se agregan acá.
class AppTexts {
  AppTexts._();

  static const logout = 'Cerrar sesión';
  static const back = 'Volver';
  static const confirm = 'Confirmar';
  static const userInfoTitle = 'Información del usuario';
  static const workAreaTitle = 'Área de trabajo';
  static const featureInDevelopment = 'Esta opción todavía no está disponible.';
  static String timelineHistoryLoadError(String _) =>
      'No pudimos cargar la historia clínica. Revisá tu conexión e intentá nuevamente.';
  static String areaChangeFailed(Object _) =>
      'No pudimos cambiar el área de trabajo. Intentá nuevamente.';
  static const selectEffector = 'Seleccioná el centro de salud';
  static const selectService = 'Seleccioná el servicio';
  static const selectArea = 'Seleccioná el área';
  static const scanDniTitle = 'Escanear DNI';
  static const contactPhoneRequired = 'Indicá un teléfono de contacto.';
  static const withoutDocumentNn = 'Sin documento / NN';
  static const triageReasonRequired = 'Indicá el motivo de consulta.';
  static String saveFailed(Object _) =>
      'No se pudo guardar la información. Intentá nuevamente.';
  static const triagePriorityTitle = 'Prioridad (Manchester)';
  static const vitalSignsOptionalTitle = 'Signos vitales (opcional)';
  static String dataLoadFailed(Object? _) =>
      'No pudimos cargar la información. Revisá tu conexión e intentá nuevamente.';
  static const retry = 'Reintentar';
  static const userDataLoadFailed =
      'No pudimos cargar tus datos. Revisá tu conexión e intentá nuevamente.';
  static String screenNotImplemented(Object? _) =>
      'Esta pantalla todavía no está disponible.';
  static const enterUserAndPassword = 'Verificá tu identidad para continuar.';
  static const reenterUserAndPassword = 'Verificá tu identidad para volver a ingresar.';
  static const appTitle = 'Personal de Salud';
  static const completeActivationFields = 'Completá usuario, código y contraseña.';
  static const passwordsMismatch = 'Las contraseñas no coinciden.';
  static const accountActivated = 'Cuenta activada. Ya podés ingresar.';
  static const activateAccount = 'Activar cuenta';
  static const biometricRequired =
      'Configurá la huella o el reconocimiento facial para continuar.';
  static String startAttendanceFailed(Object _) =>
      'No se pudo iniciar la atención. Intentá nuevamente.';
  static const flowConfirmFromPreviousStep =
      'Volvé al paso anterior y confirmá el envío para continuar.';
  static String flowStepUnavailable(String _) =>
      'No pudimos abrir este paso. Volvé a intentarlo.';
  static const screenWithoutApiRoute =
      'Esta opción no está disponible en este momento.';
  static const screenTypeUnsupported =
      'Esta opción todavía no está disponible en la app.';
  static const exitFlow = 'Salir';
  static const sessionUnavailableForAction =
      'Tu sesión no está disponible. Volvé a ingresar para continuar.';
  static const historyTemporarilyUnavailable =
      'La historia clínica no está disponible por el momento. Intentá nuevamente.';
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
  static const timelineActionNoScreen =
      'No se puede abrir esta opción en este momento.';
  static const timelineReasonsTitle = 'Motivos de esta consulta';
  static const guardiaAdmissionTitle = 'Ingreso a internación';
  static const guardiaLeftTitle = 'Paciente se retiró';
  static const vitalsTriageHint = 'Enteros de 2–3 dígitos. TA sys 50–250 · TA dia 30–150 · FC 20–250.';
  static const pendingCapturesTitle = 'Notas pendientes en el teléfono';
  static const pendingCaptureItem = 'Nota pendiente';
  static const notesDraftTitle = 'Notas del borrador';
  static const notesHistoryTitle = 'Historia clínica';
  static const notesExpand = 'Expandir';
  static const notesCollapse = 'Contraer';
  static const noteAudio = 'Nota de audio';
  static const noteText = 'Nota de texto';
  static const noteWaitingText = 'Esperando el texto';
  static const noteDelete = 'Borrar';
  static const noteCancel = 'Cancelar';
  static const noteRetry = 'Reintentar';
  static const notePlay = 'Escuchar';
  static const noteStop = 'Detener';
  static const noteAudioUnavailable = 'El audio ya no está en este teléfono.';
  static const noteDeleteAudioTitle = 'Borrar el audio';
  static const noteDeleteAudioBody =
      'El audio se elimina y no se puede recuperar. ¿Querés borrarlo?';
  static const noteLimitReached =
      'Llegaste al máximo de notas. Analizalas o borrá alguna.';
  static const noteAnalyzeBlocked =
      'Hay notas sin texto: reintentá o borralas para analizar.';
  static const noteBackToNotes = 'Volver a las notas';
  static String noteAnalyzeButton(int count) =>
      count == 1 ? 'Analizar la nota' : 'Analizar $count notas';
  static String noteOrdinal(int index) => 'Nota $index';
  static const audioRecordingNotStarted =
      'El navegador no inició la grabación. Verificá el micrófono y el permiso de acceso.';
  static const dniScanHint = 'Apuntá la cámara al código de barras del dorso del DNI.';
  static String assistantGreeting(Object? name) => 'Hola, $name';
  static const assistantHowCanIHelp = '¿En qué puedo ayudarte?';
  static const messageEncounterTitle = 'Consulta clínica por mensaje';
  static const unnamedPerson = 'Sin nombre';
  static const unnamedPatient = 'Sin paciente';
  static const activationCodeHint =
      'Ingresá el código que te entregó administración de tu centro '
      'y elegí tu contraseña.';
  static const biometricRequiredBody =
      'Este dispositivo debe tener huella digital o Face ID configurado '
      'para usar Personal de Salud.';
  static const wizardTitle = 'Configuración inicial';
  static const vitalSystolic = 'TA sistólica';
  static const vitalDiastolic = 'TA diastólica';
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
  static const timelinePreconsult = 'Orientación antes de la consulta';
  static const voiceConfigLoading = 'Preparando el dictado…';
  static const voiceConfigLoadFailed =
      'No pudimos habilitar el dictado. Podés escribir la consulta.';
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
  static const captureDeleteFailed =
      'No se pudo borrar la nota. Intentá nuevamente.';
  static const captureDeleted = 'Eliminada';
  static const captureMissingParent =
      'No encontramos la consulta asociada. Volvé a abrirla e intentá nuevamente.';
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
      'No pudimos procesar tu consulta. Intentá nuevamente.';
  static const chatLocationPermission =
      'Se necesita permiso de ubicación para listar centros de salud cercanos.';
  static const chatMissingService =
      'Elegí nuevamente el servicio en el paso anterior o iniciá de nuevo la solicitud de turno.';
  static const chatFlowStartFailed =
      'No se pudo iniciar esta consulta. Intentá nuevamente.';
  static const chatActionWithoutClientOpen =
      'No se puede abrir esta opción en este momento.';
  static const chatLegacySubmitUnsupported =
      'No se puede completar esta acción en este momento. Volvé a intentarlo.';
  static const chatAdvanceFailed = 'No se pudo avanzar. Intentá de nuevo.';
  static const chatCompleteInfo = 'Completá la información.';
  static const chatLoadReasons = 'Cargar motivos de la consulta';
  static const chatReasonsTitle = 'Motivos de la consulta';
  static const loginChooseRole = 'Elegí con qué rol entrar';
  static const loginBiometricUnavailable =
      'La huella o el reconocimiento facial no están disponibles en este dispositivo.';
  static const loginSessionExpired =
      'Tu sesión expiró. Verificá tu identidad para volver a ingresar.';
  static const loginSessionCheckFailed =
      'No pudimos verificar tu sesión. Revisá la conexión e intentá de nuevo.';
  static const loginSessionIncomplete =
      'No pudimos recuperar tu sesión. Verificá tu identidad para ingresar.';
  static const loginReverifyIdentity =
      'No hay sesión guardada. Verificá tu identidad para ingresar.';
  static const loginFirstTime =
      'Ingresá con tu identidad o creá tu cuenta desde la app.';
  static const wizardNoContextToken =
      'No pudimos completar la configuración de tu lugar de trabajo. Revisá el '
      'centro, servicio y área seleccionados. Si el problema continúa, contactá a soporte.';
  static const wizardEffectorsNeedSetup =
      'Algunos efectores requieren configuración';
  static const wizardCreateConsultorioHint =
      '¿Querés trabajar por tu cuenta? Creá tu consultorio privado desde aquí.';
  static const wizardCreateConsultorio = 'Crear mi consultorio';
  static const wizardCreateConsultorioSoon =
      'La creación de consultorios todavía no está disponible desde la app.';
  static const wizardChooseService = 'Elegí el servicio donde vas a trabajar.';
  static const wizardNoServices = 'No hay servicios disponibles';
  static const wizardChooseEncounterType =
      'Elegí el tipo de atención (ambulatorio, guardia, internación, etc.).';
  static const wizardNoAreas =
      'Este centro no tiene áreas de atención disponibles.';
  static const homeNoAreaMetadata =
      'No pudimos cargar la información de esta área. Intentá nuevamente.';
  static const homeAreaWithoutCapabilities =
      'Esta área no tiene funciones disponibles. Elegí otra área o contactá a administración.';
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
      'No hay una pantalla de inicio disponible para esta área. Elegí otra área o contactá a administración.';
  static const homeBedChange = 'Cambio de cama';
  static const homePendingTriage =
      'Pendiente de triage. Lo registra admisión o enfermería.';
  static const homeDoctorAttending =
      'El médico atiende el caso. Enfermería registra triage o una nota.';
  static const captureWriteEvolution = 'Escribí o dictá la evolución.';
  static const captureWriteNote = 'Escribí o dictá la consulta.';
  static const captureDictationEmpty =
      'No se obtuvo texto del dictado. El audio quedó guardado; escribí la consulta para continuar.';
  static const captureNothingToAnalyze = 'Sin texto para analizar.';
  static const captureSaveBlockedBySystemError =
      'No se pudo guardar porque ocurrió un problema al analizar la nota. Intentá nuevamente.';
  static const captureEditDuplicateNote =
      'Editá el texto: la nota es casi idéntica a una evolución previa del episodio.';
  static const captureResolveMissingData =
      'Completá los datos faltantes marcados antes de confirmar.';
  static const captureSavedIncomplete =
      'La consulta se guardó con algunos datos incompletos.';
  static const captureSavedIncompleteTitle = 'Guardado incompleto';
  static const captureDefineParent =
      'No encontramos la consulta asociada. Volvé a abrirla e intentá nuevamente.';

  static String timelineWeightKg(Object v) => '$timelineWeight: $v kg';
  static String timelineHeightCm(Object v) => '$timelineHeight: $v cm';
  static String timelineBmiValue(Object v) => '$timelineBmi: $v';
  static String timelineBloodPressureValue(Object v) =>
      '$timelineBloodPressure: $v';
  static String timelineActionMethodUnsupported(Object _) =>
      'Esta opción no está disponible en este momento.';
  static String dictationStartFailed(Object _) =>
      'No se pudo iniciar el dictado. Intentá nuevamente o escribí la consulta.';
  static String recordingFinishFailed(Object _) =>
      'No se pudo finalizar la grabación. Intentá nuevamente.';

  static const guardiaMissingPatient = 'Seleccioná un paciente para continuar.';
  static const guardiaBedRequested = 'Solicitud de cama registrada';
  static const guardiaCaseAssigned = 'Caso asignado';
  static const guardiaReferralRegistered = 'Derivación registrada';
  static const guardiaNoScheduleTitle = 'Sin horario';
  static const guardiaNoScheduleBody =
      'No tenés horario de guardia cargado. '
      'Para atender, configurá tus horarios en el Asistente («Configurar mis horarios») '
      'o pedile a coordinación / administración del centro que te los asigne. '
      'Podés intentar igual, pero tal vez no puedas asignar el caso.';
  static const guardiaTryAnyway = 'Intentar igual';
  static const guardiaReferTitle = 'Derivar paciente';
  static const guardiaReferAction = 'Derivar';
  static const guardiaReferTargetLabel = 'Centro de salud de destino';
  static const guardiaReferReasonLabel = 'Condiciones / motivo';
  static const guardiaReferRequestBed = 'Solicitar internación (cama)';

  static String errorWithDetail(Object _) =>
      'Ocurrió un problema. Intentá nuevamente.';
  static String guardiaLoadEffectorsFailed(Object _) =>
      'No pudimos cargar los centros de salud. Revisá tu conexión e intentá nuevamente.';
  static String guardiaReferFailed(Object _) =>
      'No se pudo derivar al paciente. Revisá los datos e intentá nuevamente.';
}
