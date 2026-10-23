import 'package:flutter/material.dart';

import '../text/shared_texts.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/api_config.dart';
import '../theme/tokens/tokens.dart';

/// CTA hacia el alta de consultorio en el sitio institucional (opción A).
class InstitutionalSignupLink extends StatelessWidget {
  const InstitutionalSignupLink({super.key});

  Future<void> _open(BuildContext context) async {
    final uri = Uri.parse(AppConfig.institutionalSignupUrl);
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text(SharedTexts.institutionalSignupOpenFailed)),
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
          SharedTexts.institutionalSignupPrompt,
          textAlign: TextAlign.center,
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
