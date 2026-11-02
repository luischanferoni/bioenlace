import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../auth/personalsalud_post_login.dart';
import 'commercial_signup_screen.dart';
import '../services/staff_signup_service.dart';

enum _SignupPath { professional, privateConsultorio, clinic }

/// Alta de cuenta profesional personal. Las altas comerciales requieren checkout.
class StaffSignupScreen extends StatefulWidget {
  const StaffSignupScreen({super.key});

  @override
  State<StaffSignupScreen> createState() => _StaffSignupScreenState();
}

class _StaffSignupScreenState extends State<StaffSignupScreen> {
  bool _submitting = false;
  bool _accountCreated = false;
  _SignupPath _selectedPath = _SignupPath.professional;
  String? _pendingVerificationId;

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _verifyIdentity() async {
    final selectedPath = _selectedPath;
    setState(() => _submitting = true);
    try {
      final service = StaffSignupService();
      final verificationId =
          _pendingVerificationId ?? await service.verifyIdentity();
      _pendingVerificationId = verificationId;
      final account = await service.createAccount(
        verificationId: verificationId,
      );
      if (!mounted) return;
      setState(() {
        _accountCreated = true;
        _submitting = false;
        _pendingVerificationId = null;
      });

      final token = account['token']?.toString().trim() ?? '';
      final userId = (account['id_user'] ?? account['id_persona'] ?? '')
          .toString()
          .trim();
      if (token.isEmpty || userId.isEmpty) return;

      await AuthTokenStore.instance.write(token);
      if (!mounted) return;
      if (selectedPath != _SignupPath.professional) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => CommercialSignupScreen(
              authToken: token,
              profile: selectedPath == _SignupPath.privateConsultorio
                  ? 'CONSULTORIO'
                  : 'CLINICA',
            ),
          ),
        );
        return;
      }
      await navigatePersonalsaludAfterLogin(
        userId,
        'Personal de Salud',
        context,
      );
    } catch (error) {
      final message = error.toString().replaceFirst('Exception: ', '');
      if (_accountCreated) {
        _snack(
          'Tu cuenta está creada, pero no pudimos iniciar la sesión. '
          'Volvé al inicio e ingresá verificando tu identidad.',
        );
        return;
      }
      _snack(
        message == 'Verificación cancelada.'
            ? 'Cancelaste la verificación.'
            : _pendingVerificationId != null
            ? 'Tu identidad ya fue verificada, pero no pudimos crear la cuenta. '
                  'Reintentá sin volver a verificarte.'
            : 'No pudimos continuar con la creación de tu cuenta. Intentá nuevamente. '
                  'Si el problema continúa, contactá a soporte.',
      );
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _buildPathCard({
    required _SignupPath path,
    required String title,
    required String description,
    bool enabled = true,
  }) {
    final tokens = context.bio;
    final primary = Theme.of(context).colorScheme.primary;
    final selected = _selectedPath == path;
    return Container(
      margin: const EdgeInsets.only(bottom: BioSpacing.md),
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        border: Border.all(
          color: selected ? primary : tokens.paperDividerDefault,
          width: selected ? 2 : 1,
        ),
        borderRadius: BorderRadius.circular(12),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: enabled ? () => setState(() => _selectedPath = path) : null,
        child: Padding(
          padding: const EdgeInsets.all(BioSpacing.md),
          child: Row(
            children: [
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                color: enabled ? primary : tokens.textMuted,
              ),
              BioSpacing.gapW(BioSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: BioTypography.title),
                    BioSpacing.gapH(BioSpacing.xs),
                    Text(
                      description,
                      style: BioTypography.bodySm.copyWith(
                        color: tokens.textMuted,
                      ),
                    ),
                  ],
                ),
              ),
              if (!enabled) ...[
                BioSpacing.gapW(BioSpacing.sm),
                const Icon(Icons.schedule, size: 18),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: AppBar(title: const Text('Crear cuenta')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: BioSpacing.pageAll,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _selectedPath == _SignupPath.professional
                    ? 'Creá tu cuenta de Personal de Salud'
                    : 'Verificá tu identidad para iniciar el alta',
                style: BioTypography.h2,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.sm),
              Text(
                _selectedPath == _SignupPath.professional
                    ? 'Elegí qué tipo de cuenta necesitás. No necesitás un e-mail para crear una cuenta profesional.'
                    : 'La identidad se crea primero desde la app. Después completarás los datos del efector y el checkout mensual.',
                style: BioTypography.body.copyWith(color: tokens.textMuted),
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.xl),
              _buildPathCard(
                path: _SignupPath.professional,
                title: 'Cuenta profesional',
                description:
                    'Gratis. Creá tu identidad; la administración del centro te suma cuando corresponda.',
              ),
              _buildPathCard(
                path: _SignupPath.privateConsultorio,
                title: 'Consultorio propio',
                description:
                    'Alta paga mensual en ARS. Se asigna automáticamente AdminEfector.',
              ),
              _buildPathCard(
                path: _SignupPath.clinic,
                title: 'Clínica o centro',
                description:
                    'Alta paga mensual en ARS para administrar una institución.',
              ),
              BioSpacing.gapH(BioSpacing.md),
              if (_pendingVerificationId != null) ...[
                Text(
                  'Tu identidad ya está verificada. Podés reintentar la creación '
                  'de la cuenta sin volver a hacer este paso.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.md),
              ],
              if (!_accountCreated) ...[
                BioButton.primary(
                  label: _submitting
                      ? 'Verificando…'
                      : _pendingVerificationId != null
                      ? 'Reintentar creación de cuenta'
                      : _selectedPath == _SignupPath.professional
                      ? 'Verificar identidad y crear cuenta'
                      : 'Verificar identidad y continuar',
                  icon: Icons.verified_user_outlined,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _submitting,
                  onPressed: _submitting ? null : _verifyIdentity,
                ),
              ],
              if (_accountCreated) ...[
                Container(
                  padding: const EdgeInsets.all(BioSpacing.md),
                  decoration: BoxDecoration(
                    color: tokens.paperSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: tokens.paperDividerDefault),
                  ),
                  child: Text(
                    'Tu cuenta está creada. Si trabajás en un centro, su '
                    'AdminEfector debe sumarte para que puedas acceder a las '
                    'funciones clínicas. Crear la cuenta no te asigna a un centro. '
                    'Para volver a entrar, verificá tu identidad desde el inicio.',
                    style: BioTypography.body,
                    textAlign: TextAlign.center,
                  ),
                ),
                BioSpacing.gapH(BioSpacing.md),
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Volver al inicio'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
