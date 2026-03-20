// lib/services/consulta_guardar_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Guardado mínimo alineado con `POST /api/v1/consulta/guardar` (form body).
class ConsultaGuardarService {
  String? authToken;

  ConsultaGuardarService({this.authToken});

  Map<String, String> get _headers {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    };
    if (authToken != null && authToken!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $authToken';
    }
    return headers;
  }

  /// [parent] ej. TURNO, CIRUGIA, GUARDIA (mismas claves que web).
  Future<Map<String, dynamic>> guardar({
    required int idPersona,
    required String parent,
    required int parentId,
    required String texto,
    int? idConfiguracion,
  }) async {
    final body = <String, String>{
      'id_persona': '$idPersona',
      'parent': parent,
      'parent_id': '$parentId',
      'texto_original': texto,
      'texto_procesado': texto,
      'datosExtraidos': json.encode({}),
    };
    if (idConfiguracion != null) {
      body['id_configuracion'] = '$idConfiguracion';
    }

    final uri = Uri.parse('${AppConfig.apiUrl}/consulta/guardar');
    final response = await http.post(uri, headers: _headers, body: body);

    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Respuesta inválida');
    }
    if (response.statusCode >= 400 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Error al guardar consulta');
    }
    return decoded;
  }
}
