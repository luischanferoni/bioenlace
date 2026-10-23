import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Servicio para obtener "Mis turnos" del paciente y el encounter asociado.
class TurnosService {
  final String? authToken;

  TurnosService({this.authToken});

  /// Usa el token inyectado o el guardado en SharedPreferences (evita 401 en turnos/como-paciente).
  Future<ApiClient> _api() async {
    var token = authToken;
    if (token == null || token.isEmpty) {
      final prefs = await SharedPreferences.getInstance();
      token = prefs.getString('auth_token');
    }
    return ApiClient(authToken: token, appClient: 'paciente-flutter');
  }

  String _message(Object e) =>
      e is ApiContractException ? e.message : e.toString();

  /// [alcance]: `pendientes` (inicio ≥ ahora, activos) o `pasados` (inicio < ahora, historial).
  Future<Map<String, dynamic>> getMisTurnos({
    String? fechaDesde,
    String? fechaHasta,
    String? alcance,
    int? limit,
    int? offset,
    int? subjectPersonaId,
  }) async {
    try {
      final block = await (await _api()).postData(
        '/turnos/listar-como-paciente',
        body: {
          if (fechaDesde != null) 'fecha_desde': fechaDesde,
          if (fechaHasta != null) 'fecha_hasta': fechaHasta,
          if (alcance != null) 'alcance': alcance,
          if (limit != null) 'limit': limit,
          if (offset != null) 'offset': offset,
          if (subjectPersonaId != null && subjectPersonaId > 0)
            'subject_persona_id': subjectPersonaId,
        },
      );
      final turnos = block['turnos'];
      final totalRaw = block['total'];
      return {
        'success': true,
        'data': block,
        'turnos': turnos is List<dynamic> ? turnos : [],
        'total': totalRaw is int ? totalRaw : int.tryParse('$totalRaw') ?? 0,
      };
    } catch (e) {
      return {
        'success': false,
        'message': _message(e),
        'turnos': [],
        'total': 0,
      };
    }
  }

  /// Confirma asistencia a un turno pendiente.
  Future<Map<String, dynamic>> confirmarAsistencia({
    required int idTurno,
    String? token,
    int? subjectPersonaId,
  }) => _post('/turnos/confirmar-asistencia-como-paciente', {
    'id': idTurno,
    if (token != null && token.isNotEmpty) 'token': token,
    if (subjectPersonaId != null && subjectPersonaId > 0)
      'subject_persona_id': subjectPersonaId,
  });

  /// Acepta adelantar el turno propio/representado al slot liberado.
  Future<Map<String, dynamic>> acceptAdvanceOffer({
    required String offerToken,
    int? subjectPersonaId,
  }) => _post('/turnos/adelantar-oferta-como-paciente', {
    'offer_token': offerToken,
    if (subjectPersonaId != null && subjectPersonaId > 0)
      'subject_persona_id': subjectPersonaId,
  });

  Future<Map<String, dynamic>> _post(
    String path,
    Map<String, dynamic> body,
  ) async {
    try {
      final data = await (await _api()).postData(path, body: body);
      return {'success': true, 'data': data};
    } catch (e) {
      return {'success': false, 'message': _message(e)};
    }
  }
}