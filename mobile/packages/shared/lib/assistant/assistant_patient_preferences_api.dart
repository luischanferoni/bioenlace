import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../http/api_client.dart';
import '../http/api_contract.dart';

/// GET/PUT /asistente/preferencias-como-paciente
class AssistantPatientPreferencesApi {
  final String? authToken;
  final http.Client? httpClient;

  AssistantPatientPreferencesApi({this.authToken, this.httpClient});

  static const _path = '/asistente/preferencias-como-paciente';

  Future<ApiClient> _api() async {
    var token = authToken;
    if (token == null || token.isEmpty) {
      final prefs = await SharedPreferences.getInstance();
      token = prefs.getString('auth_token');
    }
    return ApiClient(
      authToken: token,
      appClient: 'paciente-flutter',
      client: httpClient,
    );
  }

  String _message(Object e) => e is ApiContractException ? e.message : e.toString();

  Future<Map<String, dynamic>> fetch() async {
    try {
      final data = await (await _api()).getData(_path);
      return {'success': true, 'data': data};
    } catch (e) {
      return {'success': false, 'data': null, 'message': _message(e)};
    }
  }

  Future<Map<String, dynamic>> save({
    required bool usaResumenHcEnAsistente,
  }) async {
    try {
      final data = await (await _api()).putData(
        _path,
        body: {'usa_resumen_hc_en_asistente': usaResumenHcEnAsistente},
      );
      return {
        'success': true,
        'data': data,
        if (data['message'] != null) 'message': data['message'],
      };
    } catch (e) {
      return {'success': false, 'message': _message(e)};
    }
  }
}