/// Utilidades para turnos en estado EN_RESOLUCION (reubicación).
class TurnoResolucionUtils {
  static bool esEnResolucion(Map<String, dynamic> t) {
    if (t['estado']?.toString() == 'EN_RESOLUCION') {
      return true;
    }
    return t['en_resolucion'] == true;
  }

  /// Intent del asistente según origen de la resolución.
  static String intentResolver(Map<String, dynamic> t) {
    final res = t['turno_resolucion'];
    if (res is Map) {
      final origen = res['origen']?.toString() ?? '';
      final vecinas = res['tiene_opciones_vecinas'] == true;
      if (origen == 'cambio_agenda' && vecinas) {
        return 'turnos.conflicto-agenda-flow';
      }
    }
    return 'turnos.reubicar-como-paciente-flow';
  }

  static String etiquetaLista(Map<String, dynamic> t) {
    final fecha = t['fecha']?.toString() ?? '';
    final hora = t['hora']?.toString() ?? '';
    final svc = t['servicio']?.toString() ?? '';
    final prof = t['profesional']?.toString() ?? '';
    return [fecha, hora, svc, prof].where((s) => s.isNotEmpty).join(' · ');
  }
}

/// Turno a resolver al abrir el asistente desde Inicio.
class PendingTurnoResolver {
  final Map<String, dynamic> turno;

  const PendingTurnoResolver(this.turno);
}
