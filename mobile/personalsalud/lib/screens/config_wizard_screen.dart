// lib/screens/config_wizard_screen.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../auth/personalsalud_authenticated_shell.dart';
import '../auth/personalsalud_post_login.dart';
import '../auth/personalsalud_login_screen.dart';
import '../auth/personalsalud_session_prefs.dart';
import '../services/config_service.dart';
import '../main.dart';
import 'main_screen.dart';

class ConfigWizardScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;

  const ConfigWizardScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
  }) : super(key: key);

  @override
  State<ConfigWizardScreen> createState() => _ConfigWizardScreenState();
}

class _ConfigWizardScreenState extends State<ConfigWizardScreen> {
  final ConfigService _configService = ConfigService();
  final PageController _pageController = PageController();

  int _currentStep = 0;
  bool _isLoading = false;
  String _errorMessage = '';

  SessionWizardOptions? _wizardOptions;
  List<Efector> _efectores = [];
  List<Servicio> _servicios = [];
  List<EncounterClass> _encounterClasses = [];
  List<EfectorConProblema> _efectoresConProblemas = [];

  Efector? _selectedEfector;
  Servicio? _selectedServicio;
  EncounterClass? _selectedEncounterClass;

  @override
  void initState() {
    super.initState();
    _configService.authToken = widget.authToken;
    _loadInitialData();
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _loadInitialData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final options =
          await _configService.loadSessionWizardOptions(userId: widget.userId);
      if (!mounted) return;
      setState(() {
        _wizardOptions = options;
        _efectores = options.efectores;
        _encounterClasses = options.encounterClasses;
        _efectoresConProblemas = options.efectoresConProblemas;
        _isLoading = false;
      });

      if (_efectores.length == 1) {
        _selectedEfector = _efectores.first;
        _loadServicios(_efectores.first.id);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = userFriendlyErrorMessage(e);
        _isLoading = false;
      });
    }
  }

  Future<void> _loadServicios(int efectorId) async {
    setState(() {
      _isLoading = true;
      _servicios = [];
      _selectedServicio = null;
    });

    try {
      final opts = _wizardOptions;
      if (opts == null) {
        throw Exception('Opciones de sesión no cargadas');
      }
      final servicios = _configService.serviciosParaEfector(efectorId, opts);
      if (!mounted) return;
      setState(() {
        _servicios = servicios;
        _isLoading = false;
      });

      if (_servicios.length == 1) {
        _selectedServicio = _servicios.first;
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = userFriendlyErrorMessage(e);
        _isLoading = false;
      });
    }
  }

  void _selectEfector(Efector efector) {
    setState(() {
      _selectedEfector = efector;
    });
    _loadServicios(efector.id);
  }

  void _selectServicio(Servicio servicio) {
    setState(() {
      _selectedServicio = servicio;
    });
  }

  void _selectEncounterClass(EncounterClass encounterClass) {
    setState(() {
      _selectedEncounterClass = encounterClass;
    });
  }

  void _nextStep() {
    if (_currentStep == 0 && _selectedEfector == null) return;
    if (_currentStep == 1 && _selectedServicio == null) return;
    if (_currentStep == 2 && _selectedEncounterClass == null) return;

    if (_currentStep < 2) {
      _pageController.nextPage(
        duration: BioMotion.normal,
        curve: BioMotion.standard,
      );
      setState(() {
        _currentStep++;
      });
    } else {
      _saveConfiguration();
    }
  }

  void _previousStep() {
    if (_currentStep > 0) {
      _pageController.previousPage(
        duration: BioMotion.normal,
        curve: BioMotion.standard,
      );
      setState(() {
        _currentStep--;
      });
    }
  }

  Future<void> _saveConfiguration() async {
    if (_selectedEfector == null ||
        _selectedServicio == null ||
        _selectedEncounterClass == null) {
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final sessionConfig = await _configService.setSession(
        efectorId: _selectedEfector!.id,
        servicioId: _selectedServicio!.id,
        encounterClass: _selectedEncounterClass!.code,
        userId: widget.userId,
      );

      final sessionToken = sessionConfig.contextToken;
      if (sessionToken == null || sessionToken.isEmpty) {
        throw Exception(
          'El servidor no devolvió token de contexto operativo. '
          'No se puede usar la app sin completar efector, servicio y área.',
        );
      }

      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt('efector_id', sessionConfig.efector.id);
      await prefs.setString('efector_nombre', sessionConfig.efector.nombre);
      await prefs.setInt('servicio_id', sessionConfig.servicio.id);
      await prefs.setString('servicio_nombre', sessionConfig.servicio.nombre);
      await prefs.setString(
          'encounter_class', sessionConfig.encounterClass.code);
      await prefs.setString(
          'encounter_class_label', sessionConfig.encounterClass.label);
      await prefs.setInt('id_profesional_efector_servicio',
          sessionConfig.idProfesionalEfectorServicio);
      await prefs.setBool('config_completed', true);
      await prefs.setString('auth_token', sessionToken);

      if (mounted) {
        await maybeOfferPersonalsaludBiometricEnrollment(context: context);
        if (!mounted) return;
        navigatorKey.currentState?.pushReplacement(
          MaterialPageRoute(
            builder: (_) => wrapPersonalsaludAuthenticatedShell(
              child: MainScreen(
                userId: widget.userId,
                userName: widget.userName,
                authToken: sessionToken,
                idProfesionalEfectorServicio:
                    sessionConfig.idProfesionalEfectorServicio.toString(),
              ),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Error al guardar configuración: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  Future<void> _confirmarLogout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('¿Seguro que querés salir?'),
        actions: [
          BioButton(
            label: 'Cancelar',
            intent: UiIntent.neutral,
            variant: BioButtonVariant.soft,
            size: BioButtonSize.sm,
            onPressed: () => Navigator.pop(dialogContext, false),
          ),
          BioButton.danger(
            label: 'Cerrar sesión',
            size: BioButtonSize.sm,
            onPressed: () => Navigator.pop(dialogContext, true),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    await PersonalsaludSessionPrefs.clearOnLogout();
    ClientDiagnosticApi.bindSession(authToken: null, appClient: 'bioenlace-personalsalud');

    navigatorKey.currentState?.pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => buildPersonalsaludLoginScreen(
          onLoginSuccess: navigatePersonalsaludAfterLogin,
        ),
      ),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Configuración inicial'),
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(
              horizontal: BioSpacing.lg,
              vertical: BioSpacing.md,
            ),
            decoration: BoxDecoration(
              color: tokens.paperSurface,
              border: BioBorder.bottom(BorderWidth.thin, tokens.paperBorderDefault),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _buildStepIndicator(0, 'Efector'),
                _buildStepConnector(connectedDone: _currentStep >= 1),
                _buildStepIndicator(1, 'Servicio'),
                _buildStepConnector(connectedDone: _currentStep >= 2),
                _buildStepIndicator(2, 'Área'),
              ],
            ),
          ),
          Expanded(
            child: _isLoading && _efectores.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : _errorMessage.isNotEmpty
                    ? Center(
                        child: Padding(
                          padding: BioSpacing.pageAll,
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              BioAlert.danger(message: _errorMessage),
                              BioSpacing.gapH(BioSpacing.lg),
                              BioButton.primary(
                                label: 'Reintentar',
                                icon: Icons.refresh,
                                onPressed: _loadInitialData,
                              ),
                            ],
                          ),
                        ),
                      )
                    : PageView(
                        controller: _pageController,
                        physics: const NeverScrollableScrollPhysics(),
                        children: [
                          _buildEfectorStep(),
                          _buildServicioStep(),
                          _buildEncounterClassStep(),
                        ],
                      ),
          ),
          SafeArea(
            top: false,
            child: Container(
              padding: const EdgeInsets.all(BioSpacing.lg),
              decoration: BoxDecoration(
                color: tokens.paperSurface,
                border: BioBorder.top(BorderWidth.thin, tokens.paperBorderDefault),
              ),
              child: Row(
                children: [
                  BioButton(
                    label: 'Cerrar sesión',
                    intent: UiIntent.danger,
                    variant: BioButtonVariant.soft,
                    icon: Icons.logout,
                    size: BioButtonSize.sm,
                    onPressed: _isLoading && _currentStep == 2 ? null : _confirmarLogout,
                  ),
                  if (_currentStep > 0) ...[
                    BioSpacing.gapW(BioSpacing.sm),
                    BioButton.outlinePrimary(
                      label: 'Anterior',
                      icon: Icons.arrow_back,
                      size: BioButtonSize.sm,
                      onPressed: _previousStep,
                    ),
                  ],
                  const Spacer(),
                  BioButton.primary(
                    label: _currentStep == 2 ? 'Finalizar' : 'Siguiente',
                    icon: _currentStep == 2 ? Icons.check : Icons.arrow_forward,
                    onPressed: _canProceed() ? _nextStep : null,
                    loading: _isLoading && _currentStep == 2,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStepIndicator(int step, String label) {
    final tokens = context.bio;
    final isActive = step == _currentStep;
    final isCompleted = step < _currentStep;
    final primary = IntentPalette.of(UiIntent.primary);
    final success = IntentPalette.of(UiIntent.success);

    final Color bg = isCompleted
        ? success.base
        : isActive
            ? primary.base
            : tokens.paperSurfaceSunken;
    final Color fg = isCompleted || isActive ? Colors.white : tokens.textMuted;
    final Color labelColor = isActive ? primary.base : tokens.textMuted;

    return Column(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: bg,
            border: Border.all(
              color: isActive || isCompleted ? bg : tokens.paperBorderDefault,
              width: BorderWidth.thin,
            ),
          ),
          child: Center(
            child: isCompleted
                ? const Icon(Icons.check, color: Colors.white, size: 20)
                : Text(
                    '${step + 1}',
                    style: BioTypography.title.copyWith(color: fg),
                  ),
          ),
        ),
        BioSpacing.gapH(BioSpacing.xs),
        Text(
          label,
          style: BioTypography.caption.copyWith(
            color: labelColor,
            fontWeight: isActive ? FontWeight.w600 : FontWeight.w400,
          ),
        ),
      ],
    );
  }

  Widget _buildStepConnector({required bool connectedDone}) {
    final tokens = context.bio;
    final success = IntentPalette.of(UiIntent.success);
    return Expanded(
      child: Container(
        height: 2,
        margin: const EdgeInsets.symmetric(horizontal: BioSpacing.sm),
        color: connectedDone ? success.base : tokens.paperDividerDefault,
      ),
    );
  }

  bool _canProceed() {
    if (_isLoading) return false;
    switch (_currentStep) {
      case 0:
        return _selectedEfector != null;
      case 1:
        return _selectedServicio != null;
      case 2:
        return _selectedEncounterClass != null;
      default:
        return false;
    }
  }

  Widget _buildEfectorStep() {
    return SingleChildScrollView(
      padding: BioSpacing.pageAll,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Seleccioná el efector', style: BioTypography.h2),
          BioSpacing.gapH(BioSpacing.sm),
          if (_efectoresConProblemas.isNotEmpty) ...[
            BioAlert.warning(
              title: 'Algunos efectores requieren configuración',
              message: _efectoresConProblemas.map((p) {
                var t = p.message;
                if (p.nombre != null && p.nombre!.isNotEmpty) {
                  t += ' (${p.nombre})';
                }
                if (p.contactosNombreCompleto.isNotEmpty) {
                  t += '\nContacto: ${p.contactosNombreCompleto.join(', ')}';
                }
                return t;
              }).join('\n\n'),
            ),
            BioSpacing.gapH(BioSpacing.lg),
          ],
          if (_isLoading)
            const Center(child: CircularProgressIndicator())
          else
            ..._efectores.map(
              (efector) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.md),
                child: _buildSelectionCard(
                  title: efector.nombre,
                  isSelected: _selectedEfector?.id == efector.id,
                  onTap: () => _selectEfector(efector),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildServicioStep() {
    return SingleChildScrollView(
      padding: BioSpacing.pageAll,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Seleccioná el servicio', style: BioTypography.h2),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Elegí el servicio donde vas a trabajar.',
            style: BioTypography.bodySm,
          ),
          BioSpacing.gapH(BioSpacing.xl),
          if (_isLoading)
            const Center(child: CircularProgressIndicator())
          else if (_servicios.isEmpty)
            BioAlert.info(message: 'No hay servicios disponibles')
          else
            ..._servicios.map(
              (servicio) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.md),
                child: _buildSelectionCard(
                  title: servicio.nombre,
                  isSelected: _selectedServicio?.id == servicio.id,
                  onTap: () => _selectServicio(servicio),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildEncounterClassStep() {
    return SingleChildScrollView(
      padding: BioSpacing.pageAll,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Seleccioná el área', style: BioTypography.h2),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Elegí el tipo de atención (ambulatorio, guardia, internación, etc.).',
            style: BioTypography.bodySm,
          ),
          BioSpacing.gapH(BioSpacing.xl),
          ..._encounterClasses.map(
            (encounterClass) => Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.md),
              child: _buildSelectionCard(
                title: encounterClass.label,
                subtitle: encounterClass.code,
                isSelected:
                    _selectedEncounterClass?.code == encounterClass.code,
                onTap: () => _selectEncounterClass(encounterClass),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSelectionCard({
    required String title,
    String? subtitle,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary);
    final card = BioCard(
      padding: const EdgeInsets.all(BioSpacing.lg),
      onTap: onTap,
      color: isSelected ? primary.softBg : tokens.paperSurface,
      border: isSelected
          ? Border.all(color: primary.base, width: BorderWidth.medium)
          : BioBorder.paperDefault,
      child: Row(
        children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: isSelected ? primary.base : tokens.paperBorderDefault,
                width: BorderWidth.medium,
              ),
              color: isSelected ? primary.base : Colors.transparent,
            ),
            child: isSelected
                ? const Icon(Icons.check, color: Colors.white, size: 16)
                : null,
          ),
          BioSpacing.gapW(BioSpacing.lg),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: BioTypography.title.copyWith(
                    fontWeight:
                        isSelected ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
                if (subtitle != null) ...[
                  BioSpacing.gapH(BioSpacing.xs),
                  Text(subtitle, style: BioTypography.caption),
                ],
              ],
            ),
          ),
        ],
      ),
    );
    return card;
  }
}
