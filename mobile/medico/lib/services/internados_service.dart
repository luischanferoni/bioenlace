// lib/services/internados_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

class InternadoItem {
  final int id;
  final int idPersona;
  final String nombreCompleto;
  final String? documento;
  final String? fechaInicio;
  final String? horaInicio;
  final String? cama;
  final String? sala;
  final String? piso;

  InternadoItem({
    required this.id,
    required this.idPersona,
    required this.nombreCompleto,
    this.documento,
    this.fechaInicio,
    this.horaInicio,
    this.cama,
    this.sala,
    this.piso,
  });

  factory InternadoItem.fromJson(Map<String, dynamic> json) {
    return InternadoItem(
      id: (json['id'] as int?) ?? 0,
      idPersona: (json['id_persona'] as int?) ?? 0,
      nombreCompleto: (json['nombre_completo'] as String?) ?? 'Sin nombre',
      documento: json['documento'] as String?,
      fechaInicio: json['fecha_inicio'] as String?,
      horaInicio: json['hora_inicio'] as String?,
      cama: json['cama']?.toString(),
      sala: json['sala']?.toString(),
      piso: json['piso']?.toString(),
    );
  }
}

class InternadosService {
  String? authToken;
  String? userId;

  InternadosService({this.authToken, this.userId});

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

  Future<List<InternadoItem>> getInternados({int? efectorId}) async {
    try {
      final queryParams = <String, String>{};
      if (efectorId != null) queryParams['efector_id'] = efectorId.toString();
      if (userId != null && (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        queryParams['user_id'] = userId!;
      }
      final uri = Uri.parse('${AppConfig.apiUrl}/listado/internacion').replace(
        queryParameters: queryParams.isNotEmpty ? queryParams : null,
      );
      final response = await http.get(uri, headers: _headers);
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final list = data['data']['items'] as List<dynamic>? ?? [];
          return list.map((e) => InternadoItem.fromJson(e as Map<String, dynamic>)).toList();
        }
      }
      return [];
    } catch (e) {
      print('Error fetching internados: $e');
      return [];
    }
  }
}
