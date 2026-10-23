/// Textos de infraestructura de la app Paciente (avisos y etiquetas genéricas del cliente).
/// Los textos clínicos y de negocio vienen del backend.
class AppTexts {
  static const logout = 'Cerrar sesión';
  static const comingSoon = 'Próximamente';
  static const sectorUpdateFailed = 'No se pudo actualizar el sector';
  static const loginToSendComplaint = 'Iniciá sesión para enviar una queja';
  static const prescriptionsTitle = 'Recetas';
  static const labResultsTitle = 'Resultados de laboratorio';
  static const ordersTitle = 'Pedidos e indicaciones';
  static const audioSavedUploading = 'Audio guardado en el teléfono. Subiendo…';
  static const pendingMessageDeleted = 'Mensaje pendiente eliminado';
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
}
