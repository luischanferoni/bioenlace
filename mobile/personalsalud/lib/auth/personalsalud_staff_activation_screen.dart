import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Activación de cuenta con código presencial (administración del efector).
class PersonalsaludStaffActivationScreen extends StatefulWidget {
  const PersonalsaludStaffActivationScreen({super.key});

  @override
  State<PersonalsaludStaffActivationScreen> createState() =>
      _PersonalsaludStaffActivationScreenState();
}

class _PersonalsaludStaffActivationScreenState
    extends State<PersonalsaludStaffActivationScreen> {
  final _usernameCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _repeatCtrl = TextEditingController();

  bool _obscurePassword = true;
  bool _submitting = false;

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _codeCtrl.dispose();
    _passwordCtrl.dispose();
    _repeatCtrl.dispose();
    super.dispose();
  }

  void _snack(String message, UiIntent intent) {
    if (!mounted) return;
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: palette.base),
    );
  }

  Future<void> _submit() async {
    if (_submitting) return;

    final username = _usernameCtrl.text.trim();
    final code = _codeCtrl.text.trim();
    final password = _passwordCtrl.text;
    final repeat = _repeatCtrl.text;

    if (username.isEmpty || code.isEmpty || password.isEmpty) {
      _snack('Completá usuario, código y contraseña.', UiIntent.warning);
      return;
    }
    if (password != repeat) {
      _snack('Las contraseñas no coinciden.', UiIntent.warning);
      return;
    }

    setState(() => _submitting = true);
    try {
      await StaffAccountActivation.activate(
        username: username,
        activationCode: code,
        password: password,
      );
      if (!mounted) return;
      _snack('Cuenta activada. Ya podés ingresar.', UiIntent.success);
      Navigator.of(context).pop();
    } catch (e) {
      _snack(
        e.toString().replaceFirst('Exception: ', ''),
        UiIntent.danger,
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: AppBar(
        title: const Text('Activar cuenta'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: BioSpacing.pageAll,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Ingresá el código que te entregó administración de tu centro '
                'y elegí tu contraseña.',
                style: BioTypography.body.copyWith(color: tokens.textMuted),
              ),
              BioSpacing.gapH(BioSpacing.lg),
              TextField(
                controller: _usernameCtrl,
                decoration: const InputDecoration(labelText: 'Usuario'),
                autocorrect: false,
                enabled: !_submitting,
              ),
              BioSpacing.gapH(BioSpacing.md),
              TextField(
                controller: _codeCtrl,
                decoration: const InputDecoration(labelText: 'Código de activación'),
                keyboardType: TextInputType.number,
                enabled: !_submitting,
              ),
              BioSpacing.gapH(BioSpacing.md),
              TextField(
                controller: _passwordCtrl,
                decoration: const InputDecoration(labelText: 'Contraseña nueva'),
                obscureText: _obscurePassword,
                enabled: !_submitting,
              ),
              BioSpacing.gapH(BioSpacing.md),
              TextField(
                controller: _repeatCtrl,
                decoration: const InputDecoration(labelText: 'Repetir contraseña'),
                obscureText: _obscurePassword,
                onSubmitted: (_) => _submit(),
                enabled: !_submitting,
              ),
              BioSpacing.gapH(BioSpacing.lg),
              BioButton.primary(
                label: _submitting ? 'Activando…' : 'Activar cuenta',
                fullWidth: true,
                loading: _submitting,
                onPressed: _submitting ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
