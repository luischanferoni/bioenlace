import 'package:http/http.dart' as http;
import '../auth/auth_token_store.dart';
import '../http/api_client.dart';

/// API JSON de resúmenes de atención publicados (paciente).
class EncounterPatientSummaryApi {
  final String? authToken;
  final http.Client? httpClient;

  EncounterPatientSummaryApi({this.authToken, this.httpClient});

  Future<ApiClient> _api() async {
    var token = authToken;
    if (token == null || token.isEmpty) {
      token = await AuthTokenStore.instance.read();
    }
    return ApiClient(
      authToken: token,
      appClient: 'paciente-flutter',
      client: httpClient,
    );
  }

  Future<Map<String, dynamic>> list({
    int limit = 20,
    int offset = 0,
    int? subjectPersonaId,
  }) async {
    return (await _api()).getData(
      '/clinical/encounter/listar-atenciones-como-paciente',
      query: {
        'limit': '$limit',
        'offset': '$offset',
        if (subjectPersonaId != null && subjectPersonaId > 0)
          'subject_persona_id': '$subjectPersonaId',
      },
    );
  }

  Future<Map<String, dynamic>> fetchDetail(int encounterId) async {
    return (await _api()).getData(
      '/clinical/encounter/ver-resumen-como-paciente',
      query: {'encounter_id': '$encounterId'},
    );
  }

  Future<Map<String, dynamic>> fetchLatest() async {
    return (await _api()).getData('/clinical/encounter/ultima-atencion-como-paciente');
  }
}