import 'package:flutter/material.dart';

import '../config/api_config.dart';
import '../scheduling/turno_inicio.dart';
import '../ui_json/ui_json_screen.dart';

/// Ventana de asistencia pre-consulta por cohorte (misma ventana que motivos).
bool turnoAsistenciaCohorteDisponibleEnProducto(Map<String, dynamic> turno) {
  final flag = turno['asistencia_cohorte_disponible'];
  if (flag is bool) return flag;

  return turnoTieneEncounterParaMotivos(turno) &&
      turnoMotivosInputAbiertoEnProducto(turno);
}

int? turnoIdDesdePayloadProducto(Map<String, dynamic> turno) {
  final raw = turno['id'] ?? turno['id_turnos'];
  if (raw is int) return raw > 0 ? raw : null;
  if (raw == null) return null;
  final parsed = int.tryParse(raw.toString());
  return parsed != null && parsed > 0 ? parsed : null;
}

/// Abre el formulario dinámico GET|POST `/api/v1/care-packs/assistance`.
void abrirAsistenciaPreConsulta({
  required BuildContext context,
  required int turnoId,
  String? authToken,
  String appClient = 'paciente-flutter',
  int? subjectPersonaId,
}) {
  var query = 'turno_id=$turnoId';
  if (subjectPersonaId != null && subjectPersonaId > 0) {
    query += '&subject_persona_id=$subjectPersonaId';
  }
  final path = AppConfig.normalizeApiV1Path(
    '/api/v1/care-packs/assistance?$query',
  );
  final uri = path.startsWith('http')
      ? Uri.parse(path)
      : Uri.parse('${AppConfig.apiUrl}$path');
  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => UiJsonScreen(
        apiAbsoluteUrl: uri.toString(),
        authToken: authToken,
        appClient: appClient,
      ),
    ),
  );
}
