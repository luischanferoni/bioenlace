import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import 'weekly_scheduler_widget.dart';

/// Resuelve ruta devuelta por el backend (`/api/v1/...`) contra [AppConfig.apiUrl].
String resolveApiAbsoluteUrl(String routeOrPath) {
  final r = routeOrPath.trim();
  if (r.startsWith('http://') || r.startsWith('https://')) {
    return r;
  }
  final base = AppConfig.apiUrl.replaceAll(RegExp(r'/$'), '');
  if (r.startsWith('/api/v1/')) {
    return base + r.substring('/api/v1'.length);
  }
  final origin = Uri.parse('$base/').origin;
  if (r.startsWith('/')) {
    return origin + r;
  }
  return '$base/$r';
}

/// Wizard mínimo para respuestas `kind: ui_definition` (GET descriptor + POST submit).
///
/// Los `custom_widget` se resuelven **solo en el cliente** según `widget_id` (p. ej. `weekly_scheduler`).
/// No se descargan implementaciones desde la web.
class UiJsonWizardScreen extends StatefulWidget {
  final String apiAbsoluteUrl;
  final String? authToken;

  /// Valor de cabecera `X-App-Client` (p. ej. `bioenlace-medico`, `bioenlace-paciente`).
  final String appClient;

  const UiJsonWizardScreen({
    Key? key,
    required this.apiAbsoluteUrl,
    this.authToken,
    this.appClient = 'bioenlace-flutter',
  }) : super(key: key);

  @override
  State<UiJsonWizardScreen> createState() => _UiJsonWizardScreenState();
}

class _UiJsonWizardScreenState extends State<UiJsonWizardScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _root;
  int _step = 0;
  final Map<String, String> _accum = {};

  Map<String, dynamic>? get _wc =>
      _root != null && _root!['wizard_config'] is Map
          ? Map<String, dynamic>.from(_root!['wizard_config'] as Map)
          : null;

  List<dynamic> get _steps => _wc?['steps'] as List<dynamic>? ?? [];

  List<dynamic> get _fields => _wc?['fields'] as List<dynamic>? ?? [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Map<String, String> _headers({bool json = false}) {
    final h = <String, String>{
      'Accept': 'application/json',
      'X-App-Client': widget.appClient,
      'X-App-Version': '1.0.0',
      'X-Client': 'mobile',
    };
    if (json) {
      h['Content-Type'] = 'application/json';
    }
    final t = widget.authToken;
    if (t != null && t.isNotEmpty) {
      h['Authorization'] = 'Bearer $t';
    }
    return h;
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final uri = Uri.parse(widget.apiAbsoluteUrl);
      final res = await http
          .get(uri, headers: _headers())
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('HTTP ${res.statusCode}');
      }
      final json = jsonDecode(utf8.decode(res.bodyBytes));
      if (json is! Map) {
        throw Exception('Respuesta inválida');
      }
      final m = Map<String, dynamic>.from(json);
      if (m['kind'] != 'ui_definition') {
        throw Exception('No es ui_definition');
      }
      _seedAccum(m);
      setState(() {
        _root = m;
        _step = (m['wizard_config'] is Map && (m['wizard_config']['initial_step'] is int))
            ? m['wizard_config']['initial_step'] as int
            : 0;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  void _seedAccum(Map<String, dynamic> def) {
    _accum.clear();
    final fields = def['wizard_config'] is Map ? (def['wizard_config']['fields'] as List?) : null;
    if (fields == null) return;
    for (final raw in fields) {
      if (raw is! Map) continue;
      final f = Map<String, dynamic>.from(raw);
      final name = f['name']?.toString();
      if (name == null) continue;
      final v = f['value'];
      if (v != null && v.toString().isNotEmpty) {
        _accum[name] = v.toString();
      }
      if (f['type'] == 'custom_widget' && f['initial_values'] is Map) {
        final iv = Map<String, dynamic>.from(f['initial_values'] as Map);
        iv.forEach((k, val) {
          if (val != null && val.toString().isNotEmpty) {
            _accum[k.toString()] = val.toString();
          }
        });
      }
    }
    final values = def['values'];
    if (values is Map) {
      values.forEach((k, v) {
        if (v != null) _accum[k.toString()] = v.toString();
      });
    }
  }

  Map<String, dynamic>? _fieldByName(String name) {
    for (final raw in _fields) {
      if (raw is Map && raw['name']?.toString() == name) {
        return Map<String, dynamic>.from(raw);
      }
    }
    return null;
  }

  List<String> _stepFieldNames() {
    if (_step < 0 || _step >= _steps.length) return [];
    final s = _steps[_step];
    if (s is! Map) return [];
    final flds = s['fields'];
    if (flds is! List) return [];
    return flds.map((e) => e.toString()).toList();
  }

  List<String> _dependsOn(Map<String, dynamic> f) {
    final raw = f['depends_on'];
    if (raw == null) return [];
    if (raw is String) return raw.isEmpty ? [] : [raw];
    if (raw is List) {
      return raw.map((e) => e?.toString() ?? '').where((s) => s.isNotEmpty).toList();
    }
    return [];
  }

  bool _depsOk(Map<String, dynamic> f) {
    for (final d in _dependsOn(f)) {
      final v = _accum[d];
      if (v == null || v.trim().isEmpty) return false;
    }
    return true;
  }

  Future<List<Map<String, dynamic>>> _fetchAutocomplete(Map<String, dynamic> field) async {
    final endpoint = field['endpoint']?.toString() ?? '';
    if (endpoint.isEmpty) return [];
    var uri = Uri.parse(resolveApiAbsoluteUrl(endpoint));
    final qp = Map<String, String>.from(uri.queryParameters);
    final pm = field['params'];
    if (pm is Map) {
      pm.forEach((k, v) {
        final fn = v.toString();
        final val = _accum[fn];
        if (val != null && val.isNotEmpty) {
          qp[k.toString()] = val;
        }
      });
    }
    uri = uri.replace(queryParameters: qp);
    final res = await http.get(uri, headers: _headers()).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw Exception('Autocomplete HTTP ${res.statusCode}');
    }
    final j = jsonDecode(utf8.decode(res.bodyBytes));
    if (j is! Map) return [];
    final results = j['results'];
    if (results is! List) return [];
    return results.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> _pickAutocomplete(Map<String, dynamic> field) async {
    if (!_depsOk(field)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(field['message']?.toString() ?? 'Complete los pasos anteriores.')),
      );
      return;
    }
    try {
      final items = await _fetchAutocomplete(field);
      if (!mounted) return;
      final name = field['name']?.toString() ?? '';
      final picked = await showModalBottomSheet<Map<String, dynamic>>(
        context: context,
        builder: (ctx) {
          return ListView(
            children: items
                .map(
                  (it) => ListTile(
                    title: Text(it['text']?.toString() ?? ''),
                    onTap: () => Navigator.pop(ctx, it),
                  ),
                )
                .toList(),
          );
        },
      );
      if (picked != null) {
        setState(() {
          _accum[name] = picked['id']?.toString() ?? '';
        });
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e')));
    }
  }

  Future<void> _pickDate(Map<String, dynamic> field) async {
    final name = field['name']?.toString() ?? '';
    final now = DateTime.now();
    final initial = DateTime.tryParse(_accum[name] ?? '') ?? now;
    final d = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1990),
      lastDate: DateTime(now.year + 5),
    );
    if (d != null) {
      final y = d.year.toString().padLeft(4, '0');
      final m = d.month.toString().padLeft(2, '0');
      final day = d.day.toString().padLeft(2, '0');
      setState(() {
        _accum[name] = '$y-$m-$day';
      });
    }
  }

  Widget _buildField(Map<String, dynamic> field) {
    final name = field['name']?.toString() ?? '';
    final label = field['label']?.toString() ?? name;
    final type = field['type']?.toString() ?? 'text';
    final required = field['required'] == true;

    if (!_depsOk(field)) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Text(
          field['message']?.toString() ?? 'Complete: ${_dependsOn(field).join(", ")}',
          style: TextStyle(color: Colors.orange[800], fontSize: 13),
        ),
      );
    }

    switch (type) {
      case 'hidden':
        return const SizedBox.shrink();
      case 'custom_widget':
        final wid = field['widget_id']?.toString();
        if (wid == 'weekly_scheduler') {
          final vf = (field['value_fields'] as List?)?.map((e) => e.toString()).toList() ?? [];
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (label.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
                ),
              WeeklySchedulerWidget(
                fieldNames: vf,
                values: Map<String, String>.from(_accum),
                onChanged: (m) => setState(() => _accum.addAll(m)),
              ),
            ],
          );
        }
        return Text('Widget no soportado: $wid');
      case 'select':
        final opts = (field['options'] as List?) ?? [];
        final current = _accum[name];
        final effective = (current != null && current.isNotEmpty) ? current : null;
        return DropdownButtonFormField<String>(
          decoration: InputDecoration(labelText: required ? '$label *' : label),
          // ignore: deprecated_member_use
          value: effective,
          hint: const Text('Seleccione...'),
          items: opts.map((o) {
            final om = o is Map ? Map<String, dynamic>.from(o) : {'value': o, 'label': o};
            final v = om['value']?.toString() ?? '';
            final lab = om['label']?.toString() ?? v;
            return DropdownMenuItem<String>(value: v, child: Text(lab));
          }).toList(),
          onChanged: (v) => setState(() {
            if (v != null) _accum[name] = v;
          }),
          validator: required ? (v) => v == null || v.isEmpty ? 'Requerido' : null : null,
        );
      case 'number':
        return TextFormField(
          initialValue: _accum[name] ?? '',
          decoration: InputDecoration(labelText: required ? '$label *' : label),
          keyboardType: TextInputType.number,
          onChanged: (v) => _accum[name] = v,
          validator: required ? (v) => (v == null || v.isEmpty) ? 'Requerido' : null : null,
        );
      case 'date':
        return ListTile(
          title: Text(required ? '$label *' : label),
          subtitle: Text(_accum[name] ?? 'Elegir fecha'),
          trailing: const Icon(Icons.calendar_today),
          onTap: () => _pickDate(field),
        );
      case 'autocomplete':
        final id = _accum[name] ?? '';
        return ListTile(
          title: Text(required ? '$label *' : label),
          subtitle: Text(id.isEmpty ? 'Sin selección' : 'id: $id'),
          trailing: const Icon(Icons.search),
          onTap: () => _pickAutocomplete(field),
        );
      default:
        return TextFormField(
          initialValue: _accum[name] ?? '',
          decoration: InputDecoration(labelText: required ? '$label *' : label),
          onChanged: (v) => _accum[name] = v,
          validator: required ? (v) => (v == null || v.isEmpty) ? 'Requerido' : null : null,
        );
    }
  }

  bool _validateStep() {
    for (final fn in _stepFieldNames()) {
      final f = _fieldByName(fn);
      if (f == null) continue;
      if (!_depsOk(f)) continue;
      if (f['type'] == 'hidden') continue;
      if (f['required'] == true) {
        final name = f['name']?.toString() ?? '';
        final v = _accum[name];
        if (v == null || v.trim().isEmpty) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Complete: ${f['label'] ?? name}')),
          );
          return false;
        }
      }
    }
    return true;
  }

  Future<void> _submit() async {
    if (!_validateStep()) return;
    setState(() => _loading = true);
    try {
      final uri = Uri.parse(widget.apiAbsoluteUrl);
      final res = await http
          .post(uri, headers: _headers(), body: _accum)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final j = jsonDecode(utf8.decode(res.bodyBytes));
      if (j is! Map) throw Exception('Respuesta inválida');
      final m = Map<String, dynamic>.from(j);
      if (m['kind'] == 'ui_submit_result' && m['success'] == true) {
        final data = m['data'];
        String msg = 'Guardado.';
        if (data is Map && data['message'] != null) {
          msg = data['message'].toString();
        }
        if (!mounted) return;
        setState(() => _loading = false);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
        Navigator.of(context).pop();
        return;
      }
      if (m['kind'] == 'ui_definition') {
        _seedAccum(m);
        setState(() {
          _root = m;
          _loading = false;
          _step = (m['wizard_config'] is Map && (m['wizard_config']['initial_step'] is int))
              ? m['wizard_config']['initial_step'] as int
              : _step;
        });
        final err = m['errors'];
        if (err is Map && err.isNotEmpty) {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(err.values.first.toString())),
          );
        }
        return;
      }
      throw Exception(m['message']?.toString() ?? 'Error al guardar');
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _root == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Cargando…')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    if (_error != null && _root == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Error')),
        body: Center(child: Text(_error!)),
      );
    }
    final wc = _wc;
    if (wc == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('UI')),
        body: const Center(child: Text('Sin wizard_config')),
      );
    }
    final stepMeta = _step < _steps.length && _steps[_step] is Map ? _steps[_step] as Map : null;
    final title = stepMeta?['title']?.toString() ?? 'Paso ${_step + 1}';

    return Scaffold(
      appBar: AppBar(title: Text(_root?['action_id']?.toString() ?? 'Formulario')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(title, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  LinearProgressIndicator(
                    value: _steps.isEmpty ? 0 : (_step + 1) / _steps.length,
                  ),
                  const SizedBox(height: 16),
                  ..._stepFieldNames().map((fn) {
                    final f = _fieldByName(fn);
                    if (f == null) return const SizedBox.shrink();
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _buildField(f),
                    );
                  }),
                  const SizedBox(height: 24),
                  Row(
                    children: [
                      if (_step > 0)
                        TextButton(
                          onPressed: () => setState(() => _step--),
                          child: const Text('Anterior'),
                        ),
                      const Spacer(),
                      if (_step < _steps.length - 1)
                        ElevatedButton(
                          onPressed: () {
                            if (!_validateStep()) return;
                            setState(() => _step++);
                          },
                          child: const Text('Siguiente'),
                        )
                      else
                        ElevatedButton(
                          onPressed: _submit,
                          child: const Text('Confirmar'),
                        ),
                    ],
                  ),
                ],
              ),
            ),
    );
  }
}
