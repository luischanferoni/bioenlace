/// Textos de infraestructura del paquete shared (avisos y etiquetas genéricas del cliente).
class SharedTexts {
  SharedTexts._();

  static const biometricUnavailableWeb =
      'La huella y el reconocimiento facial no están disponibles en el navegador. '
      'Usá la app móvil para ingresar.';
  static const diditUnsupportedPlatform =
      'La verificación de identidad está disponible en la app para Android o iPhone.';
  static const diditMissingPlugin =
      'No pudimos iniciar la verificación de identidad. Actualizá la app e '
      'intentá nuevamente. Si el problema continúa, contactá a soporte.';
  static const hcContextSwitchHint =
      'Si activás esta opción, el asistente podrá usar un resumen de tus alergias, '
      'condiciones y medicación. Esto no cambia la información que ve el profesional '
      'durante la consulta.';
  static const welcomeShortcutsHint = 'Elegí un atajo para comenzar o escribí tu consulta abajo.';
  static const journeyNoPendingActions = 'No hay tareas pendientes para este turno.';
  static String journeyAppointmentOf(Object? v) => 'Turno del $v';
  static const journeyPrepareTitle = 'Preparar tu consulta';
  static const journeyFollowupTitle = 'Seguimiento post-consulta';

  static const imageLoadFailed = 'No pudimos mostrar la imagen. Intentá nuevamente.';
  static String biometricConfirmContinue(Object? type, Object? app) =>
      'Confirmá con $type para continuar en $app';
  static const biometricSecurityConfirm = 'Por seguridad, confirmá tu identidad para continuar';
  static String biometricConfirmEnroll(Object? type) =>
      'Confirmá con $type para activar el acceso rápido';
  static const asyncTakeFailed =
      'No pudimos aceptar la solicitud. Intentá nuevamente.';
  static const asyncCloseFailed =
      'No pudimos cerrar esta solicitud. Intentá nuevamente.';
  static const whoToOperateFor = '¿Para quién estás usando Bioenlace?';
  static const previewNoData = 'No hay información disponible para mostrar.';
  static String pendingAppointmentsCount(Object? total) => 'Turnos pendientes ($total):';
  static String andMore(Object? n) => '… y $n más';
  static const confirmAppointmentsToResolution =
      'Confirmá solo si estás de acuerdo con que estos turnos se resuelvan.';
  static const asyncReviewNotice =
      'Un profesional revisará tu solicitud y te responderá en Inicio, en '
      '«Consultas clínicas por mensaje».';

  static String kpiService(Object? v) => 'Servicio: $v';
  static String kpiContext(Object? v) => 'Contexto: $v';
  static const dynamicOptionsRequired =
      'No se pudieron cargar las opciones de este campo. Revisá tu conexión e '
      'intentá nuevamente.';
  static const privacyPolicy = 'Política de privacidad';
  static const playReviewCredentialsNote =
      'Ingresá con los datos de acceso habilitados para la revisión de la app.';
  static String biometricPrompt(Object? type, Object? app) =>
      'Ingresá a $app con $type';
  static const verifyIdentitySelfie = 'Verificá tu identidad con una selfie para volver a entrar.';
  static String biometricReady(Object? type) => 'Ya podés usar $type para ingresar';
  static const signupPrompt = '¿No tenés cuenta? Creá una ahora';
  static const institutionalSignupPrompt =
      '¿Tenés un consultorio? Conocé cómo crear tu cuenta';

  static const biometricUnavailable =
      'La huella y el reconocimiento facial no están disponibles en este dispositivo.';
  static const sessionCheckFailed =
      'No pudimos verificar tu sesión. Revisá la conexión e intentá de nuevo.';
  static const diditLoginNotConfigured =
      'No pudimos iniciar sesión en este momento. Intentá nuevamente más tarde '
      'o contactá a soporte.';
  static String biometricVerificationFailed(Object? _) =>
      'No pudimos verificar tu identidad. Intentá nuevamente.';

  static const reminderFirstDoseHint =
      'Indicá cuándo tomás la primera dosis. Calculamos el resto según la prescripción.';
  static const reminderUnsupportedPlatform =
      'Las alarmas de recordatorio están disponibles en la app para Android o iPhone. '
      'En el navegador podés ver el plan; las preferencias se sincronizan al usar el celular.';
  static const reminderEnableGlobal =
      'Activá "Recordatorios de tratamiento" en Configuración para usar alarmas de este plan.';
  static const reminderNoScheduledItems = 'No hay actividades con horarios para recordar.';

  static String unsupportedWidget(Object? _) =>
      'Esta opción todavía no está disponible.';
  static const noOptions = 'Sin opciones disponibles';
  static const selectOption = 'Seleccioná una opción';
  static const noResults = 'Sin resultados.';
  static String completeField(Object? label) => 'Completá: $label';
  static const retry = 'Reintentar';
  static const invalidUi =
      'No se pudo mostrar esta pantalla. Intentá nuevamente.';
  static String pdfDownloadFailed(String _) =>
      'No pudimos descargar el archivo. Revisá tu conexión e intentá nuevamente.';
  static const invalidUiMissingDraftField =
      'No se pudo mostrar el formulario. Intentá nuevamente.';
  static const confirm = 'Confirmar';
  static const cancel = 'Cancelar';
  static const save = 'Guardar';
  static String selected(Object? label) => 'Seleccionado: $label';
  static const noResultsMinimalSignup =
      'No encontramos resultados. Si es un paciente nuevo, completá sus datos para continuar.';

  static const firstDoseTime = 'Hora de la primera toma';
  static const dailyReminders = 'Recordatorios del día';
  static const reminders = 'Recordatorios';
  static const treatmentReminders = 'Recordatorios de este tratamiento';
  static String remindersCustom(String times) => 'Recordatorios: $times';
  static const chooseFirstDoseTime = 'Elegí la hora de la primera toma';
  static const chooseFirstDoseTimeButton = 'Elegir hora de la primera toma';

  static const privacyPolicyOpenFailed =
      'No pudimos abrir la política de privacidad. Intentá nuevamente.';
  static const institutionalSignupOpenFailed =
      'No pudimos abrir la información para crear tu cuenta. Intentá nuevamente.';
  static const reviewAccessTitle = 'Acceso para revisión';
  static const hcSummaryInAssistantTitle = 'Resumen de historia en el asistente';
  static const yourAccount = 'Tu cuenta';
  static const treatmentRemindersTitle = 'Recordatorios de tratamiento';
  static String pdfReady(String name) => 'Ya está listo el PDF: $name';
  static const calculatingAppointmentImpact = 'Calculando impacto en turnos…';
  static const searching = 'Buscando...';
  static const previous = 'Anterior';
  static String professionalLabel(String name) => 'Profesional: $name';
  static String enableBiometric(String type) => 'Activar $type';
  static const notNow = 'Ahora no';
  static const verificationCancelled = 'Cancelaste la verificación.';
  static String authenticationFailed(Object _) =>
      'No pudimos completar el acceso. Intentá nuevamente. Si el problema '
      'continúa, contactá a soporte.';
}
