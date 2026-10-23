// lib/services/turnos_service.dart
import 'package:shared/shared.dart';

import '../models/turno.dart';

class TurnosService {
  String? authToken;
  String? userId; // Para desarrollo/simulación

  TurnosService({this.authToken, this.userId});

  ApiClient get _api =>
      ApiClient(authToken: authToken, appClient: 'personalsalud-flutter');

  /// Agenda del día: GET profesional-agenda/dia (contrato v1: `data.turnos`).
  Future<List<Turno>> getTurnosPorFecha(
    String fecha, {
    String? idProfesionalEfectorServicio,
  }) async {
    try {
      final query = <String, String>{'fecha': fecha};
      if (idProfesionalEfectorServicio != null) {
        query['id_profesional_efector_servicio'] = idProfesionalEfectorServicio;
      }
      // Desarrollo/simulación: user_id como parámetro si no hay token válido.
      if (userId != null &&
          (authToken == null ||
              authToken!.isEmpty ||
              authToken!.startsWith('simulated_'))) {
        query['user_id'] = userId!;
      }
      final data = await _api.getData('/profesional-agenda/dia', query: query);
      final raw = data['turnos'];
      if (raw is! List) return [];
      return raw
          .map((t) => Turno.fromJson(Map<String, dynamic>.from(t as Map)))
          .toList();
    } catch (e) {
      print('Error fetching turnos: $e');
      rethrow;
    }
  }
  /// Detalle vía POST turnos/ver-turno (UiScreenService: el GET solo devuelve descriptor UI).
  Future<Turno> getTurno(int id) async {
    try {
      final data = await _api.postData('/turnos/ver-turno', body: {'id': id});
      if (data.isEmpty) throw Exception('Error al obtener turno');
      return Turno.fromJson(data);
    } catch (e) {
      print('Error fetching turno: $e');
      rethrow;
    }
  }

  /// Alta operativa: POST turnos/para-paciente (respuesta típica 200 + kind ui_submit_result).
  Future<Turno> crearTurno(Map<String, dynamic> datosTurno) async {
    try {
      final data = await _api.postData('/turnos/para-paciente', body: datosTurno);
      final idRaw = data['id'];
      final turnoId = idRaw is int ? idRaw : int.tryParse('$idRaw');
      if (turnoId != null && turnoId > 0) {
        return await getTurno(turnoId);
      }
      throw Exception(data['message']?.toString() ?? 'Error al crear turno');
    } catch (e) {
      print('Error creating turno: $e');
      rethrow;
    }
  }

  /// Actualización vía POST turnos/actualizar-turno (UiScreenService no ejecuta submit en PUT).
  Future<Turno> actualizarTurno(int id, Map<String, dynamic> datosTurno) async {
    try {
      await _api.postData(
        '/turnos/actualizar-turno',
        body: {'id': id, ...datosTurno},
      );
      return await getTurno(id);
    } catch (e) {
      print('Error updating turno: $e');
      rethrow;
    }
  }
}