import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Servicio para obtener "Mis turnos" del paciente (con tipo_atencion e id_consulta para chat).
class TurnosService {
  final String? authToken;

  TurnosService({this.authToken});

  Future<Map<String, dynamic>> getMisTurnos({
    String? fechaDesde,
    String? fechaHasta,
  }) async {
    try {
      var uri = Uri.parse('${AppConfig.apiUrl}/turnos/mis-turnos');
      if (fechaDesde != null || fechaHasta != null) {
        uri = uri.replace(queryParameters: {
          if (fechaDesde != null) 'fecha_desde': fechaDesde,
          if (fechaHasta != null) 'fecha_hasta': fechaHasta,
        });
      }

      final headers = {
        'Accept': 'application/json',
      };
      if (authToken != null && authToken!.isNotEmpty) {
        headers['Authorization'] = 'Bearer $authToken';
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
      return {
        'success': false,
        'message': data['message'] ?? 'Error al cargar turnos',
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
