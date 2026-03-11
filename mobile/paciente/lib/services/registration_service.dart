import 'dart:io';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';
import '../models/user_registration.dart';

class RegistrationService {
  /// Envía las fotos del DNI y selfie al backend para registro y verificación facial
  Future<Map<String, dynamic>> submitRegistration(File dniImage, File selfieImage) async {
    try {
      // 1) Primero usamos el endpoint existente /signup para:
      //    - extraer datos del DNI
      //    - verificar coincidencia facial
      final signupUri = Uri.parse('${AppConfig.apiUrl}/signup');
      final request = http.MultipartRequest('POST', signupUri)
        ..files.add(await http.MultipartFile.fromPath('dni_photo', dniImage.path))
        ..files.add(await http.MultipartFile.fromPath('selfie_photo', selfieImage.path));

      final streamedResponse = await request.send().timeout(
        Duration(seconds: AppConfig.httpTimeoutSeconds),
      );
      
      final response = await http.Response.fromStream(streamedResponse);
      final responseData = json.decode(response.body);

      if (response.statusCode == 200 && responseData['success'] == true) {
        // 2) Con los datos del DNI extraídos por /signup, llamamos al nuevo
        //    endpoint de registro unificado de la API (/registro/registrar),
        //    para crear/actualizar la persona y sincronizar con MPI/REFEPS.
        try {
          final dniData = responseData['dni_data'] ?? {};
          final dni = dniData['dni'];
          final nombre = dniData['nombre'];
          final apellido = dniData['apellido'];

          if (dni != null && nombre != null && apellido != null) {
            final registroUri = Uri.parse('${AppConfig.apiUrl}/registro/registrar');
            final registroResponse = await http
                .post(
                  registroUri,
                  headers: {'Content-Type': 'application/json'},
                  body: json.encode({
                    'tipo': 'paciente',
                    'dni': dni,
                    'nombre': nombre,
                    'apellido': apellido,
                  }),
                )
                .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

            final registroData = json.decode(registroResponse.body);
            responseData['registro'] = registroData;
          } else {
            responseData['registro'] = {
              'success': false,
              'message': 'No se pudieron obtener todos los datos del DNI para registrar a la persona',
            };
          }
        } catch (e) {
          // No rompemos el flujo original si el nuevo registro falla,
          // pero devolvemos el error para poder depurarlo.
          responseData['registro_error'] = e.toString();
        }

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
