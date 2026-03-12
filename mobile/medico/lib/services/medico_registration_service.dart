import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';
import 'package:didit_sdk/sdk_flutter.dart';

/// Servicio para registrar/verificar la identidad del médico usando Didit (KYC completo)
/// y luego registrar/actualizar la Persona en el backend (incluyendo validación REFEPS).
class MedicoRegistrationService {
  Future<Map<String, dynamic>> registerMedico() async {
    try {
      // 1) Iniciar verificación KYC de médico con Didit
      final result = await DiditSdk.startVerificationWithWorkflow(
        AppConfig.diditMedicoKycWorkflowId,
        config: const DiditConfig(
          languageCode: 'es',
          loggingEnabled: true,
        ),
      );

      switch (result) {
        case VerificationCompleted(:final session):
          // 2) Llamar al backend para registrar/actualizar al médico
          final uri = Uri.parse('${AppConfig.apiUrl}/registro/registrar');
          final response = await http
              .post(
                uri,
                headers: {'Content-Type': 'application/json'},
                body: jsonEncode({
                  'tipo': 'medico',
                  'verification_id': session.sessionId,
                }),
              )
              .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

          final data = jsonDecode(response.body);

          if (response.statusCode >= 200 &&
              response.statusCode < 300 &&
              data['success'] == true) {
            return {
              'success': true,
              'data': {
                'didit_session': {
                  'session_id': session.sessionId,
                  'status': session.status.name,
                },
                'registro': data,
              },
            };
          } else {
            return {
              'success': false,
              'message': data['message'] ??
                  'Error en el registro del médico en el backend',
              'errors': data['errors'],
            };
          }

        case VerificationCancelled():
          return {
            'success': false,
            'message': 'Verificación cancelada por el usuario.',
          };

        case VerificationFailed(:final error):
          return {
            'success': false,
            'message': 'Error en Didit: ${error.message}',
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

