import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/api_config.dart';
import '../theme/tokens/tokens.dart';

/// Enlace a la política de privacidad publicada en el sitio institucional.
class PrivacyPolicyLink extends StatelessWidget {
  const PrivacyPolicyLink({super.key});

  Future<void> _open(BuildContext context) async {
    final uri = Uri.parse(AppConfig.privacyPolicyUrl);
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo abrir la política de privacidad')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Center(
      child: TextButton(
        onPressed: () => _open(context),
        child: Text(
          'Política de privacidad',
          style: BioTypography.bodySm.copyWith(
            color: tokens.textMuted,
            decoration: TextDecoration.underline,
            decorationColor: tokens.textMuted,
          ),
        ),
      ),
    );
  }
}
