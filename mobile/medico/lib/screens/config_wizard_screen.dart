// lib/screens/config_wizard_screen.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../services/config_service.dart';
import '../main.dart';
import 'main_screen.dart';

class ConfigWizardScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String authToken;

  const ConfigWizardScreen({
    Key? key,
    required this.userId,
    required this.userName,
    required this.authToken,
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

  // Datos disponibles
  List<Efector> _efectores = [];
  List<Servicio> _servicios = [];
  List<EncounterClass> _encounterClasses = [];

  // Selecciones
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
      // Cargar efectores y encounter classes en paralelo
      // Pasar userId para desarrollo/simulación
      final efectoresFuture = _configService.getEfectores(userId: widget.userId);
      final encounterClassesFuture = _configService.getEncounterClasses();

      final results = await Future.wait([efectoresFuture, encounterClassesFuture]);

      setState(() {
        _efectores = results[0] as List<Efector>;
        _encounterClasses = results[1] as List<EncounterClass>;
        _isLoading = false;
      });

      // Si solo hay un efector, seleccionarlo automáticamente
      if (_efectores.length == 1) {
        _selectedEfector = _efectores.first;
        _loadServicios(_efectores.first.id);
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar datos: ${e.toString()}';
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
      // Pasar userId para desarrollo/simulación
      final servicios = await _configService.getServicios(efectorId, userId: widget.userId);
      setState(() {
        _servicios = servicios;
        _isLoading = false;
      });

      // Si solo hay un servicio, seleccionarlo automáticamente
      if (_servicios.length == 1) {
        _selectedServicio = _servicios.first;
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar servicios: ${e.toString()}';
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
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
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
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
      setState(() {
        _currentStep--;
      });
    }
  }

  Future<void> _saveConfiguration() async {
    if (_selectedEfector == null || _selectedServicio == null || _selectedEncounterClass == null) {
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

      // Guardar en SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt('efector_id', sessionConfig.efector.id);
      await prefs.setString('efector_nombre', sessionConfig.efector.nombre);
      await prefs.setInt('servicio_id', sessionConfig.servicio.id);
      await prefs.setString('servicio_nombre', sessionConfig.servicio.nombre);
      await prefs.setString('encounter_class', sessionConfig.encounterClass.code);
      await prefs.setString('encounter_class_label', sessionConfig.encounterClass.label);
      await prefs.setInt('rrhh_id', sessionConfig.rrhhId);
      await prefs.setBool('config_completed', true);

      // Navegar al MainScreen
      if (mounted) {
        navigatorKey.currentState?.pushReplacement(
          MaterialPageRoute(
            builder: (_) => MainScreen(
              userId: widget.userId,
              userName: widget.userName,
              authToken: widget.authToken,
              rrhhId: sessionConfig.rrhhId.toString(),
            ),
          ),
        );
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al guardar configuración: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Configuración Inicial',
          style: AppTheme.h2Style.copyWith(color: Colors.white),
        ),
        backgroundColor: AppTheme.primaryColor,
        elevation: 0,
      ),
      body: Container(
        color: AppTheme.backgroundColor,
        child: Column(
          children: [
            // Indicador de pasos
            Container(
              padding: const EdgeInsets.all(16.0),
              color: Colors.white,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _buildStepIndicator(0, 'Efector'),
                  _buildStepConnector(),
                  _buildStepIndicator(1, 'Servicio'),
                  _buildStepConnector(),
                  _buildStepIndicator(2, 'Área'),
                ],
              ),
            ),
            
            // Contenido
            Expanded(
              child: _isLoading && _efectores.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : _errorMessage.isNotEmpty
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24.0),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.error_outline,
                                  size: 48,
                                  color: AppTheme.dangerColor,
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  _errorMessage,
                                  style: AppTheme.subTitleStyle,
                                  textAlign: TextAlign.center,
                                ),
                                const SizedBox(height: 16),
                                ElevatedButton(
                                  onPressed: _loadInitialData,
                                  child: const Text('Reintentar'),
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
            
            // Botones de navegación
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.all(16.0),
                color: Colors.white,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    if (_currentStep > 0)
                      ElevatedButton(
                        onPressed: _previousStep,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: AppTheme.secondaryColor,
                          side: const BorderSide(color: AppTheme.secondaryColor),
                        ),
                        child: const Text('Anterior'),
                      )
                    else
                      const SizedBox(),
                    ElevatedButton(
                      onPressed: _canProceed() ? _nextStep : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryColor,
                        foregroundColor: Colors.white,
                      ),
                      child: Text(_currentStep == 2 ? 'Finalizar' : 'Siguiente'),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStepIndicator(int step, String label) {
    final isActive = step == _currentStep;
    final isCompleted = step < _currentStep;
    
    return Column(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isCompleted
                ? AppTheme.successColor
                : isActive
                    ? AppTheme.primaryColor
                    : AppTheme.secondaryColor,
          ),
          child: Center(
            child: isCompleted
                ? const Icon(Icons.check, color: Colors.white, size: 20)
                : Text(
                    '${step + 1}',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                  ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: AppTheme.subTitleStyle.copyWith(
            color: isActive ? AppTheme.primaryColor : AppTheme.secondaryColor,
            fontWeight: isActive ? FontWeight.bold : FontWeight.normal,
          ),
        ),
      ],
    );
  }

  Widget _buildStepConnector() {
    return Expanded(
      child: Container(
        height: 2,
        margin: const EdgeInsets.symmetric(horizontal: 8),
        color: _currentStep > 0 ? AppTheme.successColor : AppTheme.secondaryColor,
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
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Seleccione el Efector',
            style: AppTheme.h2Style,
          ),
          const SizedBox(height: 8),
          Text(
            'Elija el efector donde trabajará',
            style: AppTheme.subTitleStyle,
          ),
          const SizedBox(height: 24),
          if (_isLoading)
            const Center(child: CircularProgressIndicator())
          else
            ..._efectores.map((efector) => Padding(
                  padding: const EdgeInsets.only(bottom: 12.0),
                  child: _buildSelectionCard(
                    title: efector.nombre,
                    isSelected: _selectedEfector?.id == efector.id,
                    onTap: () => _selectEfector(efector),
                  ),
                )),
        ],
      ),
    );
  }

  Widget _buildServicioStep() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Seleccione el Servicio',
            style: AppTheme.h2Style,
          ),
          const SizedBox(height: 8),
          Text(
            'Elija el servicio donde trabajará',
            style: AppTheme.subTitleStyle,
          ),
          const SizedBox(height: 24),
          if (_isLoading)
            const Center(child: CircularProgressIndicator())
          else if (_servicios.isEmpty)
            Center(
              child: Text(
                'No hay servicios disponibles',
                style: AppTheme.subTitleStyle,
              ),
            )
          else
            ..._servicios.map((servicio) => Padding(
                  padding: const EdgeInsets.only(bottom: 12.0),
                  child: _buildSelectionCard(
                    title: servicio.nombre,
                    isSelected: _selectedServicio?.id == servicio.id,
                    onTap: () => _selectServicio(servicio),
                  ),
                )),
        ],
      ),
    );
  }

  Widget _buildEncounterClassStep() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Seleccione el Área',
            style: AppTheme.h2Style,
          ),
          const SizedBox(height: 8),
          Text(
            'Elija el área donde trabajará',
            style: AppTheme.subTitleStyle,
          ),
          const SizedBox(height: 24),
          ..._encounterClasses.map((encounterClass) => Padding(
                padding: const EdgeInsets.only(bottom: 12.0),
                child: _buildSelectionCard(
                  title: encounterClass.label,
                  subtitle: encounterClass.code,
                  isSelected: _selectedEncounterClass?.code == encounterClass.code,
                  onTap: () => _selectEncounterClass(encounterClass),
                ),
              )),
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
    return Card(
      elevation: isSelected ? 4 : 2,
      color: isSelected ? AppTheme.primaryColor.withOpacity(0.1) : Colors.white,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Row(
            children: [
              Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: isSelected ? AppTheme.primaryColor : AppTheme.secondaryColor,
                    width: 2,
                  ),
                  color: isSelected ? AppTheme.primaryColor : Colors.transparent,
                ),
                child: isSelected
                    ? const Icon(Icons.check, color: Colors.white, size: 16)
                    : null,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: AppTheme.h5Style.copyWith(
                        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        style: AppTheme.subTitleStyle.copyWith(fontSize: 12),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

