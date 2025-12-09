// lib/services/acciones_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

class AccionesService {
  String userId;
  final String? authToken;

  AccionesService({
    required this.userId,
    this.authToken,
  });

  /// Procesa una consulta en lenguaje natural y devuelve acciones
  Future<Map<String, dynamic>> processQuery(String query) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/crud/process-query');
      
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

      // Agregar token de autenticación si existe
      if (authToken != null) {
        headers['Authorization'] = 'Bearer $authToken';
      }

      final response = await http.post(
        uri,
        headers: headers,
        body: json.encode({
          'query': query,
        }),
      ).timeout(
        Duration(seconds: AppConfig.httpTimeoutSeconds),
      );

      final responseData = json.decode(response.body);

      if (response.statusCode == 200) {
        // La respuesta puede venir envuelta en 'data' o directamente
        if (responseData['success'] == true) {
          final data = responseData['data'] ?? responseData;
          return {
            'success': true,
            'data': data,
          };
        } else {
          return {
            'success': false,
            'message': responseData['message'] ?? 'Error en la consulta',
            'data': responseData,
          };
        }
      } else {
        return {
          'success': false,
          'message': responseData['message'] ?? 'Error en la consulta',
          'errors': responseData['errors'] ?? null,
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'Error de conexión: ${e.toString()}',
      };
    }
  }

  /// Obtiene acciones comunes disponibles
  Future<List<Map<String, dynamic>>> getCommonActions() async {
    try {
      // Por ahora retornamos acciones comunes predefinidas
      // En el futuro se puede obtener del backend
      return [
        {
          'title': 'Ver mis consultas',
          'description': 'Revisa tus consultas médicas',
          'icon': 'medical_services',
          'action': 'ver_consultas',
        },
        {
          'title': 'Agendar turno',
          'description': 'Solicita un turno médico',
          'icon': 'event',
          'action': 'agendar_turno',
        },
        {
          'title': 'Ver resultados',
          'description': 'Consulta tus estudios y resultados',
          'icon': 'description',
          'action': 'ver_resultados',
        },
        {
          'title': 'Contactar médico',
          'description': 'Comunícate con tu médico',
          'icon': 'chat',
          'action': 'contactar_medico',
        },
      ];
    } catch (e) {
      return [];
    }
  }
}

