import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

/// Servicio para obtener "Mis turnos" del paciente (con tipo_atencion e id_consulta para chat).
class TurnosService {
  final String? authToken;

  TurnosService({this.authToken});

  static String _extractErrorMessage(Map<String, dynamic> data, int statusCode) {
    if (statusCode == 401) {
      return data['message'] as String? ??
          'No autorizado. Iniciá sesión de nuevo para ver tus turnos.';
    }
    final errors = data['errors'];
    if (errors is Map) {
      final global = errors['_error'];
      if (global is List && global.isNotEmpty) {
        return global.first.toString();
      }
      if (global is String && global.isNotEmpty) {
        return global;
      }
    }
    return data['message'] as String? ?? 'Error al cargar turnos';
  }

  /// Obtiene el token a usar: el inyectado o el guardado en SharedPreferences (para evitar 401 en turnos/como-paciente).
  Future<String?> _getEffectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) return authToken;
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  /// [alcance]: `pendientes` (inicio ≥ ahora, activos) o `pasados` (inicio &lt; ahora, historial).
  /// Sin [alcance]: comportamiento API legacy (rango de fechas).
  Future<Map<String, dynamic>> getMisTurnos({
    String? fechaDesde,
    String? fechaHasta,
    String? alcance,
    int? limit,
    int? offset,
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
        if (alcance != null) 'alcance': alcance,
        if (limit != null) 'limit': limit,
        if (offset != null) 'offset': offset,
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
        if (block is! Map<String, dynamic>) {
          return {
            'success': false,
            'message': 'Respuesta de turnos sin datos (formato inesperado).',
            'turnos': <dynamic>[],
            'total': 0,
          };
        }
        final turnos = block['turnos'];
        final totalRaw = block['total'];
        final total = totalRaw is int ? totalRaw : int.tryParse('$totalRaw') ?? 0;
        return {
          'success': true,
          'data': block,
          'turnos': turnos is List<dynamic> ? turnos : [],
          'total': total,
        };
      }
      if (response.statusCode == 200 &&
          data['kind'] == 'ui_definition' &&
          data['success'] == true) {
        return {
          'success': false,
          'message':
              'La API devolvió un formulario en lugar del listado. Enviá POST con alcance (pendientes/pasados).',
          'turnos': <dynamic>[],
          'total': 0,
        };
      }
      final message = _extractErrorMessage(data, response.statusCode);
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
