import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared/http/bioenlace_http_trace.dart';
import 'package:shared/shared.dart';

/// Bandeja de alertas in-app (API v1 notificaciones).
class NotificacionesService {
  final String? authToken;

  NotificacionesService({this.authToken});

  Future<Map<String, dynamic>> listar({
    bool soloNoLeidas = false,
    int limit = 30,
    int offset = 0,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/notificaciones/listar');
      final headers = AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: 'paciente-flutter',
      );
      final body = json.encode({
        'solo_no_leidas': soloNoLeidas ? 1 : 0,
        'limit': limit,
        'offset': offset,
      });
      final response = await http
          .post(uri, headers: headers, body: body)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      BioenlaceHttpTrace.logResponse('notificaciones/listar', response);
      final data = json.decode(response.body) as Map<String, dynamic>;
      if (response.statusCode >= 200 &&
          response.statusCode < 300 &&
          data['success'] == true) {
        final block = data['data'];
        return {
          'success': true,
          'items': block is Map ? block['items'] : [],
          'total': block is Map ? block['total'] : 0,
          'no_leidas': block is Map ? block['no_leidas'] : 0,
        };
      }
      return {
        'success': false,
        'message': data['message'] ?? 'Error al cargar alertas',
        'items': [],
        'no_leidas': 0,
      };
    } catch (e) {
      return {'success': false, 'message': e.toString(), 'items': [], 'no_leidas': 0};
    }
  }

  Future<bool> marcarLeida({int? id}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/notificaciones/marcar-leida');
      final headers = AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: 'paciente-flutter',
      );
      final body = json.encode(id != null && id > 0 ? {'id': id} : {});
      final response = await http
          .post(uri, headers: headers, body: body)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      BioenlaceHttpTrace.logResponse('notificaciones/marcar-leida', response);
      final data = json.decode(response.body) as Map<String, dynamic>;
      return response.statusCode >= 200 &&
          response.statusCode < 300 &&
          data['success'] == true;
    } catch (_) {
      return false;
    }
  }
}
