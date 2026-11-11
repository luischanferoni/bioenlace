import 'package:shared/shared.dart';

/// Cuestionario pre-turno de motivos (bot con opciones fijas y cierre libre).
/// Endpoints: encounter-reasons/cuestionario y responder-cuestionario.
class EncounterQuestionnaireService {
  final String? authToken;

  EncounterQuestionnaireService({this.authToken});

  ApiClient get _api =>
      ApiClient(authToken: authToken, appClient: 'paciente-flutter');

  Future<Map<String, dynamic>> fetchState(int encounterId) async {
    try {
      final payload = await _api.getData(
        '/encounter-reasons/cuestionario',
        query: {'encounter_id': encounterId.toString()},
      );
      return {'success': true, 'data': payload};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> answer(
    int encounterId, {
    required String questionId,
    String? optionId,
    String? texto,
  }) async {
    try {
      final payload = await _api.postData(
        '/encounter-reasons/responder-cuestionario',
        body: {
          'encounter_id': encounterId,
          'question_id': questionId,
          if (optionId != null && optionId.isNotEmpty) 'option_id': optionId,
          if (texto != null && texto.trim().isNotEmpty) 'texto': texto.trim(),
        },
      );
      return {'success': true, 'data': payload};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
