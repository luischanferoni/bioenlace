import 'dart:async';
import 'dart:convert';

import 'package:didit_sdk/sdk_flutter.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:shared/config/didit_config_resolver.dart';
import 'package:shared/http/bioenlace_http_trace.dart';
import 'package:shared/platform/didit_platform.dart';
import 'package:shared/shared.dart';

const _diditVerificationTimeout = Duration(minutes: 10);

const String _diditNotConfiguredMessage =
    'El registro con verificación de identidad no está configurado en el servidor. '
    'Contactá al soporte de Bioenlace.';

class RegistrationService {
  /// Inicia el flujo de verificación de identidad con Didit y, si es aprobado,
  /// llama al endpoint de registro unificado en el backend.
  ///
  /// [onVerificationUiStarting] se invoca justo antes de abrir la UI nativa de Didit.
  Future<Map<String, dynamic>> submitRegistration({
    void Function()? onVerificationUiStarting,
  }) async {
    if (!isDiditSupported) {
      return {
        'success': false,
        'message': diditUnsupportedPlatformMessage,
      };
    }

    final workflowId = await DiditConfigResolver.resolvePacienteKycWorkflowId();
    if (workflowId == null) {
      return {
        'success': false,
        'message': _diditNotConfiguredMessage,
      };
    }

    try {
      onVerificationUiStarting?.call();
      final result = await DiditSdk.startVerificationWithWorkflow(
        workflowId,
        config: const DiditConfig(
          languageCode: 'es',
          loggingEnabled: true,
        ),
      ).timeout(_diditVerificationTimeout);

      switch (result) {
        case VerificationCompleted(:final session):
          if (session.status == VerificationStatus.approved) {
            final registroUri =
                Uri.parse('${AppConfig.apiUrl}/registro/registrar');
            final registroResponse = await http
                .post(
                  registroUri,
                  headers:
                      AppConfig.jsonHeaders(appClient: 'bioenlace-paciente'),
                  body: json.encode({
                    'tipo': 'paciente',
                    'verification_id': session.sessionId,
                  }),
                )
                .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

            BioenlaceHttpTrace.logResponse('registro/registrar', registroResponse);

            final registroData = json.decode(registroResponse.body);

            if (registroResponse.statusCode >= 200 &&
                registroResponse.statusCode < 300 &&
                registroData['success'] == true) {
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
            }
            return {
              'success': false,
              'message':
                  registroData['message'] ?? 'Error en el registro en el backend',
              'errors': registroData['errors'],
              'status_code': registroResponse.statusCode,
            };
          }
          return {
            'success': false,
            'message':
                'La verificación de identidad está en estado: ${session.status.name}.',
          };
        case VerificationCancelled():
          return {
            'success': false,
            'message': 'Verificación cancelada por el usuario.',
          };
        case VerificationFailed(:final error):
          return {
            'success': false,
            'message':
                'No se pudo completar la verificación de identidad: ${error.message}',
          };
      }
    } on MissingPluginException {
      return {
        'success': false,
        'message': diditMissingPluginMessage,
      };
    } on TimeoutException {
      return {
        'success': false,
        'message':
            'La verificación de identidad tardó demasiado. Intentá de nuevo.',
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Error de conexión: ${e.toString()}',
      };
    }
  }
}
