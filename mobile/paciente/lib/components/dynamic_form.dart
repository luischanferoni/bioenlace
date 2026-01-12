import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared/shared.dart';

/// Widget para generar formularios dinámicos basados en form_config del backend
class DynamicForm extends StatefulWidget {
  final Map<String, dynamic> formConfig;
  final String? authToken;
  final Function(Map<String, dynamic>) onSubmit;
  final Function()? onCancel;

  const DynamicForm({
    Key? key,
    required this.formConfig,
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

  @override
  void initState() {
    super.initState();
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
      
      // Cargar opciones si es necesario
      if (field['endpoint'] != null && field['type'] == 'autocomplete') {
        _loadOptions(fieldName, field);
      }
    }
  }

  Future<void> _loadOptions(String fieldName, Map<String, dynamic> field) async {
    if (_loadingOptions[fieldName] == true) return;
    
    setState(() {
      _loadingOptions[fieldName] = true;
    });

    try {
      final endpoint = field['endpoint'] as String;
      final params = field['params'] as Map<String, dynamic>? ?? {};
      
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
        final items = data['data'] ?? data['items'] ?? data;
        
        List<Map<String, dynamic>> options = [];
        if (items is List) {
          options = items.map((item) {
            if (item is Map) {
              return Map<String, dynamic>.from(item);
            }
            return {'id': item, 'name': item.toString()};
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

    // Si depende de otro campo, recargar opciones cuando cambie
    if (dependsOn != null && _formValues.containsKey(dependsOn)) {
      final dependsValue = _formValues[dependsOn];
      final fieldParams = field['params'] as Map<String, dynamic>? ?? {};
      if (fieldParams[dependsOn] != dependsValue) {
        fieldParams[dependsOn] = dependsValue;
        field['params'] = fieldParams;
        _loadOptions(fieldName, field);
      }
    }

    switch (type) {
      case 'autocomplete':
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
    
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: DropdownButtonFormField<String>(
        decoration: InputDecoration(
          labelText: label + (required ? ' *' : ''),
          hintText: description,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        value: _formValues[fieldName]?.toString(),
        items: options.map((option) {
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

  Widget _buildAutocompleteField(Map<String, dynamic> field, String label, bool required, String? description) {
    final fieldName = field['name'] as String;
    final options = _optionsCache[fieldName] ?? [];
    final isLoading = _loadingOptions[fieldName] == true;
    
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
            Text('Cargando opciones...'),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: Autocomplete<Map<String, dynamic>>(
        optionsBuilder: (TextEditingValue textEditingValue) {
          if (textEditingValue.text.isEmpty) {
            return options;
          }
          return options.where((option) {
            final display = option['name']?.toString() ?? 
                           option['label']?.toString() ?? 
                           option.toString();
            return display.toLowerCase().contains(textEditingValue.text.toLowerCase());
          });
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
              hintText: description,
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

  @override
  Widget build(BuildContext context) {
    final fields = widget.formConfig['fields'] as List<dynamic>? ?? [];
    final submitLabel = widget.formConfig['submit_label'] as String? ?? 'Enviar';

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ...fields.map((field) => _buildField(Map<String, dynamic>.from(field))),
          const SizedBox(height: 16),
          Row(
            children: [
              if (widget.onCancel != null)
                Expanded(
                  child: OutlinedButton(
                    onPressed: widget.onCancel,
                    child: Text('Cancelar'),
                  ),
                ),
              if (widget.onCancel != null) const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton(
                  onPressed: () {
                    if (_formKey.currentState?.validate() ?? false) {
                      widget.onSubmit(_formValues);
                    }
                  },
                  child: Text(submitLabel),
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
