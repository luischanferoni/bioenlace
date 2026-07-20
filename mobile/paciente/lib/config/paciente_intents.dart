/// Identificadores de intents del asistente usados como entrypoints en la app paciente.
abstract final class PacienteIntents {
  static const enviarQueja = 'plataforma.enviar-queja-como-paciente-flow';
  /// Control/Seguimiento y solicitudes sobre tratamiento (antes consultas-seguimiento-flow).
  static const solicitarAtencion = 'atencion.necesito-atencion';
}
