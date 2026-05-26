// lib/services/internacion_api.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// API v1 clinical/internacion (mapa de camas e indicadores).
class InternacionApi {
  final String? authToken;
  final String? userId;

  InternacionApi({this.authToken, this.userId});

  Map<String, String> _headers() {
    final h = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (authToken != null && authToken!.isNotEmpty) {
      h['Authorization'] = 'Bearer $authToken';
    }
    if (userId != null && userId!.isNotEmpty) {
      h['X-User-Id'] = userId!;
    }
    return h;
  }

  Future<Map<String, dynamic>> mapaCamas({int? idPiso, int? idSala}) async {
    final q = <String, String>{};
    if (idPiso != null && idPiso > 0) q['id_piso'] = '$idPiso';
    if (idSala != null && idSala > 0) q['id_sala'] = '$idSala';
    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/internacion/mapa-camas')
        .replace(queryParameters: q.isEmpty ? null : q);
    final res = await http.get(uri, headers: _headers());
    final json = jsonDecode(res.body) as Map<String, dynamic>;
    if (res.statusCode >= 400 || json['success'] != true) {
      throw Exception(json['message']?.toString() ?? 'Error al cargar mapa de camas');
    }
    return (json['data'] as Map<String, dynamic>?) ?? json;
  }

  Future<Map<String, dynamic>> indicadoresResumen() async {
    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/internacion/indicadores-resumen');
    final res = await http.get(uri, headers: _headers());
    final json = jsonDecode(res.body) as Map<String, dynamic>;
    if (res.statusCode >= 400 || json['success'] != true) {
      throw Exception(json['message']?.toString() ?? 'Error al cargar indicadores');
    }
    return (json['data'] as Map<String, dynamic>?) ?? {};
  }

  Future<void> marcarEstadoCama(int camaId, String estadoMapa, {String? motivo}) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/internacion/cama/$camaId/marcar-estado',
    );
    final body = <String, dynamic>{'estado_mapa': estadoMapa};
    if (motivo != null && motivo.isNotEmpty) body['motivo'] = motivo;
    final res = await http.post(uri, headers: _headers(), body: jsonEncode(body));
    final json = jsonDecode(res.body) as Map<String, dynamic>;
    if (res.statusCode >= 400 || json['success'] != true) {
      throw Exception(json['message']?.toString() ?? 'No se pudo actualizar la cama');
    }
  }
}
