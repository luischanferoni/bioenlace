import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

/// Servicio para obtener "Mis turnos" del paciente (con tipo_atencion e id_consulta para chat).
class TurnosService {
  final String? authToken;

  TurnosService({this.authToken});

  /// Obtiene el token a usar: el inyectado o el guardado en SharedPreferences (para evitar 401 en turnos/como-paciente).
  Future<String?> _getEffectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) return authToken;
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>> getMisTurnos({
    String? fechaDesde,
    String? fechaHasta,
  }) async {
    try {
      var uri = Uri.parse('${AppConfig.apiUrl}/turnos/listar-como-paciente');
      if (fechaDesde != null || fechaHasta != null) {
        uri = uri.replace(queryParameters: {
          if (fechaDesde != null) 'fecha_desde': fechaDesde,
          if (fechaHasta != null) 'fecha_hasta': fechaHasta,
        });
      }

      final token = await _getEffectiveToken();
      final headers = {
        'Accept': 'application/json',
      };
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }

      final response = await http.get(
        uri,
        headers: headers,
      ).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final data = json.decode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        return {
          'success': true,
          'data': data['data'],
          'turnos': data['data']?['turnos'] ?? [],
          'total': data['data']?['total'] ?? 0,
        };
      }
      final message = response.statusCode == 401
          ? (data['message'] ?? 'No autorizado. Iniciá sesión de nuevo para ver tus turnos.')
          : (data['message'] ?? 'Error al cargar turnos');
      return {
        'success': false,
        'message': message,
        'turnos': [],
        'total': 0,
      };
    } catch (e) {
      return {
        'success': false,
        'message': e.toString(),
        'turnos': [],
        'total': 0,
      };
    }
  }
}
