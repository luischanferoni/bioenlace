import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../auth/paciente_dev_login.dart';
import '../services/chat_service.dart';
import 'main_screen.dart';
import 'signup_screen.dart';

/// Pantalla de configuración del paciente (perfil, preferencias, cerrar sesión).
class ConfiguracionScreen extends StatelessWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final VoidCallback? onOpenAlertas;
  final int alertasNoLeidas;

  const ConfiguracionScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.onOpenAlertas,
    this.alertasNoLeidas = 0,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Configuración'),
      body: ListView(
        padding: BioSpacing.pageAll,
        children: [
          _buildCuentaCard(context),
          BioSpacing.gapH(BioSpacing.lg),
          _buildPreferenciasCard(context),
          BioSpacing.gapH(BioSpacing.lg),
          _buildCerrarSesionCard(context),
        ],
      ),
    );
  }

  Widget _buildCuentaCard(BuildContext context) {
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary).base;
    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Tu cuenta',
            style: BioTypography.title.copyWith(fontWeight: FontWeight.w700),
          ),
          BioSpacing.gapH(BioSpacing.md),
          Row(
            children: [
              Icon(Icons.person_outline, color: primary, size: 20),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text('Usuario: $userName', style: BioTypography.body),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              Icon(Icons.badge_outlined, color: primary, size: 20),
              BioSpacing.gapW(BioSpacing.sm),
              Text(
                'ID: $userId',
                style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPreferenciasCard(BuildContext context) {
    return BioCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          _ConfigTile(
            icon: Icons.notifications_outlined,
            title: 'Alertas',
            subtitle: alertasNoLeidas > 0
                ? '$alertasNoLeidas sin leer'
                : 'Avisos del consultorio y turnos',
            trailing: alertasNoLeidas > 0
                ? BioBadge.danger(
                    alertasNoLeidas > 99 ? '99+' : '$alertasNoLeidas',
                  )
                : null,
            onTap: onOpenAlertas ??
                () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Iniciá sesión para ver alertas'),
                    ),
                  );
                },
          ),
          BioDivider.subtle(),
          CarePlanReminderGlobalSwitch(authToken: authToken),
          BioDivider.subtle(),
          _ConfigTile(
            icon: Icons.language_outlined,
            title: 'Idioma',
            subtitle: 'Español',
            onTap: () => _proximamente(context),
          ),
          BioDivider.subtle(),
          _ConfigTile(
            icon: Icons.dark_mode_outlined,
            title: 'Tema',
            subtitle: 'Claro',
            onTap: () => _proximamente(context),
          ),
        ],
      ),
    );
  }

  Widget _buildCerrarSesionCard(BuildContext context) {
    final danger = IntentPalette.of(UiIntent.danger).base;
    return BioCard(
      padding: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(BioRadius.sm),
        onTap: () => _confirmarCerrarSesion(context),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: BioSpacing.md,
            vertical: BioSpacing.md,
          ),
          child: Row(
            children: [
              Icon(Icons.logout, color: danger, size: 22),
              BioSpacing.gapW(BioSpacing.md),
              Expanded(
                child: Text(
                  'Cerrar sesión',
                  style: BioTypography.title.copyWith(
                    color: danger,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Icon(Icons.chevron_right, color: context.bio.textMuted),
            ],
          ),
        ),
      ),
    );
  }

  void _proximamente(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Próximamente')),
    );
  }

  Future<void> _confirmarCerrarSesion(BuildContext context) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('¿Estás seguro de que deseas cerrar sesión?'),
        actionsPadding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.md,
          vertical: BioSpacing.sm,
        ),
        actions: [
          BioButton(
            label: 'Cancelar',
            intent: UiIntent.neutral,
            variant: BioButtonVariant.soft,
            size: BioButtonSize.sm,
            onPressed: () => Navigator.pop(ctx, false),
          ),
          BioButton.danger(
            label: 'Cerrar sesión',
            size: BioButtonSize.sm,
            onPressed: () => Navigator.pop(ctx, true),
          ),
        ],
      ),
    );

    if (confirm != true || !context.mounted) return;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_logged_in', false);
    await prefs.remove('user_id');
    await prefs.remove('user_name');
    await prefs.remove('auth_token');

    if (!context.mounted) return;

    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => _buildLoginScreen(context),
      ),
      (route) => false,
    );
  }

  Widget _buildLoginScreen(BuildContext context) {
    return LoginScreen(
      appTitle: 'Bienvenido a BioEnlace',
      appSubtitle: 'Tu asistente de salud personal',
      welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
      signupButtonText: '¿No tienes cuenta? Regístrate aquí',
      goToHomeButtonText: 'Ir al inicio de la app',
      onLoginSuccess: (userId, userName, loginContext) async {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('is_logged_in', true);
        await prefs.setString('user_id', userId);
        await prefs.setString('user_name', userName);
        if (!loginContext.mounted) return;
        final newChatService = ChatService(
          currentUserId: userId,
          currentUserName: userName,
          authToken: prefs.getString('auth_token'),
        );
        Navigator.pushReplacement(
          loginContext,
          MaterialPageRoute(
            builder: (_) => MainScreen(
              chatService: newChatService,
              authToken: prefs.getString('auth_token'),
            ),
          ),
        );
      },
      onNavigateToSignup: (loginContext) {
        Navigator.push(
          loginContext,
          MaterialPageRoute(builder: (_) => SignupScreen()),
        );
      },
      onNavigateToHome: navigatePacienteDevHome,
    );
  }
}

/// Fila de configuración con ícono, título, subtítulo y acción.
class _ConfigTile extends StatelessWidget {
  const _ConfigTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary).base;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.md,
          vertical: BioSpacing.md,
        ),
        child: Row(
          children: [
            Icon(icon, color: primary, size: 22),
            BioSpacing.gapW(BioSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: BioTypography.title),
                  BioSpacing.gapH(2),
                  Text(
                    subtitle,
                    style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                  ),
                ],
              ),
            ),
            if (trailing != null) ...[
              BioSpacing.gapW(BioSpacing.sm),
              trailing!,
            ],
            BioSpacing.gapW(BioSpacing.xs),
            Icon(Icons.chevron_right, color: tokens.textMuted),
          ],
        ),
      ),
    );
  }
}
