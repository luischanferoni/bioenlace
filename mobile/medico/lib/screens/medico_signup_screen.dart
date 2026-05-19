import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/medico_registration_service.dart';

/// Pantalla de registro/verificación KYC para médicos usando Didit + backend (REPEFS/MPI).
class MedicoSignupScreen extends StatefulWidget {
  const MedicoSignupScreen({super.key});

  @override
  State<MedicoSignupScreen> createState() => _MedicoSignupScreenState();
}

class _MedicoSignupScreenState extends State<MedicoSignupScreen> {
  final MedicoRegistrationService _registrationService =
      MedicoRegistrationService();
  bool _isSubmitting = false;

  Future<void> _startRegistration() async {
    if (_isSubmitting) return;

    setState(() {
      _isSubmitting = true;
    });

    try {
      final result = await _registrationService.registerMedico();

      if (!mounted) return;

      if (result['success'] == true) {
        final registro =
            (result['data']?['registro']?['data']?['persona']) ?? {};
        final nombre =
            '${registro['nombre'] ?? ''} ${registro['apellido'] ?? ''}'.trim();

        _snack(
          nombre.isNotEmpty
              ? 'Verificación completada para $nombre'
              : 'Verificación de médico completada correctamente',
          UiIntent.success,
          seconds: 4,
        );

        Navigator.pop(context);
      } else {
        final message =
            result['message'] ?? 'Error en el registro/verificación del médico';
        _snack(message.toString(), UiIntent.danger, seconds: 5);
      }
    } catch (e) {
      if (!mounted) return;
      _snack('Error inesperado: ${e.toString()}', UiIntent.danger, seconds: 5);
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  void _snack(String message, UiIntent intent, {int seconds = 4}) {
    if (!mounted) return;
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: palette.base,
        duration: Duration(seconds: seconds),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary);

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Registro de médico'),
      body: SafeArea(
        child: Padding(
          padding: BioSpacing.pageAll,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              BioSpacing.gapH(BioSpacing.md),
              Text('Verificación de identidad', style: BioTypography.h2),
              BioSpacing.gapH(BioSpacing.sm),
              Text(
                'Vamos a verificar tu identidad como profesional de la salud '
                'usando Didit y REFEPS. Este proceso utiliza tu documento, '
                'selfie y validaciones externas para garantizar que sos un '
                'médico habilitado.',
                style: BioTypography.body,
              ),
              BioSpacing.gapH(BioSpacing.xl),
              Center(
                child: Container(
                  width: 120,
                  height: 120,
                  decoration: BoxDecoration(
                    color: primary.softBg,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: primary.border,
                      width: BorderWidth.thin,
                    ),
                  ),
                  child: Icon(
                    Icons.medical_information,
                    size: 64,
                    color: primary.base,
                  ),
                ),
              ),
              BioSpacing.gapH(BioSpacing.xl),
              Text('Requisitos', style: BioTypography.h3),
              BioSpacing.gapH(BioSpacing.sm),
              _buildBullet(
                'Tu documento de identidad vigente (DNI, pasaporte, etc.).',
              ),
              _buildBullet(
                'Un lugar bien iluminado para tomar la selfie y prueba de vida.',
              ),
              _buildBullet(
                'Al finalizar, validamos tu matrícula contra REFEPS/SISA.',
              ),
              const Spacer(),
              BioButton.primary(
                label: _isSubmitting
                    ? 'Iniciando verificación…'
                    : 'Iniciar verificación de médico',
                icon: Icons.verified_user,
                size: BioButtonSize.lg,
                fullWidth: true,
                loading: _isSubmitting,
                onPressed: _isSubmitting ? null : _startRegistration,
              ),
              BioSpacing.gapH(BioSpacing.md),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBullet(String text) {
    final tokens = context.bio;
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('•  ', style: BioTypography.body.copyWith(color: tokens.textTitle)),
          Expanded(child: Text(text, style: BioTypography.body)),
        ],
      ),
    );
  }
}
