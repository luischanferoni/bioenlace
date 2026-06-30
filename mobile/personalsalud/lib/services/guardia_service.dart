// lib/services/guardia_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

class GuardiaItem {
  final int id;
  final int idPersona;
  final String nombreCompleto;
  final String? documento;
  final String? fecha;
  final String? hora;
  final String? estado;

  GuardiaItem({
    required this.id,
    required this.idPersona,
    required this.nombreCompleto,
    this.documento,
    this.fecha,
    this.hora,
    this.estado,
  });

  factory GuardiaItem.fromJson(Map<String, dynamic> json) {
    return GuardiaItem(
      id: (json['id'] as int?) ?? 0,
      idPersona: (json['id_persona'] as int?) ?? 0,
      nombreCompleto: (json['nombre_completo'] as String?) ?? 'Sin nombre',
      documento: json['documento'] as String?,
      fecha: json['fecha'] as String?,
      hora: json['hora'] as String?,
      estado: json['estado'] as String?,
    );
  }
}

class GuardiaService {
  String? authToken;
  String? userId;

  GuardiaService({this.authToken, this.userId});

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (authToken != null) {
      headers['Authorization'] = 'Bearer $authToken';
    }
    return headers;
  }

  Future<List<GuardiaItem>> getGuardia({int? efectorId}) async {
    try {
      final queryParams = <String, String>{};
      if (efectorId != null) queryParams['efector_id'] = efectorId.toString();
      if (userId != null && (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        queryParams['user_id'] = userId!;
      }
      final uri = Uri.parse('${AppConfig.apiUrl}/listado/guardia').replace(
        queryParameters: queryParams.isNotEmpty ? queryParams : null,
      );
      final response = await http.get(uri, headers: _headers);
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final list = data['data']['items'] as List<dynamic>? ?? [];
          return list.map((e) => GuardiaItem.fromJson(e as Map<String, dynamic>)).toList();
        }
      }
      return [];
    } catch (e) {
      print('Error fetching guardia: $e');
      return [];
    }
  }
}
