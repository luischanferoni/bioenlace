import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../auth/paciente_post_login.dart';
import '../services/registration_service.dart';

/// Registro del paciente: dispara el flujo de verificación de identidad.
class SignupScreen extends StatefulWidget {
  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isSubmitting = false;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSubmitting = true);

    try {
      final registrationService = RegistrationService();
      final result = await registrationService.submitRegistration(
        onVerificationUiStarting: () {
          if (mounted) setState(() => _isSubmitting = false);
        },
      );

      if (result['success'] == true) {
        final data = result['data'];
        final registroWrapper = data['registro'] as Map<String, dynamic>? ?? {};
        final registroInner =
            registroWrapper['data'] as Map<String, dynamic>? ?? registroWrapper;
        final persona = registroInner['persona'] as Map<String, dynamic>? ?? {};
        final userId =
            'paciente_${persona['id_persona'] ?? DateTime.now().millisecondsSinceEpoch}';
        final fullName =
            '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();
        final token = registroInner['token']?.toString();
        final ctxJson = registroInner['paciente_contexto'];

        debugPrint(
          'SignupScreen: registro OK userId=$userId — enrolando huella…',
        );

        // Independiente de mounted: Didit puede haber desmontado esta ruta.
        final ok = await enterPacienteAuthenticatedApp(
          userId: userId,
          userName: fullName.isNotEmpty ? fullName : 'Usuario',
          authToken: token,
          pacienteContexto:
              ctxJson is Map<String, dynamic> ? ctxJson : null,
          fallbackContext: mounted ? context : null,
          forceBiometricEnrollment: true,
          waitForNativeReturn: true,
        );

        if (!ok) {
          debugPrint('SignupScreen: no se completó enrolamiento/navegación');
          if (mounted) {
            _snack(
              'Registramos tu cuenta, pero falta activar la huella del dispositivo.',
              UiIntent.warning,
            );
          }
          return;
        }

        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(
          'dni_detected',
          persona['documento']?.toString() ?? '',
        );
        await prefs.setString('name_detected', fullName);
      } else {
        if (!mounted) return;
        final errors = result['errors'];
        var message = result['message']?.toString() ?? 'Error en el registro';
        if (errors is Map && errors.isNotEmpty) {
          message = '$message\n${errors.toString()}';
        }
        debugPrint('SignupScreen: registro falló — $message');
        _snack(message, UiIntent.danger);
      }
    } catch (e) {
      debugPrint('SignupScreen: error $e');
      if (!mounted) return;
      _snack('Error inesperado: ${e.toString()}', UiIntent.danger);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _snack(String msg, UiIntent intent) {
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg, style: TextStyle(color: palette.onBase)),
        backgroundColor: palette.base,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(),
      body: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.xl,
          vertical: BioSpacing.lg,
        ),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
              Text(
                'Completá tu registro',
                style: BioTypography.h2,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.sm),
              Text(
                'Subí las fotos requeridas para verificar tu identidad.',
                style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.xxl),
              BioButton.primary(
                label: _isSubmitting ? 'Registrando…' : 'Completar Registro',
                size: BioButtonSize.lg,
                fullWidth: true,
                loading: _isSubmitting,
                onPressed: _isSubmitting ? null : _submit,
              ),
              BioSpacing.gapH(BioSpacing.lg),
              const PrivacyPolicyLink(),
            ],
          ),
        ),
      ),
    );
  }
}
