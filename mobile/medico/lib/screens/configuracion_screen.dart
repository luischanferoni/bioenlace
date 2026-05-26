// lib/screens/configuracion_screen.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../main.dart';
import '../services/config_service.dart';
import 'main_screen.dart';

class ConfiguracionScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final VoidCallback? onEncounterChanged;

  const ConfiguracionScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.onEncounterChanged,
  }) : super(key: key);

  @override
  State<ConfiguracionScreen> createState() => _ConfiguracionScreenState();
}

class _ConfiguracionScreenState extends State<ConfiguracionScreen> {
  final ConfigService _configService = ConfigService();
  List<EncounterClass> _encounterClasses = [];
  String _currentEncounterCode = 'AMB';
  String _currentEncounterLabel = 'Ambulatoria';
  int? _efectorId;
  int? _servicioId;
  bool _isLoadingEncounter = false;
  bool _isLoadingClasses = true;

  @override
  void initState() {
    super.initState();
    _configService.authToken = widget.authToken;
    _loadStoredEncounter();
    _loadEncounterClasses();
  }

  Future<void> _loadStoredEncounter() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _currentEncounterCode = prefs.getString('encounter_class') ?? 'AMB';
      _currentEncounterLabel =
          prefs.getString('encounter_class_label') ?? 'Ambulatoria';
      _efectorId = prefs.getInt('efector_id');
      _servicioId = prefs.getInt('servicio_id');
    });
  }

  Future<void> _loadEncounterClasses() async {
    try {
      final list = await _configService.getEncounterClasses();
      if (mounted) {
        setState(() {
          _encounterClasses = list;
          _isLoadingClasses = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoadingClasses = false);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Error al cargar áreas: $e')),
          );
        }
      }
    }
  }

  Future<void> _changeEncounterClass(EncounterClass encounter) async {
    if (encounter.code == _currentEncounterCode) return;
    if (_efectorId == null || _servicioId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
              'Completá la configuración inicial (efector y servicio) para cambiar el área.'),
        ),
      );
      return;
    }

    setState(() => _isLoadingEncounter = true);
    try {
      final sessionConfig = await _configService.setSession(
        efectorId: _efectorId!,
        servicioId: _servicioId!,
        encounterClass: encounter.code,
        userId: widget.userId,
      );
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
          'encounter_class', sessionConfig.encounterClass.code);
      await prefs.setString(
          'encounter_class_label', sessionConfig.encounterClass.label);
      final sessionToken = sessionConfig.contextToken;
      if (sessionToken != null && sessionToken.isNotEmpty) {
        await prefs.setString('auth_token', sessionToken);
        _configService.authToken = sessionToken;
      }
      if (mounted) {
        setState(() {
          _currentEncounterCode = sessionConfig.encounterClass.code;
          _currentEncounterLabel = sessionConfig.encounterClass.label;
          _isLoadingEncounter = false;
        });
        widget.onEncounterChanged?.call();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text(
                  'Área actualizada: ${sessionConfig.encounterClass.label}. Andá a Inicio para ver el cambio.')),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoadingEncounter = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cambiar área: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      appBar: const BioAppBar(title: 'Configuración'),
      body: Container(
        color: tokens.paperBackground,
        child: ListView(
          padding: BioSpacing.pageAll,
          children: [
            _buildUsuarioCard(),
            BioSpacing.gapH(BioSpacing.md),
            _buildAreaCard(),
            BioSpacing.gapH(BioSpacing.md),
            _buildOpcionesCard(),
            BioSpacing.gapH(BioSpacing.md),
            _buildCerrarSesionCard(),
          ],
        ),
      ),
    );
  }

  Widget _buildUsuarioCard() {
    final primary = IntentPalette.of(UiIntent.primary).base;
    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Información del usuario', style: BioTypography.h3),
          BioSpacing.gapH(BioSpacing.md),
          Row(
            children: [
              Icon(Icons.person_outline, color: primary, size: 20),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text('Usuario: ${widget.userName}',
                    style: BioTypography.title),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Row(
            children: [
              Icon(Icons.badge_outlined, color: primary, size: 20),
              BioSpacing.gapW(BioSpacing.sm),
              Text('ID: ${widget.userId}', style: BioTypography.bodySm),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAreaCard() {
    final primary = IntentPalette.of(UiIntent.primary).base;
    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Área de trabajo', style: BioTypography.h3),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            'Según el área, Inicio mostrará turnos, pacientes internados o ingresos en guardia.',
            style: BioTypography.caption,
          ),
          BioSpacing.gapH(BioSpacing.md),
          if (_isLoadingClasses)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(BioSpacing.md),
                child: CircularProgressIndicator(),
              ),
            )
          else ...[
            Row(
              children: [
                Icon(Icons.medical_services_outlined,
                    color: primary, size: 20),
                BioSpacing.gapW(BioSpacing.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(_currentEncounterLabel, style: BioTypography.title),
                      Text('Código: $_currentEncounterCode',
                          style: BioTypography.caption),
                    ],
                  ),
                ),
              ],
            ),
            BioSpacing.gapH(BioSpacing.md),
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.sm,
              children: _encounterClasses.map((ec) {
                final isSelected = ec.code == _currentEncounterCode;
                return BioChip(
                  label: ec.label,
                  selected: isSelected,
                  onTap: _isLoadingEncounter
                      ? null
                      : () => _changeEncounterClass(ec),
                );
              }).toList(),
            ),
            if (_isLoadingEncounter)
              const Padding(
                padding: EdgeInsets.only(top: BioSpacing.md),
                child: Center(
                  child: SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _buildOpcionesCard() {
    return BioCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          _ConfigTile(
            icon: Icons.notifications_outlined,
            title: 'Notificaciones',
            subtitle: 'Gestionar notificaciones',
            trailing: Switch(
              value: true,
              onChanged: (value) {
                // TODO: Implementar gestión de notificaciones
              },
            ),
          ),
          const BioDivider(),
          _ConfigTile(
            icon: Icons.language_outlined,
            title: 'Idioma',
            subtitle: 'Español',
            trailing: const Icon(Icons.chevron_right),
            onTap: () => _showDevSnack(),
          ),
          const BioDivider(),
          _ConfigTile(
            icon: Icons.dark_mode_outlined,
            title: 'Tema',
            subtitle: 'Claro',
            trailing: const Icon(Icons.chevron_right),
            onTap: () => _showDevSnack(),
          ),
        ],
      ),
    );
  }

  Widget _buildCerrarSesionCard() {
    final danger = IntentPalette.of(UiIntent.danger).base;
    return BioCard(
      padding: EdgeInsets.zero,
      child: _ConfigTile(
        icon: Icons.logout,
        iconColor: danger,
        title: 'Cerrar sesión',
        titleColor: danger,
        onTap: _confirmarLogout,
      ),
    );
  }

  Future<void> _confirmarLogout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('¿Seguro que querés cerrar sesión?'),
        actions: [
          BioButton(
            label: 'Cancelar',
            intent: UiIntent.neutral,
            variant: BioButtonVariant.soft,
            size: BioButtonSize.sm,
            onPressed: () => Navigator.pop(context, false),
          ),
          BioButton.danger(
            label: 'Cerrar sesión',
            size: BioButtonSize.sm,
            onPressed: () => Navigator.pop(context, true),
          ),
        ],
      ),
    );

    if (confirm == true) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('is_logged_in', false);
      await prefs.remove('user_id');
      await prefs.remove('user_name');

      navigatorKey.currentState?.pushReplacement(
        MaterialPageRoute(
          builder: (_) => LoginScreen(
            appTitle: 'Bienvenido a BioEnlace Médico',
            appSubtitle: 'Tu plataforma de gestión médica',
            onLoginSuccess: (userId, userName, loginContext) async {
              final p = await SharedPreferences.getInstance();
              await p.setBool('is_logged_in', true);
              await p.setString('user_id', userId);
              await p.setString('user_name', userName);
              navigatorKey.currentState?.pushReplacement(
                MaterialPageRoute(
                  builder: (_) => MainScreen(
                    userId: userId,
                    userName: userName,
                    authToken: p.getString('auth_token'),
                    idProfesionalEfectorServicio:
                        p.getInt('id_profesional_efector_servicio')?.toString(),
                  ),
                ),
              );
            },
          ),
        ),
      );
    }
  }

  void _showDevSnack() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Funcionalidad en desarrollo')),
    );
  }
}

class _ConfigTile extends StatelessWidget {
  const _ConfigTile({
    required this.icon,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.iconColor,
    this.titleColor,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final Color? iconColor;
  final Color? titleColor;

  @override
  Widget build(BuildContext context) {
    final primary = IntentPalette.of(UiIntent.primary).base;
    final resolvedIcon = iconColor ?? primary;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.lg,
          vertical: BioSpacing.md,
        ),
        child: Row(
          children: [
            Icon(icon, color: resolvedIcon, size: 22),
            BioSpacing.gapW(BioSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: BioTypography.title.copyWith(color: titleColor),
                  ),
                  if (subtitle != null) ...[
                    BioSpacing.gapH(2),
                    Text(subtitle!, style: BioTypography.caption),
                  ],
                ],
              ),
            ),
            if (trailing != null) trailing!,
          ],
        ),
      ),
    );
  }
}
