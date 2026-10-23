import 'package:shared_preferences/shared_preferences.dart';

import '../http/api_client.dart';

/// Estado del recorrido pre/post consulta (`GET /api/v1/encounter-journey/estado`).
class EncounterJourneyApi {
  final String? authToken;
  final String appClient;

  EncounterJourneyApi({
    this.authToken,
    this.appClient = 'paciente-flutter',
  });

  Future<String?> _effectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) {
      return authToken;
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  /// Devuelve null sin token o ante cualquier fallo (el recorrido es opcional).
  Future<Map<String, dynamic>?> fetchEstado({
    required int turnoId,
    int? subjectPersonaId,
  }) async {
    final token = await _effectiveToken();
    if (token == null || token.isEmpty) return null;
    try {
      return await ApiClient(authToken: token, appClient: appClient).getData(
        '/encounter-journey/estado',
        query: {
          'turno_id': '$turnoId',
          if (subjectPersonaId != null && subjectPersonaId > 0)
            'subject_persona_id': '$subjectPersonaId',
        },
      );
    } catch (_) {
      return null;
    }
  }
}