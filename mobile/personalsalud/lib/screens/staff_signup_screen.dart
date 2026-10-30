import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../auth/personalsalud_post_login.dart';
import '../services/staff_signup_service.dart';

/// Alta de cuenta de personal de salud: Didit KYC → cuenta de identidad → consultorio.
class StaffSignupScreen extends StatefulWidget {
  const StaffSignupScreen({super.key});

  @override
  State<StaffSignupScreen> createState() => _StaffSignupScreenState();
}

class _StaffSignupScreenState extends State<StaffSignupScreen> {
  final _emailCtrl = TextEditingController();
  final _nombreCtrl = TextEditingController();
  final _domicilioCtrl = TextEditingController();

  bool _submitting = false;
  String? _verificationId;
  Map<String, dynamic>? _account;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _nombreCtrl.dispose();
    _domicilioCtrl.dispose();
    super.dispose();
  }

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _verifyIdentity() async {
    final email = _emailCtrl.text.trim();
    if (email.isEmpty) {
      _snack('Ingresá tu e-mail.');
      return;
    }

    setState(() => _submitting = true);
    try {
      final service = StaffSignupService();
      final verificationId = await service.verifyIdentity();
      final account = await service.createAccount(
        verificationId: verificationId,
        email: email,
      );
      if (!mounted) return;
      setState(() {
        _verificationId = verificationId;
        _account = account;
        _submitting = false;
      });
    } catch (error) {
      final message = error.toString().replaceFirst('Exception: ', '');
      _snack(
        message == 'Verificación cancelada.'
            ? 'Cancelaste la verificación.'
            : 'No pudimos continuar con la creación de tu cuenta. Intentá nuevamente. '
                  'Si el problema continúa, contactá a soporte.',
      );
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _createConsultorio() async {
    final verificationId = _verificationId;
    if (verificationId == null) return;
    final nombre = _nombreCtrl.text.trim();
    final domicilio = _domicilioCtrl.text.trim();
    if (nombre.isEmpty || domicilio.isEmpty) {
      _snack('Completá nombre y domicilio del consultorio.');
      return;
    }

    setState(() => _submitting = true);
    try {
      await StaffSignupService().createConsultorio(
        verificationId: verificationId,
        email: _emailCtrl.text.trim(),
        nombre: nombre,
        domicilio: domicilio,
      );

      final account = _account ?? const <String, dynamic>{};
      final token = account['token']?.toString() ?? '';
      final userId = (account['id_user'] ?? account['id_persona'] ?? '')
          .toString();
      final userName = account['email']?.toString().trim() ?? 'Usuario';

      final prefs = await SharedPreferences.getInstance();
      if (token.isNotEmpty) {
        await prefs.setString('auth_token', token);
      }

      if (!mounted) return;
      await navigatePersonalsaludAfterLogin(userId, userName, context);
    } catch (_) {
      _snack(
        'No pudimos crear tu consultorio. Revisá los datos e intentá nuevamente.',
      );
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final identityDone = _verificationId != null;

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
                'Creá tu cuenta de Personal de Salud',
                style: BioTypography.h2,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.sm),
              Text(
                'Verificamos tu identidad con tu documento y una selfie; si trabajás por tu cuenta, creás tu consultorio privado.',
                style: BioTypography.body.copyWith(color: tokens.textMuted),
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.xl),
              TextField(
                controller: _emailCtrl,
                decoration: const InputDecoration(labelText: 'E-mail'),
                keyboardType: TextInputType.emailAddress,
                autocorrect: false,
                enabled: !_submitting && !identityDone,
              ),
              BioSpacing.gapH(BioSpacing.lg),
              if (!identityDone) ...[
                BioButton.primary(
                  label: _submitting ? 'Verificando…' : 'Verificar identidad',
                  icon: Icons.verified_user_outlined,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _submitting,
                  onPressed: _submitting ? null : _verifyIdentity,
                ),
              ],
              if (identityDone) ...[
                TextField(
                  controller: _nombreCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Nombre del consultorio',
                  ),
                  enabled: !_submitting,
                ),
                BioSpacing.gapH(BioSpacing.md),
                TextField(
                  controller: _domicilioCtrl,
                  decoration: const InputDecoration(labelText: 'Domicilio'),
                  enabled: !_submitting,
                ),
                BioSpacing.gapH(BioSpacing.lg),
                BioButton.primary(
                  label: _submitting
                      ? 'Creando…'
                      : 'Crear consultorio y continuar',
                  icon: Icons.storefront_outlined,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _submitting,
                  onPressed: _submitting ? null : _createConsultorio,
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  'No se realizará ningún cobro durante este paso.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                  textAlign: TextAlign.center,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
