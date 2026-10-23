import 'package:shared/shared.dart';

/// Bandeja de alertas in-app (API v1 notificaciones).
class NotificacionesService {
  final String? authToken;

  NotificacionesService({this.authToken});

  ApiClient _api(String traceLabel) => ApiClient(
    authToken: authToken,
    appClient: 'paciente-flutter',
    traceLabel: traceLabel,
  );

  Future<Map<String, dynamic>> listar({
    bool soloNoLeidas = false,
    int limit = 30,
    int offset = 0,
  }) async {
    try {
      final block = await _api('notificaciones/listar').postData(
        '/notificaciones/listar',
        body: {
          'solo_no_leidas': soloNoLeidas ? 1 : 0,
          'limit': limit,
          'offset': offset,
        },
      );
      return {
        'success': true,
        'items': block['items'] ?? [],
        'total': block['total'] ?? 0,
        'no_leidas': block['no_leidas'] ?? 0,
      };
    } catch (e) {
      return {'success': false, 'message': e.toString(), 'items': [], 'no_leidas': 0};
    }
  }

  Future<bool> marcarLeida({int? id}) async {
    try {
      await _api('notificaciones/marcar-leida').postData(
        '/notificaciones/marcar-leida',
        body: id != null && id > 0 ? {'id': id} : {},
      );
      return true;
    } catch (_) {
      return false;
    }
  }
}