import 'dart:io';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';
import '../models/user_registration.dart';

class RegistrationService {
  /// Envía las fotos del DNI y selfie al backend para registro y verificación facial
  Future<Map<String, dynamic>> submitRegistration(File dniImage, File selfieImage) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/signup');
      
      final request = http.MultipartRequest('POST', uri)
        ..files.add(await http.MultipartFile.fromPath('dni_photo', dniImage.path))
        ..files.add(await http.MultipartFile.fromPath('selfie_photo', selfieImage.path));

      final streamedResponse = await request.send().timeout(
        Duration(seconds: AppConfig.httpTimeoutSeconds),
      );
      
      final response = await http.Response.fromStream(streamedResponse);
      final responseData = json.decode(response.body);

      if (response.statusCode == 200 && responseData['success'] == true) {
        return {
          'success': true,
          'data': responseData,
        };
      } else {
        return {
          'success': false,
          'message': responseData['message'] ?? 'Error en el registro',
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
}
