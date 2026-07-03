import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../auth/paciente_authenticated_shell.dart';
import '../auth/paciente_post_login.dart';
import '../services/chat_service.dart';
import 'main_screen.dart';
import 'person_representation_hub_screen.dart';
import 'paciente_provincia_context_screen.dart';
import 'signup_screen.dart';

/// Pantalla de configuración del paciente (perfil, preferencias, cerrar sesión).
class ConfiguracionScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final VoidCallback? onEnviarQueja;

  const ConfiguracionScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.onEnviarQueja,
  }) : super(key: key);

  @override
  State<ConfiguracionScreen> createState() => _ConfiguracionScreenState();
}

class _ConfiguracionScreenState extends State<ConfiguracionScreen> {
  @override
  void initState() {
    super.initState();
    PacienteContextScope.instance.bindAuthToken(widget.authToken);
  }

  void _abrirProvinciaContexto() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PacienteProvinciaContextScreen(
          authToken: widget.authToken,
        ),
      ),
    ).then((changed) {
      if (changed == true && mounted) setState(() {});
    });
  }

  Future<void> _toggleSector() async {
    final actual = PacienteContextScope.instance.state.sectorSalud;
    final nuevo = actual == 'privado' ? 'publico' : 'privado';
    final ok = await PacienteContextScope.instance.actualizarSector(
      nuevo,
      authToken: widget.authToken,
    );
    if (!mounted) return;
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo actualizar el sector')),
      );
    }
  }

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
                child: Text('Usuario: ${widget.userName}', style: BioTypography.body),
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
          CarePlanReminderGlobalSwitch(authToken: widget.authToken),
          BioDivider.subtle(),
          ListenableBuilder(
            listenable: PacienteContextScope.instance,
            builder: (context, _) {
              final ctx = PacienteContextScope.instance.state;
              final sectorLabel =
                  ctx.sectorSalud == 'privado' ? 'Privado' : 'Público';
              final provinciaLabel =
                  ctx.provinciaNombre ?? 'Sin definir';
              return Column(
                children: [
                  _ConfigTile(
                    icon: Icons.account_balance_outlined,
                    title: 'Sector de salud',
                    subtitle: sectorLabel,
                    onTap: _toggleSector,
                  ),
                  BioDivider.subtle(),
                  _ConfigTile(
                    icon: Icons.map_outlined,
                    title: 'Provincia de contexto',
                    subtitle: provinciaLabel,
                    onTap: _abrirProvinciaContexto,
                  ),
                ],
              );
            },
          ),
          BioDivider.subtle(),
          _ConfigTile(
            icon: Icons.family_restroom_outlined,
            title: 'Representación',
            subtitle: 'Tutela, representantes y notificaciones',
            onTap: () {
              final actorId = int.tryParse(widget.userId) ?? 0;
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => PersonRepresentationHubScreen(
                    authToken: widget.authToken,
                    actorPersonaId: actorId,
                    actorLabel: widget.userName,
                  ),
                ),
              );
            },
          ),
          BioDivider.subtle(),
          _ConfigTile(
            icon: Icons.report_outlined,
            title: 'Enviar queja',
            subtitle: 'Problemas con la app, turnos o la atención recibida',
            onTap: widget.onEnviarQueja ??
                () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Iniciá sesión para enviar una queja'),
                    ),
                  );
                },
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
    DiditConfigResolver.clearCache();
    await BiometricSessionPrefs.clearOnLogout();
    await PersonRepresentationContext.instance.clearOnLogout();
    PacienteContextScope.instance.clearOnLogout();

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
      diditRemoteLoginAfterLogout: true,
      appClient: 'paciente-flutter',
      onLoginSuccess: (userId, userName, loginContext) async {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('is_logged_in', true);
        await prefs.setString('user_id', userId);
        await prefs.setString('user_name', userName);
        await CrashlyticsBootstrap.setUserId(userId);
        ClientDiagnosticApi.bindSession(
          authToken: prefs.getString('auth_token'),
          appClient: 'paciente-flutter',
        );
        if (!loginContext.mounted) return;
        final enrolled =
            await requirePacienteBiometricEnrollment(loginContext);
        if (!enrolled || !loginContext.mounted) return;
        final newChatService = ChatService(
          currentUserId: userId,
          currentUserName: userName,
          authToken: prefs.getString('auth_token'),
        );
        Navigator.pushReplacement(
          loginContext,
          MaterialPageRoute(
            builder: (_) => wrapPacienteAuthenticatedShell(
              child: MainScreen(
                chatService: newChatService,
                authToken: prefs.getString('auth_token'),
              ),
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
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

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
            BioSpacing.gapW(BioSpacing.xs),
            Icon(Icons.chevron_right, color: tokens.textMuted),
          ],
        ),
      ),
    );
  }
}
