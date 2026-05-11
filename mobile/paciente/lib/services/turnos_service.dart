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
      final uri = Uri.parse('${AppConfig.apiUrl}/turnos/listar-como-paciente');

      final token = await _getEffectiveToken();
      final headers = AppConfig.jsonHeaders(
        bearerToken: token,
        appClient: 'paciente-flutter',
      );

      final body = <String, dynamic>{
        if (fechaDesde != null) 'fecha_desde': fechaDesde,
        if (fechaHasta != null) 'fecha_hasta': fechaHasta,
      };

      final response = await http
          .post(
            uri,
            headers: headers,
            body: json.encode(body),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final data = json.decode(response.body) as Map<String, dynamic>;

      if (response.statusCode == 200 && data['success'] == true) {
        final block = data['data'];
        final turnos = block is Map<String, dynamic> ? block['turnos'] : null;
        final total = block is Map<String, dynamic> ? block['total'] : null;
        return {
          'success': true,
          'data': block,
          'turnos': turnos is List<dynamic> ? turnos : [],
          'total': total is int ? total : int.tryParse('$total') ?? 0,
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
