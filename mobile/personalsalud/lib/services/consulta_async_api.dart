import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// API de consultas clínicas async (bandeja staff).
class ConsultaAsyncApi {
  final String? authToken;
  final String? userId;

  ConsultaAsyncApi({this.authToken, this.userId});

  Map<String, String> get _headers => AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: 'personalsalud-flutter',
      );

  /// POST /api/v1/consulta-async/tomar-como-staff
  Future<Map<String, dynamic>> tomarComoStaff(int encounterId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-async/tomar-como-staff');
      final response = await http
          .post(
            uri,
            headers: _headers,
            body: json.encode({'encounter_id': encounterId}),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = json.decode(utf8.decode(response.bodyBytes));
      if (data is! Map) {
        return {'success': false, 'message': 'Respuesta inválida'};
      }
      final map = Map<String, dynamic>.from(data);
      if (response.statusCode >= 200 &&
          response.statusCode < 300 &&
          map['success'] != false) {
        return {'success': true, 'data': map['data']};
      }
      return {
        'success': false,
        'message': map['message']?.toString() ?? 'No se pudo tomar la solicitud.',
      };
    } catch (e) {
      return {'success': false, 'message': userFriendlyErrorMessage(e)};
    }
  }

  /// POST /api/v1/consulta-async/cerrar-como-staff
  Future<Map<String, dynamic>> cerrarComoStaff(
    int encounterId,
    String resolutionCode, {
    String? note,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-async/cerrar-como-staff');
      final body = <String, dynamic>{
        'encounter_id': encounterId,
        'resolution_code': resolutionCode,
      };
      if (note != null && note.trim().isNotEmpty) {
        body['note'] = note.trim();
      }
      final response = await http
          .post(
            uri,
            headers: _headers,
            body: json.encode(body),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = json.decode(utf8.decode(response.bodyBytes));
      if (data is! Map) {
        return {'success': false, 'message': 'Respuesta inválida'};
      }
      final map = Map<String, dynamic>.from(data);
      if (response.statusCode >= 200 &&
          response.statusCode < 300 &&
          map['success'] != false) {
        return {'success': true, 'data': map['data']};
      }
      return {
        'success': false,
        'message': map['message']?.toString() ?? 'No se pudo cerrar la solicitud.',
      };
    } catch (e) {
      return {'success': false, 'message': userFriendlyErrorMessage(e)};
    }
  }
}
