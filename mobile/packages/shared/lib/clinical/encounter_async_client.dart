import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../http/api_contract.dart';
import '../text/user_friendly_error.dart';

/// HTTP client for patient and staff actions on asynchronous encounters.
class EncounterAsyncClient {
  final String? authToken;
  final String appClient;
  final http.Client? client;

  EncounterAsyncClient({this.authToken, required this.appClient, this.client});

  Map<String, String> get _headers =>
      AppConfig.jsonHeaders(bearerToken: authToken, appClient: appClient);

  Future<Map<String, dynamic>> cancelarComoPaciente(int encounterId) =>
      _postAction('cancelar-como-paciente', {
        'encounter_id': encounterId,
      }, 'No se pudo cancelar la solicitud.');

  Future<Map<String, dynamic>> tomarComoStaff(int encounterId) => _postAction(
    'tomar-como-staff',
    {'encounter_id': encounterId},
    'No se pudo tomar la solicitud.',
  );

  Future<Map<String, dynamic>> cerrarComoStaff(
    int encounterId,
    String resolutionCode, {
    String? note,
  }) {
    final body = <String, dynamic>{
      'encounter_id': encounterId,
      'resolution_code': resolutionCode,
    };
    if (note != null && note.trim().isNotEmpty) {
      body['note'] = note.trim();
    }
    return _postAction(
      'cerrar-como-staff',
      body,
      'No se pudo cerrar la solicitud.',
    );
  }

  Future<Map<String, dynamic>> _postAction(
    String action,
    Map<String, dynamic> body,
    String fallbackMessage,
  ) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/encounter-async/$action');
      final response =
          client == null
              ? await http
                  .post(uri, headers: _headers, body: json.encode(body))
                  .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds))
              : await client!
                  .post(uri, headers: _headers, body: json.encode(body))
                  .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final decoded = json.decode(utf8.decode(response.bodyBytes));
      if (decoded is! Map) {
        return {'success': false, 'message': 'Respuesta inválida'};
      }
      final map = Map<String, dynamic>.from(decoded);
      if (map['success'] == false) {
        return {
          'success': false,
          'message': map['message']?.toString() ?? fallbackMessage,
        };
      }
      if (response.statusCode >= 400 && map['error'] is! Map) {
        return {
          'success': false,
          'message': map['message']?.toString() ?? fallbackMessage,
        };
      }
      if (response.statusCode >= 200 && response.statusCode < 300) {
        final data = unwrapApiContract(decoded, response.statusCode);
        return {'success': true, 'data': data};
      }
      unwrapApiContract(decoded, response.statusCode);
      return {'success': false, 'message': fallbackMessage};
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message, 'error': e.toJson()};
    } catch (e) {
      return {'success': false, 'message': userFriendlyErrorMessage(e)};
    }
  }
}
