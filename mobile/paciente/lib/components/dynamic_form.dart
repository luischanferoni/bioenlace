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
  /// Cache de opciones cargadas por SearchableCardSelector (key = fieldName|dependencyKey) para no refetch al cambiar de paso.
  final Map<String, List<Map<String, dynamic>>> _selectorOptionsCache = {};
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
        // Convertir a string para campos select que esperan string como value
        if (field['type'] == 'select') {
          _formValues[fieldName] = defaultValue.toString();
        } else {
          _formValues[fieldName] = defaultValue;
        }
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

  /// Normaliza depends_on del JSON: puede ser un string (un campo) o una lista (varios).
  List<String> _getDependsOnList(Map<String, dynamic> field) {
    final raw = field['depends_on'];
    if (raw == null) return [];
    if (raw is String) return raw.isNotEmpty ? [raw] : [];
    if (raw is List) {
      return raw.map((e) => e?.toString() ?? '').where((s) => s.isNotEmpty).toList();
    }
    return [];
  }

  Widget _buildField(Map<String, dynamic> field) {
    final fieldName = field['name'] as String;
    final label = field['label'] as String? ?? fieldName;
    final type = field['type'] as String? ?? 'text';
    final required = field['required'] as bool? ?? false;
    final description = field['description'] as String?;
    final dependsOnList = _getDependsOnList(field);
    final message = field['message'] as String?;

    // Verificar dependencias (todas deben estar completas)
    final missingDeps = dependsOnList.where((d) {
      if (!_formValues.containsKey(d)) return true;
      final v = _formValues[d];
      return v == null || v.toString().trim() == '';
    }).toList();
    if (missingDeps.isNotEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8.0),
        child: Text(
          message ?? 'Primero debe completar: ${missingDeps.join(", ")}',
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
            builder: (context, child) {
              // Tema claro completo para todo el calendario (días, mes, año, cabecera)
              // así todos los textos son oscuros sobre fondo claro.
              final lightScheme = ColorScheme.light(
                surface: Colors.white,
                onSurface: Colors.black87,
                primary: Theme.of(context).colorScheme.primary,
                onPrimary: Colors.white,
              );
              return Theme(
                data: ThemeData.light().copyWith(
                  colorScheme: lightScheme,
                  dialogBackgroundColor: Colors.white,
                  textTheme: ThemeData.light().textTheme.apply(bodyColor: Colors.black87, displayColor: Colors.black87),
                ),
                child: child!,
              );
            },
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

  /// Resuelve el mapeo params (nombre_parametro_endpoint -> nombre_campo_formulario) desde el campo o desde formConfig.
  static Map<String, String>? _resolveParamsMapping(Map<String, dynamic> field, Map<String, dynamic> formConfig) {
    final rawParams = field['params'];

    if (rawParams is Map && rawParams.isNotEmpty) {
      return rawParams.map((k, v) => MapEntry(k.toString(), v?.toString() ?? ''));
    }
    // Fallback: buscar el campo por nombre en formConfig.fields (respuesta original del API)
    final name = field['name'] as String?;
    if (name == null) return null;
    final allFields = formConfig['fields'] as List<dynamic>? ?? [];
    for (var raw in allFields) {
      if (raw is! Map) continue;
      final rf = raw;
      if (rf['name']?.toString() == name) {
        final p = rf['params'];
        if (p is Map && p.isNotEmpty) {
          return p.map((k, v) => MapEntry(k.toString(), v?.toString() ?? ''));
        }
        break;
      }
    }
    return null;
  }

  /// Construye los query params para el endpoint a partir del mapeo definido en el JSON.
  /// params: { "nombre_parametro_endpoint": "nombre_campo_formulario" } → se envía nombre_parametro_endpoint = valor del campo.
  /// Si no hay params, se usa depends_on: se envía el nombre del campo como nombre del parámetro (retrocompatibilidad).
  Map<String, dynamic> _buildEndpointParams(Map<String, dynamic> field) {
    final paramsMapping = _resolveParamsMapping(field, widget.formConfig);
    final dependsOnList = _getDependsOnList(field);
    final result = <String, dynamic>{};

    if (paramsMapping != null && paramsMapping.isNotEmpty) {
      for (final entry in paramsMapping.entries) {
        final paramName = entry.key;
        final formFieldName = entry.value;
        if (formFieldName.isNotEmpty && _formValues.containsKey(formFieldName)) {
          final v = _formValues[formFieldName];
          if (v != null && v.toString().trim() != '') {
            result[paramName] = v;
          }
        }
      }
    } else {
      for (final dep in dependsOnList) {
        if (_formValues.containsKey(dep)) {
          final v = _formValues[dep];
          if (v != null && v.toString().trim() != '') {
            result[dep] = v;
          }
        }
      }
    }
    return result;
  }

  Widget _buildSearchableCardSelectorField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    final endpoint = field['endpoint'] as String?;
    final params = _buildEndpointParams(field);

    // Textos e icono desde el JSON; fallback genérico (app agnóstica)
    final icon = Icons.article;
    final searchHint = field['search_hint'] as String? ?? field['searchHint'] as String? ?? 'Buscar...';
    final emptyMessage = field['empty_message'] as String? ?? field['emptyMessage'] as String? ?? 'No hay opciones disponibles';
    final noResultsMessage = field['no_results_message'] as String? ?? field['noResultsMessage'] as String? ?? 'No se encontraron resultados';

    final autoLoad = field['auto_load'] as bool? ?? false;
    final showSearch = field['show_search'] as bool? ?? true;

    // Key única cuando cambian los valores de los campos de los que dependen (nombres vienen del JSON)
    final rawParamsForKey = field['params'];
    final paramsMapForKey = rawParamsForKey is Map ? rawParamsForKey : null;
    final hasParamsMapping = paramsMapForKey != null && paramsMapForKey.isNotEmpty;
    final dependentFieldNames = hasParamsMapping
        ? paramsMapForKey.values.map((v) => v?.toString() ?? '').where((s) => s.isNotEmpty).toSet().toList()
        : _getDependsOnList(field);
    final dependencyKey = dependentFieldNames.isEmpty
        ? fieldName
        : '${fieldName}_${dependentFieldNames.map((f) => _formValues[f]?.toString() ?? '').join('_')}';
    final selectorCacheKey = '${fieldName}|$dependencyKey';
    final cachedOptions = _selectorOptionsCache[selectorCacheKey];

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
        showSearch: showSearch,
        initialOptions: cachedOptions,
        onOptionsLoaded: (list) {
          setState(() {
            _selectorOptionsCache[selectorCacheKey] = list;
          });
        },
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
      final params = Map<String, dynamic>.from(_buildEndpointParams(field));
      params['q'] = query;

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
        
        for (var rawField in allFields) {
          final fieldMap = Map<String, dynamic>.from(rawField);
          // Asegurar que 'params' (mapeo nombre_parametro -> nombre_campo) se preserve; puede perderse en el copy desde JSON
          if (rawField is Map) {
            final rf = rawField;
            if (rf.containsKey('params')) {
              final p = rf['params'];
              if (p is Map) {
                fieldMap['params'] = p.map((k, v) => MapEntry(k.toString(), v?.toString() ?? ''));
              }
            }
          }
          final name = fieldMap['name'] as String?;
          if (name != null && name.isNotEmpty) {
            fieldsMap[name] = fieldMap;
          }
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
                        // Asegurar que todos los valores inicializados se incluyan
                        // Incluir valores de campos que pueden tener value pre-inyectado
                        final allFields = widget.formConfig['fields'] as List<dynamic>? ?? [];
                        for (var field in allFields) {
                          final fieldName = field['name'] as String;
                          final fieldValue = field['value'];
                          // Si el campo tiene un valor pre-inyectado y no está en _formValues, agregarlo
                          if (fieldValue != null && !_formValues.containsKey(fieldName)) {
                            _formValues[fieldName] = fieldValue;
                          }
                        }
                        
                        // Log para debug
                        print('Submitting form with values: $_formValues');
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
