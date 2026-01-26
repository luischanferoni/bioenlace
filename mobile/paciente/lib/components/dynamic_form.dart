import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared/shared.dart';
import 'searchable_card_selector.dart';

/// Widget para generar formularios dinámicos basados en form_config del backend
class DynamicForm extends StatefulWidget {
  final Map<String, dynamic> formConfig;
  final List<dynamic>? wizardSteps;
  final int? initialStep;
  final String? authToken;
  final Function(Map<String, dynamic>) onSubmit;
  final Function()? onCancel;

  const DynamicForm({
    Key? key,
    required this.formConfig,
    this.wizardSteps,
    this.initialStep,
    required this.onSubmit,
    this.onCancel,
    this.authToken,
  }) : super(key: key);

  @override
  _DynamicFormState createState() => _DynamicFormState();
}

class _DynamicFormState extends State<DynamicForm> {
  final Map<String, dynamic> _formValues = {};
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, List<Map<String, dynamic>>> _optionsCache = {};
  final Map<String, bool> _loadingOptions = {};
  final _formKey = GlobalKey<FormState>();
  int _currentStep = 0;

  @override
  void initState() {
    super.initState();
    _currentStep = widget.initialStep ?? 0;
    _initializeForm();
  }

  void _initializeForm() {
    final fields = widget.formConfig['fields'] as List<dynamic>? ?? [];
    
    for (var field in fields) {
      final fieldName = field['name'] as String;
      final defaultValue = field['value'];
      
      // Inicializar controlador
      _controllers[fieldName] = TextEditingController(
        text: defaultValue?.toString() ?? '',
      );
      
      // Inicializar valor
      if (defaultValue != null) {
        _formValues[fieldName] = defaultValue;
      }
      
      // Para campos select, cargar opciones directamente del JSON si están disponibles
      if (field['type'] == 'select' && field['options'] != null) {
        final options = field['options'] as List<dynamic>? ?? [];
        _optionsCache[fieldName] = options.map((option) {
          if (option is Map) {
            return Map<String, dynamic>.from(option);
          }
          return {'id': option.toString(), 'name': option.toString()};
        }).toList();
      }
      
      // NO cargar opciones automáticamente para autocomplete
      // Se cargarán solo cuando el usuario escriba al menos 3 caracteres
    }
  }


  Widget _buildField(Map<String, dynamic> field) {
    final fieldName = field['name'] as String;
    final label = field['label'] as String? ?? fieldName;
    final type = field['type'] as String? ?? 'text';
    final required = field['required'] as bool? ?? false;
    final description = field['description'] as String?;
    final dependsOn = field['depends_on'] as String?;
    final message = field['message'] as String?;

    // Verificar dependencias
    if (dependsOn != null && !_formValues.containsKey(dependsOn)) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8.0),
        child: Text(
          message ?? 'Primero debe completar: $dependsOn',
          style: TextStyle(color: Colors.orange[700], fontSize: 12),
        ),
      );
    }

    // Si depende de otro campo y es select, las opciones ya deberían venir del backend
    // Si es autocomplete, se cargarán cuando el usuario escriba
    // No hacer nada aquí para evitar cargas automáticas

    switch (type) {
      case 'autocomplete':
        // Si el campo tiene un endpoint y es de tipo autocomplete, usar el selector con cards
        final endpoint = field['endpoint'] as String?;
        if (endpoint != null) {
          return _buildSearchableCardSelectorField(field, label, required, description);
        }
        return _buildAutocompleteField(field, label, required, description);
      case 'select':
        return _buildSelectField(field, label, required, description);
      case 'date':
        return _buildDateField(field, label, required, description);
      case 'number':
        return _buildNumberField(field, label, required, description);
      case 'text':
      default:
        return _buildTextField(field, label, required, description);
    }
  }

  Widget _buildTextField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: TextFormField(
        controller: _controllers[fieldName],
        decoration: InputDecoration(
          labelText: label + (required ? ' *' : ''),
          hintText: description,
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        validator: required
            ? (value) => value == null || value.isEmpty ? 'Este campo es requerido' : null
            : null,
        onChanged: (value) {
          _formValues[fieldName] = value;
        },
      ),
    );
  }

  Widget _buildNumberField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: TextFormField(
        controller: _controllers[fieldName],
        keyboardType: TextInputType.number,
        decoration: InputDecoration(
          labelText: label + (required ? ' *' : ''),
          hintText: description,
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        validator: required
            ? (value) => value == null || value.isEmpty ? 'Este campo es requerido' : null
            : null,
        onChanged: (value) {
          _formValues[fieldName] = int.tryParse(value) ?? value;
        },
      ),
    );
  }

  Widget _buildDateField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: InkWell(
        onTap: () async {
          final date = await showDatePicker(
            context: context,
            initialDate: DateTime.now(),
            firstDate: DateTime(1900),
            lastDate: DateTime(2100),
          );
          if (date != null) {
            final formattedDate = '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
            _controllers[fieldName]?.text = formattedDate;
            _formValues[fieldName] = formattedDate;
            setState(() {});
          }
        },
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label + (required ? ' *' : ''),
            hintText: description ?? 'Seleccionar fecha',
            filled: true,
            fillColor: Colors.white,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
            ),
            suffixIcon: Icon(Icons.calendar_today),
          ),
          child: Text(
            _controllers[fieldName]?.text ?? '',
            style: TextStyle(fontSize: 16),
          ),
        ),
      ),
    );
  }

  Widget _buildSelectField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    final options = _optionsCache[fieldName] ?? [];
    
    // Si no hay opciones y hay endpoint, no mostrar el campo (debería usar autocomplete en su lugar)
    if (options.isEmpty && field['endpoint'] != null) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8.0),
        child: Text(
          'Este campo requiere opciones que se cargarán dinámicamente. Por favor, use autocomplete en su lugar.',
          style: TextStyle(color: Colors.orange[700], fontSize: 12),
        ),
      );
    }
    
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: DropdownButtonFormField<String>(
        decoration: InputDecoration(
          labelText: label + (required ? ' *' : ''),
          hintText: description,
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        value: _formValues[fieldName]?.toString(),
        items: options.isEmpty 
            ? [DropdownMenuItem<String>(value: null, child: Text('Sin opciones disponibles'))]
            : options.map((option) {
                final value = option['id']?.toString() ?? option['value']?.toString() ?? option.toString();
                final display = option['name']?.toString() ?? option['label']?.toString() ?? value;
                return DropdownMenuItem<String>(
                  value: value,
                  child: Text(display),
                );
              }).toList(),
        onChanged: (value) {
          setState(() {
            _formValues[fieldName] = value;
          });
        },
        validator: required
            ? (value) => value == null || value.isEmpty ? 'Este campo es requerido' : null
            : null,
      ),
    );
  }

  Widget _buildSearchableCardSelectorField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    final endpoint = field['endpoint'] as String?;
    final params = field['params'] as Map<String, dynamic>? ?? {};
    
    // Si depende de otro campo, agregar su valor a los params
    final dependsOn = field['depends_on'] as String?;
    if (dependsOn != null && _formValues.containsKey(dependsOn)) {
      params[dependsOn] = _formValues[dependsOn];
    }
    
    // Determinar icono según el tipo de campo
    IconData? icon;
    String? searchHint;
    String? emptyMessage;
    String? noResultsMessage;
    
    if (fieldName == 'id_efector' || fieldName.contains('efector')) {
      icon = Icons.local_hospital;
      searchHint = 'Buscar efector...';
      emptyMessage = 'No hay efectores disponibles';
      noResultsMessage = 'No se encontraron efectores';
    } else if (fieldName == 'id_rr_hh' || fieldName.contains('rrhh') || fieldName.contains('profesional')) {
      icon = Icons.person;
      searchHint = 'Buscar profesional...';
      emptyMessage = 'No hay profesionales disponibles';
      noResultsMessage = 'No se encontraron profesionales';
    } else if (fieldName.contains('servicio')) {
      icon = Icons.medical_services;
      searchHint = 'Buscar servicio...';
      emptyMessage = 'No hay servicios disponibles';
      noResultsMessage = 'No se encontraron servicios';
    } else {
      icon = Icons.article;
      searchHint = 'Buscar...';
      emptyMessage = 'No hay opciones disponibles';
      noResultsMessage = 'No se encontraron resultados';
    }
    
    final autoLoad = field['auto_load'] as bool? ?? false;
    
    // Crear una key única basada en la dependencia para forzar reconstrucción cuando cambia
    final dependencyKey = dependsOn != null && _formValues.containsKey(dependsOn)
        ? '${fieldName}_${_formValues[dependsOn]}'
        : fieldName;
    
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: SearchableCardSelector(
        key: ValueKey(dependencyKey),
        label: label,
        required: required,
        description: description,
        endpoint: endpoint,
        params: params,
        authToken: widget.authToken,
        initialValue: _formValues[fieldName]?.toString(),
        icon: icon,
        searchHint: searchHint,
        emptyMessage: emptyMessage,
        noResultsMessage: noResultsMessage,
        autoLoad: autoLoad,
        onChanged: (selectedId) {
          setState(() {
            _formValues[fieldName] = selectedId;
          });
        },
      ),
    );
  }

  Widget _buildAutocompleteField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    final endpoint = field['endpoint'] as String?;
    final isLoading = _loadingOptions[fieldName] == true;
    final options = _optionsCache[fieldName] ?? [];
    
    if (isLoading) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8.0),
        child: Row(
          children: [
            SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
            SizedBox(width: 8),
            Text('Buscando...'),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: Autocomplete<Map<String, dynamic>>(
        optionsBuilder: (TextEditingValue textEditingValue) async {
          final query = textEditingValue.text.trim();
          
          // Solo buscar si hay al menos 3 caracteres
          if (query.length < 3) {
            return const Iterable<Map<String, dynamic>>.empty();
          }
          
          // Si hay endpoint, cargar opciones desde el servidor
          if (endpoint != null) {
            // Verificar si ya tenemos opciones cargadas para esta consulta
            // Si no, cargar desde el servidor
            await _loadOptionsForAutocomplete(fieldName, field, query);
          }
          
          // Filtrar opciones locales si existen
          if (options.isNotEmpty) {
            return options.where((option) {
              final display = option['name']?.toString() ?? 
                             option['label']?.toString() ?? 
                             option.toString();
              return display.toLowerCase().contains(query.toLowerCase());
            });
          }
          
          return const Iterable<Map<String, dynamic>>.empty();
        },
        displayStringForOption: (option) {
          return option['name']?.toString() ?? 
                 option['label']?.toString() ?? 
                 option.toString();
        },
        onSelected: (option) {
          final value = option['id']?.toString() ?? option['value']?.toString();
          _controllers[fieldName]?.text = option['name']?.toString() ?? option['label']?.toString() ?? '';
          _formValues[fieldName] = value;
        },
        fieldViewBuilder: (context, controller, focusNode, onFieldSubmitted) {
          return TextFormField(
            controller: controller,
            focusNode: focusNode,
            decoration: InputDecoration(
              labelText: label + (required ? ' *' : ''),
              hintText: description ?? 'Escribe al menos 3 caracteres para buscar...',
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            validator: required
                ? (value) => value == null || value.isEmpty ? 'Este campo es requerido' : null
                : null,
          );
        },
      ),
    );
  }
  
  Future<void> _loadOptionsForAutocomplete(String fieldName, Map<String, dynamic> field, String query) async {
    if (_loadingOptions[fieldName] == true) return;
    
    setState(() {
      _loadingOptions[fieldName] = true;
    });

    try {
      final endpoint = field['endpoint'] as String;
      final params = field['params'] as Map<String, dynamic>? ?? {};
      
      // Agregar el query como parámetro 'q'
      params['q'] = query;
      
      // Si depende de otro campo, agregar su valor a los params
      final dependsOn = field['depends_on'] as String?;
      if (dependsOn != null && _formValues.containsKey(dependsOn)) {
        params[dependsOn] = _formValues[dependsOn];
      }
      
      // Construir URL con parámetros
      final uri = Uri.parse('${AppConfig.apiUrl}$endpoint');
      final uriWithParams = uri.replace(queryParameters: params.map((k, v) => MapEntry(k, v.toString())));
      
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };
      
      if (widget.authToken != null) {
        headers['Authorization'] = 'Bearer ${widget.authToken}';
      }

      final response = await http.get(
        uriWithParams,
        headers: headers,
      ).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final items = data['results'] ?? data['data'] ?? data['items'] ?? data;
        
        List<Map<String, dynamic>> options = [];
        if (items is List) {
          options = items.map((item) {
            if (item is Map) {
              // Normalizar formato: puede venir como {'id': x, 'text': y} o {'id': x, 'name': y}
              final id = item['id']?.toString() ?? item['value']?.toString();
              final name = item['text']?.toString() ?? item['name']?.toString() ?? item['label']?.toString() ?? id;
              return {
                'id': id,
                'name': name,
              };
            }
            return {'id': item.toString(), 'name': item.toString()};
          }).toList();
        }
        
        setState(() {
          _optionsCache[fieldName] = options;
          _loadingOptions[fieldName] = false;
        });
      } else {
        setState(() {
          _loadingOptions[fieldName] = false;
        });
      }
    } catch (e) {
      setState(() {
        _loadingOptions[fieldName] = false;
      });
    }
  }

  List<Map<String, dynamic>> _getCurrentStepFields() {
    // Si hay wizard_steps, usar solo los campos del paso actual
    if (widget.wizardSteps != null && widget.wizardSteps!.isNotEmpty) {
      if (_currentStep < widget.wizardSteps!.length) {
        final currentStepData = widget.wizardSteps![_currentStep] as Map<String, dynamic>;
        final stepFields = currentStepData['fields'] as List<dynamic>? ?? [];
        
        // Obtener todos los campos del formConfig para tener la configuración completa
        final allFields = widget.formConfig['fields'] as List<dynamic>? ?? [];
        final fieldsMap = <String, Map<String, dynamic>>{};
        
        for (var field in allFields) {
          final fieldMap = Map<String, dynamic>.from(field);
          fieldsMap[fieldMap['name'] as String] = fieldMap;
        }
        
        // Retornar solo los campos del paso actual con su configuración completa
        return stepFields.map((field) {
          if (field is Map) {
            return Map<String, dynamic>.from(field);
          } else if (field is String) {
            return fieldsMap[field] ?? {'name': field};
          }
          return {'name': field.toString()};
        }).toList();
      }
    }
    
    // Si no hay wizard_steps, mostrar todos los campos (comportamiento anterior)
    final fields = widget.formConfig['fields'] as List<dynamic>? ?? [];
    return fields.map((field) => Map<String, dynamic>.from(field)).toList();
  }

  bool _canGoToNextStep() {
    if (widget.wizardSteps == null || widget.wizardSteps!.isEmpty) {
      return false;
    }
    return _currentStep < widget.wizardSteps!.length - 1;
  }

  bool _canGoToPreviousStep() {
    return _currentStep > 0;
  }

  void _goToNextStep() {
    if (_formKey.currentState?.validate() ?? false) {
      if (_canGoToNextStep()) {
        setState(() {
          _currentStep++;
        });
      }
    }
  }

  void _goToPreviousStep() {
    if (_canGoToPreviousStep()) {
      setState(() {
        _currentStep--;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final currentFields = _getCurrentStepFields();
    final submitLabel = widget.formConfig['ui']?['submit_label'] as String? ?? 
                       widget.formConfig['submit_label'] as String? ?? 'Enviar';
    final cancelLabel = widget.formConfig['ui']?['cancel_label'] as String? ?? 'Cancelar';
    
    final isWizard = widget.wizardSteps != null && widget.wizardSteps!.isNotEmpty;
    final isLastStep = isWizard && _currentStep == (widget.wizardSteps!.length - 1);
    final currentStepData = isWizard && _currentStep < widget.wizardSteps!.length
        ? widget.wizardSteps![_currentStep] as Map<String, dynamic>
        : null;
    final stepTitle = currentStepData?['title'] as String?;

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Mostrar título del paso si es wizard
          if (isWizard && stepTitle != null) ...[
            Padding(
              padding: const EdgeInsets.only(bottom: 16.0),
              child: Text(
                stepTitle,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            // Indicador de progreso del wizard
            Row(
              children: List.generate(
                widget.wizardSteps!.length,
                (index) => Expanded(
                  child: Container(
                    height: 4,
                    margin: EdgeInsets.symmetric(horizontal: 2),
                    decoration: BoxDecoration(
                      color: index <= _currentStep
                          ? Theme.of(context).primaryColor
                          : Colors.grey[300],
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          // Campos del paso actual
          ...currentFields.map((field) => _buildField(field)),
          const SizedBox(height: 16),
          // Botones de navegación
          Row(
            children: [
              if (widget.onCancel != null)
                Expanded(
                  child: OutlinedButton(
                    onPressed: widget.onCancel,
                    child: Text(cancelLabel),
                  ),
                ),
              if (widget.onCancel != null) const SizedBox(width: 8),
              // Botón "Anterior" si no es el primer paso del wizard
              if (isWizard && _canGoToPreviousStep()) ...[
                Expanded(
                  child: OutlinedButton(
                    onPressed: _goToPreviousStep,
                    child: Text('Anterior'),
                  ),
                ),
                const SizedBox(width: 8),
              ],
              Expanded(
                child: ElevatedButton(
                  onPressed: () {
                    if (_formKey.currentState?.validate() ?? false) {
                      if (isWizard && !isLastStep) {
                        _goToNextStep();
                      } else {
                        widget.onSubmit(_formValues);
                      }
                    }
                  },
                  child: Text(isWizard && !isLastStep ? 'Siguiente' : submitLabel),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _controllers.values.forEach((controller) => controller.dispose());
    super.dispose();
  }
}
