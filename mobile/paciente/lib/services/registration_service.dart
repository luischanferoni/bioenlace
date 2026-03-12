import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';
import 'package:didit_sdk/sdk_flutter.dart';

class RegistrationService {
  /// Inicia el flujo de verificación de identidad con Didit y, si es aprobado,
  /// llama al endpoint de registro unificado en el backend.
  Future<Map<String, dynamic>> submitRegistration() async {
    try {
      // 1) Iniciar verificación con Didit usando el workflow de KYC para pacientes
      final result = await DiditSdk.startVerificationWithWorkflow(
        AppConfig.diditPacienteKycWorkflowId,
        config: const DiditConfig(
          languageCode: 'es',
          loggingEnabled: true,
        ),
      );

      switch (result) {
        case VerificationCompleted(:final session):
          // Estado de la sesión: approved | pending | declined
          if (session.status == VerificationStatus.approved) {
            // 2) Llamar al endpoint de registro unificado del backend
            final registroUri = Uri.parse('${AppConfig.apiUrl}/registro/registrar');
            final registroResponse = await http
                .post(
                  registroUri,
                  headers: {'Content-Type': 'application/json'},
                  body: json.encode({
                    'tipo': 'paciente',
                    'verification_id': session.sessionId,
                  }),
                )
                .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

            final registroData = json.decode(registroResponse.body);

            if (registroResponse.statusCode >= 200 && registroResponse.statusCode < 300 && registroData['success'] == true) {
              return {
                'success': true,
                'data': {
                  'didit_session': {
                    'session_id': session.sessionId,
                    'status': session.status.name,
                  },
                  'registro': registroData,
                },
              };
            } else {
              return {
                'success': false,
                'message': registroData['message'] ?? 'Error en el registro en el backend',
                'errors': registroData['errors'],
              };
            }
          } else {
            return {
              'success': false,
              'message': 'La verificación de identidad está en estado: ${session.status.name}.',
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
