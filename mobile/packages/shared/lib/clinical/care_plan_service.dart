import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../http/api_client.dart';
import '../http/api_contract.dart';

/// Planes de tratamiento activos del paciente (`GET /clinical/care-plans/active`).
class CarePlanService {
  final String? authToken;
  final http.Client? httpClient;

  CarePlanService({this.authToken, this.httpClient});

  Future<ApiClient> _api() async {
    var token = authToken;
    if (token == null || token.isEmpty) {
      final prefs = await SharedPreferences.getInstance();
      token = prefs.getString('auth_token');
    }
    return ApiClient(authToken: token, appClient: 'paciente-flutter', client: httpClient);
  }

  /// Devuelve `{ success, data: List<Map>, message? }`.
  Future<Map<String, dynamic>> fetchActivePlans({
    bool includeActivities = true,
    int? subjectPersonaId,
  }) async {
    try {
      final list = await (await _api()).getList(
        '/clinical/care-plans/active',
        query: {
          'includeActivities': includeActivities ? '1' : '0',
          if (subjectPersonaId != null && subjectPersonaId > 0)
            'subject_persona_id': '$subjectPersonaId',
        },
      );
      return {'success': true, 'data': list};
    } on ApiContractException catch (e) {
      return {'success': false, 'data': <Map<String, dynamic>>[], 'message': e.message};
    } catch (e) {
      return {
        'success': false,
        'data': <Map<String, dynamic>>[],
        'message': e.toString(),
      };
    }
  }

  /// Detalle de un plan (`GET /clinical/care-plans/<id>`). Paciente recibe resumen con indicaciones.
  Future<Map<String, dynamic>> fetchById(int id) async {
    if (id <= 0) {
      return {'success': false, 'data': null, 'message': 'Plan inválido'};
    }
    try {
      final data = await (await _api()).getData('/clinical/care-plans/$id');
      return {'success': true, 'data': data};
    } on ApiContractException catch (e) {
      return {'success': false, 'data': null, 'message': e.message};
    } catch (e) {
      return {'success': false, 'data': null, 'message': e.toString()};
    }
  }
}
