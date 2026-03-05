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
      _currentEncounterLabel = prefs.getString('encounter_class_label') ?? 'Ambulatoria';
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
        setState(() {
          _isLoadingClasses = false;
        });
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
          content: Text('Completa la configuración inicial (efector y servicio) para cambiar el área.'),
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
      await prefs.setString('encounter_class', sessionConfig.encounterClass.code);
      await prefs.setString('encounter_class_label', sessionConfig.encounterClass.label);
      if (mounted) {
        setState(() {
          _currentEncounterCode = sessionConfig.encounterClass.code;
          _currentEncounterLabel = sessionConfig.encounterClass.label;
          _isLoadingEncounter = false;
        });
        widget.onEncounterChanged?.call();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Área actualizada: ${sessionConfig.encounterClass.label}. Ve a Inicio para ver el cambio.')),
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
    return Scaffold(
      appBar: AppBar(title: const Text('Configuración')),
      body: Container(
        color: AppTheme.backgroundColor,
        child: ListView(
          padding: const EdgeInsets.all(16.0),
          children: [
            // Información del usuario
            Card(
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Información del Usuario',
                      style: AppTheme.h4Style.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(Icons.person, color: AppTheme.primaryColor),
                        const SizedBox(width: 8),
                        Text(
                          'Usuario: ${widget.userName}',
                          style: AppTheme.h5Style,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(Icons.badge, color: AppTheme.primaryColor),
                        const SizedBox(width: 8),
                        Text(
                          'ID: ${widget.userId}',
                          style: AppTheme.subTitleStyle,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            // Área de trabajo (Encounter Class)
            Card(
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Área de trabajo',
                      style: AppTheme.h4Style.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Según el área, la pantalla de Inicio mostrará turnos, pacientes internados o ingresos en guardia.',
                      style: AppTheme.subTitleStyle.copyWith(fontSize: 12),
                    ),
                    const SizedBox(height: 12),
                    if (_isLoadingClasses)
                      const Center(child: Padding(
                        padding: EdgeInsets.all(16.0),
                        child: CircularProgressIndicator(),
                      ))
                    else ...[
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(Icons.medical_services, color: AppTheme.primaryColor),
                        title: Text(_currentEncounterLabel, style: AppTheme.h5Style),
                        subtitle: Text('Código: $_currentEncounterCode', style: AppTheme.subTitleStyle.copyWith(fontSize: 11)),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: _encounterClasses.map((ec) {
                          final isSelected = ec.code == _currentEncounterCode;
                          return ChoiceChip(
                            label: Text(ec.label),
                            selected: isSelected,
                            onSelected: _isLoadingEncounter
                                ? null
                                : (selected) {
                                    if (selected) _changeEncounterClass(ec);
                                  },
                            selectedColor: AppTheme.primaryColor.withOpacity(0.3),
                          );
                        }).toList(),
                      ),
                      if (_isLoadingEncounter)
                        const Padding(
                          padding: EdgeInsets.only(top: 12.0),
                          child: Center(child: SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )),
                        ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            // Opciones de configuración
            Card(
              elevation: 0,
              child: Column(
                children: [
                  ListTile(
                    leading: Icon(Icons.notifications, color: AppTheme.primaryColor),
                    title: Text('Notificaciones', style: AppTheme.h5Style),
                    subtitle: Text('Gestionar notificaciones', style: AppTheme.subTitleStyle),
                    trailing: Switch(
                      value: true,
                      onChanged: (value) {
                        // TODO: Implementar gestión de notificaciones
                      },
                    ),
                  ),
                  const Divider(),
                  ListTile(
                    leading: Icon(Icons.language, color: AppTheme.primaryColor),
                    title: Text('Idioma', style: AppTheme.h5Style),
                    subtitle: Text('Español', style: AppTheme.subTitleStyle),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Funcionalidad en desarrollo'),
                        ),
                      );
                    },
                  ),
                  const Divider(),
                  ListTile(
                    leading: Icon(Icons.dark_mode, color: AppTheme.primaryColor),
                    title: Text('Tema', style: AppTheme.h5Style),
                    subtitle: Text('Claro', style: AppTheme.subTitleStyle),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Funcionalidad en desarrollo'),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 16),
            
            // Cerrar sesión
            Card(
              elevation: 0,
              child: ListTile(
                leading: Icon(Icons.logout, color: AppTheme.dangerColor),
                title: Text(
                  'Cerrar Sesión',
                  style: AppTheme.h5Style.copyWith(
                    color: AppTheme.dangerColor,
                  ),
                ),
                onTap: () async {
                  // Confirmar cierre de sesión
                  final confirm = await showDialog<bool>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('Cerrar Sesión'),
                      content: const Text('¿Estás seguro de que deseas cerrar sesión?'),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(context, false),
                          child: const Text('Cancelar'),
                        ),
                        TextButton(
                          onPressed: () => Navigator.pop(context, true),
                          style: TextButton.styleFrom(
                            foregroundColor: AppTheme.dangerColor,
                          ),
                          child: const Text('Cerrar Sesión'),
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
                                  rrhhId: p.getString('rrhh_id'),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    );
                  }
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

