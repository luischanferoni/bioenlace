/// Textos de infraestructura del paquete shared (avisos y etiquetas genéricas del cliente).
class SharedTexts {
  SharedTexts._();

  static const diditUnsupportedPlatform =
      'La verificación de identidad (Didit) solo funciona en la app móvil '
      'Android o iOS. Usá un dispositivo o emulador; no está disponible en '
      'navegador ni en escritorio.';
  static const diditMissingPlugin =
      'No se pudo iniciar Didit en este dispositivo. Detené la app, ejecutá '
      '«flutter clean», volvé a compilar e instalá de nuevo (no uses hot reload '
      'ni Chrome).';
  static const journeyNoPendingActions = 'No hay acciones pendientes para este turno.';
  static String journeyAppointmentOf(Object? v) => 'Turno del $v';
  static const journeyPrepareTitle = 'Preparar tu consulta';
  static const journeyFollowupTitle = 'Seguimiento post-consulta';

  static const imageLoadFailed = 'No se pudo cargar la imagen';
  static String biometricConfirmContinue(Object? type, Object? app) =>
      'Confirmá con $type para continuar en $app';
  static const biometricSecurityConfirm = 'Por seguridad, confirmá tu identidad para continuar';
  static String biometricConfirmEnroll(Object? type) =>
      'Confirmá con $type para activar el acceso rápido';
  static const asyncTakeFailed = 'No se pudo tomar la solicitud.';
  static const asyncCloseFailed = 'No se pudo cerrar la solicitud.';
  static const whoToOperateFor = '¿Por quién operás?';
  static const previewNoData = 'Sin datos de preview.';
  static String pendingAppointmentsCount(Object? total) => 'Turnos pendientes ($total):';
  static String andMore(Object? n) => '… y $n más';
  static const confirmAppointmentsToResolution =
      'Confirmá solo si aceptás que esos turnos pasen a resolución.';
  static const asyncReviewNotice =
      'Un profesional la revisará y te responderá por mensaje en Inicio → Consultas clínicas por mensaje.';

  static String kpiService(Object? v) => 'Servicio: $v';
  static String kpiContext(Object? v) => 'Contexto: $v';
  static const dynamicOptionsRequired =
      'Este campo requiere opciones que se cargarán dinámicamente. Por favor, use autocomplete en su lugar.';
  static const privacyPolicy = 'Política de privacidad';
  static const playReviewCredentialsNote =
      'Credenciales provistas en Google Play Console. Uso solo para revisores de la tienda.';
  static String biometricPrompt(Object? type, Object? app) =>
      'Autenticación con $type para ingresar a $app';
  static const verifyIdentitySelfie = 'Verificá tu identidad con una selfie para volver a entrar.';
  static String biometricReady(Object? type) => '$type configurada y lista para usar';
  static const signupPrompt = '¿No tienes cuenta? Registrate aquí';
  static const institutionalSignupPrompt = '¿Tenés tu consultorio? Creá tu cuenta en la web';

  static const biometricUnavailable =
      'La autenticación biométrica no está disponible en este dispositivo';
  static const sessionCheckFailed =
      'No pudimos verificar tu sesión. Revisá la conexión e intentá de nuevo.';
  static const diditLoginNotConfigured =
      'El login biométrico con Didit no está configurado. Contactá soporte.';
  static String biometricVerificationFailed(Object? reason) =>
      'No se pudo completar la verificación biométrica: $reason';

  static const reminderFirstDoseHint =
      'Indicá cuándo tomás la primera dosis. Calculamos el resto según la prescripción.';
  static const reminderUnsupportedPlatform =
      'Las alarmas de recordatorio están disponibles en la app para Android o iPhone. '
      'En el navegador podés ver el plan; las preferencias se sincronizan al usar el celular.';
  static const reminderEnableGlobal =
      'Activá "Recordatorios de tratamiento" en Configuración para usar alarmas de este plan.';
  static const reminderNoScheduledItems = 'No hay actividades con horarios para recordar.';

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
