import 'package:http/http.dart' as http;
import '../auth/auth_token_store.dart';
import '../http/api_client.dart';
import '../http/api_contract.dart';

/// GET /clinical/care-plans/recordatorios-como-paciente
class CarePlanReminderApi {
  final String? authToken;
  final http.Client? httpClient;

  CarePlanReminderApi({this.authToken, this.httpClient});

  static const _preferencesPath =
      '/clinical/care-plans/preferencias-recordatorios-como-paciente';

  Future<ApiClient> _api() async {
    var token = authToken;
    if (token == null || token.isEmpty) {
      token = await AuthTokenStore.instance.read();
    }
    return ApiClient(authToken: token, appClient: 'paciente-flutter', client: httpClient);
  }

  Future<Map<String, dynamic>> fetchSchedule({int? carePlanId}) async {
    try {
      final data = await (await _api()).getData(
        '/clinical/care-plans/recordatorios-como-paciente',
        query: {
          if (carePlanId != null && carePlanId > 0) 'care_plan_id': '$carePlanId',
        },
      );
      return {'success': true, 'data': data, 'message': data['message']};
    } on ApiContractException catch (e) {
      return {'success': false, 'data': null, 'message': e.message};
    } catch (e) {
      return {'success': false, 'data': null, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> fetchPreferences() async {
    try {
      final data = await (await _api()).getData(_preferencesPath);
      return {'success': true, 'data': data};
    } on ApiContractException catch (e) {
      return {'success': false, 'data': null, 'message': e.message};
    } catch (e) {
      return {'success': false, 'data': null, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> savePreferences(Map<String, dynamic> body) async {
    try {
      final data = await (await _api()).putData(_preferencesPath, body: body);
      return {'success': true, 'data': data, 'message': data['message']};
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
