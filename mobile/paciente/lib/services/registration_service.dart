import 'dart:async';

import 'package:didit_sdk/sdk_flutter.dart';
import 'package:flutter/services.dart';
import 'package:shared/platform/didit_platform.dart';
import 'package:shared/shared.dart';

import '../utils/app_texts.dart';

const _diditVerificationTimeout = Duration(minutes: 10);

const String _diditNotConfiguredMessage = AppTexts.diditNotConfigured;

class RegistrationService {
  /// Inicia el flujo de verificación de identidad con Didit y, si es aprobado,
  /// llama al endpoint de registro unificado en el backend.
  ///
  /// [onVerificationUiStarting] se invoca justo antes de abrir la UI nativa de Didit.
  Future<Map<String, dynamic>> submitRegistration({
    void Function()? onVerificationUiStarting,
  }) async {
    if (!isDiditSupported) {
      return {'success': false, 'message': diditUnsupportedPlatformMessage};
    }

    final workflowId = await DiditConfigResolver.resolvePacienteKycWorkflowId();
    if (workflowId == null) {
      return {'success': false, 'message': _diditNotConfiguredMessage};
    }

    try {
      onVerificationUiStarting?.call();
      final result = await DiditSdk.startVerificationWithWorkflow(
        workflowId,
        config: const DiditConfig(languageCode: 'es', loggingEnabled: true),
      ).timeout(_diditVerificationTimeout);

      switch (result) {
        case VerificationCompleted(:final session):
          if (session.status == VerificationStatus.approved) {
            final registroData = await ApiClient(
              appClient: 'bioenlace-paciente',
              traceLabel: 'registro/registrar',
            ).postData(
              '/registro/registrar',
              body: {'tipo': 'paciente', 'verification_id': session.sessionId},
            );
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
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message};
    } on MissingPluginException {
      return {'success': false, 'message': diditMissingPluginMessage};
    } on TimeoutException {
      return {
        'success': false,
        'message': AppTexts.diditTimeout,
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Error de conexión: ${e.toString()}',
      };
    }
  }
}
