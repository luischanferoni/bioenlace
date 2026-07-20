import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// API consulta async (cancelar solicitud) — app paciente.
class ConsultaAsyncApi {
  final String? authToken;

  ConsultaAsyncApi({this.authToken});

  Map<String, String> get _headers => AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: 'bioenlace-paciente',
      );

  Future<Map<String, dynamic>> cancelarComoPaciente(int encounterId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-async/cancelar-como-paciente');
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
        'message': map['message']?.toString() ?? 'No se pudo cancelar la solicitud.',
      };
    } catch (e) {
      return {'success': false, 'message': userFriendlyErrorMessage(e)};
    }
  }
}
