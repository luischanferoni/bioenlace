import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../auth/play_review_auth.dart';
import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';

/// Diálogo de acceso para revisores (Google Play). Se abre con 5 toques en el logo.
class PlayReviewLoginSheet extends StatefulWidget {
  final String appClient;
  final Future<void> Function(String userId, String userName) onSuccess;

  const PlayReviewLoginSheet({
    super.key,
    this.appClient = 'bioenlace-flutter',
    required this.onSuccess,
  });

  static Future<void> show(
    BuildContext context, {
    required String appClient,
    required Future<void> Function(String userId, String userName) onSuccess,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: PlayReviewLoginSheet(
          appClient: appClient,
          onSuccess: onSuccess,
        ),
      ),
    );
  }

  @override
  State<PlayReviewLoginSheet> createState() => _PlayReviewLoginSheetState();
}

class _PlayReviewLoginSheetState extends State<PlayReviewLoginSheet> {
  final _userCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  bool _loading = false;
  bool _obscure = true;

  @override
  void dispose() {
    _userCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading) return;
    setState(() => _loading = true);
    try {
      final data = await PlayReviewAuth.login(
        username: _userCtrl.text,
        password: _passCtrl.text,
        appClient: widget.appClient,
      );
      final payload = data['data'] is Map ? Map<String, dynamic>.from(data['data'] as Map) : <String, dynamic>{};
      final user = payload['user'] is Map ? Map<String, dynamic>.from(payload['user'] as Map) : <String, dynamic>{};
      final persona = payload['persona'] is Map ? Map<String, dynamic>.from(payload['persona'] as Map) : <String, dynamic>{};
      final token = payload['token']?.toString();

      final userId = (user['id'] ?? persona['id_persona'] ?? '').toString();
      final userName = user['name']?.toString().trim() ??
          '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();

      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('is_logged_in', true);
      await prefs.setString('user_id', userId);
      await prefs.setString('user_name', userName.isNotEmpty ? userName : 'Usuario');
      if (persona['documento'] != null) {
        await prefs.setString('dni_detected', persona['documento'].toString());
      }
      if (token != null && token.isNotEmpty) {
        await prefs.setString('auth_token', token);
      }

      if (!mounted) return;
      Navigator.of(context).pop();
      await widget.onSuccess(userId, userName.isNotEmpty ? userName : 'Usuario');
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Padding(
      padding: const EdgeInsets.fromLTRB(BioSpacing.xl, 0, BioSpacing.xl, BioSpacing.xl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Acceso para revisión', style: BioTypography.h3),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Credenciales provistas en Google Play Console. Uso solo para revisores de la tienda.',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          TextField(
            controller: _userCtrl,
            decoration: const InputDecoration(labelText: 'Usuario'),
            textInputAction: TextInputAction.next,
            autocorrect: false,
          ),
          BioSpacing.gapH(BioSpacing.md),
          TextField(
            controller: _passCtrl,
            decoration: InputDecoration(
              labelText: 'Contraseña',
              suffixIcon: IconButton(
                icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
                onPressed: () => setState(() => _obscure = !_obscure),
              ),
            ),
            obscureText: _obscure,
            onSubmitted: (_) => _submit(),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          BioButton.primary(
            label: _loading ? 'Ingresando…' : 'Ingresar',
            fullWidth: true,
            loading: _loading,
            onPressed: _loading ? null : _submit,
          ),
        ],
      ),
    );
  }
}
