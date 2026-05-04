import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import 'weekly_scheduler_widget.dart';

String _messageFromErrorBody(http.Response res) {
  try {
    final json = jsonDecode(utf8.decode(res.bodyBytes));
    if (json is Map) {
      final msg = json['message']?.toString();
      if (msg != null && msg.trim().isNotEmpty) {
        return msg.trim();
      }
    }
  } catch (_) {
    // ignore
  }
  return 'HTTP ${res.statusCode}';
}

String _humanizeExceptionMessage(Object e) {
  var s = e.toString().trim();
  // Dart suele formatear Exception como "Exception: <msg>" (o a veces "Exception. <msg>").
  s = s.replaceFirst(RegExp(r'^Exception\s*[:.]\s*', caseSensitive: false), '');
  return s.trim();
}

/// Resuelve ruta devuelta por el backend (`/api/v1/...`) contra [AppConfig.apiUrl].
String resolveApiAbsoluteUrl(String routeOrPath) {
  final r = routeOrPath.trim();
  if (r.startsWith('http://') || r.startsWith('https://')) {
    return r;
  }
  final base = AppConfig.apiUrl.replaceAll(RegExp(r'/$'), '');
  final origin = Uri.parse('$base/').origin;
  if (r.startsWith('/api/v1/')) {
    // Ruta absoluta de API: resolver siempre contra el origin (evita duplicar /api/v1 según configuración de base).
    return origin + r;
  }
  if (r.startsWith('/')) {
    // En este proyecto los endpoints del descriptor (p. ej. `/efectores/buscar`) son relativos a `/api/v1`.
    // Si ya viniera una ruta absoluta de API (`/api/...`), usar origin.
    if (r.startsWith('/api/')) {
      return origin + r;
    }
    return base + r;
  }
  return '$base/$r';
}

/// Aplica parámetros `provided` (formato backend: {k: {value,source}}) al route `/api/v1/<entidad>/<accion>`.
String applyProvidedParamsToRoute(String routeOrPath, Map<String, dynamic>? provided) {
  // Importante (Flutter Web): si devolvemos un path relativo (`/api/v1/...`),
  // el navegador lo resuelve contra el origin actual (p. ej. http://localhost:55275).
  // Por eso SIEMPRE resolvemos a URL absoluta primero.
  final base = resolveApiAbsoluteUrl(routeOrPath);
  if (provided == null || provided.isEmpty) return base;
  Uri uri = Uri.parse(base);
  final qp = <String, String>{...uri.queryParameters};
  provided.forEach((k, v) {
    if (k.toString().isEmpty) return;
    dynamic value = v;
    if (v is Map && v.containsKey('value')) {
      value = v['value'];
    }
    if (value == null) return;
    final s = value.toString();
    if (s.isEmpty) return;
    qp[k.toString()] = s;
  });
  uri = uri.replace(queryParameters: qp);
  return uri.toString();
}

// --- Campo `layout` en descriptors UI JSON (rejilla 12 cols, alineado con SPA web) ---

double _layoutBreakpointMinPx(String bp) {
  switch (bp) {
    case 'sm':
      return 576;
    case 'md':
      return 768;
    case 'lg':
      return 992;
    case 'xl':
      return 1200;
    case 'xxl':
      return 1400;
    default:
      return 768;
  }
}

bool _fieldsBlockUsesBootstrapGrid(List<dynamic> fields) {
  for (final raw in fields) {
    if (raw is! Map) continue;
    final f = Map<String, dynamic>.from(raw);
    if (f['type']?.toString() == 'hidden') continue;
    final layout = f['layout'];
    if (layout is Map && layout['col'] is num) return true;
  }
  return false;
}

/// Columnas efectivas 1–12; sin `layout.col` => 12. Si el ancho disponible es menor que el mínimo del breakpoint, fuerza 12 (stack como Bootstrap).
int _effectiveLayoutCol(Map<String, dynamic> field, double parentWidth) {
  final layout = field['layout'];
  if (layout is! Map || layout['col'] is! num) {
    return 12;
  }
  final n = (layout['col'] as num).round().clamp(1, 12);
  final bpRaw = layout['breakpoint']?.toString().trim().toLowerCase() ?? 'md';
  final minW = _layoutBreakpointMinPx(bpRaw);
  if (parentWidth > 0 && parentWidth < minW) {
    return 12;
  }
  return n;
}

List<List<Map<String, dynamic>>> _splitFieldsIntoBootstrapRows(
  List<Map<String, dynamic>> visibleFields,
  double parentWidth,
) {
  final rows = <List<Map<String, dynamic>>>[];
  var current = <Map<String, dynamic>>[];
  var sum = 0;
  for (final f in visibleFields) {
    final col = _effectiveLayoutCol(f, parentWidth);
    if (sum + col > 12 && current.isNotEmpty) {
      rows.add(current);
      current = <Map<String, dynamic>>[];
      sum = 0;
    }
    current.add(f);
    sum += col;
  }
  if (current.isNotEmpty) {
    rows.add(current);
  }
  return rows;
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

  /// Título visible para el usuario (por ejemplo action_name/display_name).
  final String? title;

  /// Si true, renderiza sin Scaffold/AppBar para embebido en chat.
  final bool embedded;

  /// Callback opcional para listados inline: aplicar draft_delta en el host (chat) y continuar flow.
  final Future<void> Function(Map<String, dynamic> draftDelta)? onDraftDelta;

  /// Callback opcional para submits de formularios: permite que el host (chat) avance el flow.
  final Future<void> Function(Map<String, dynamic> submitData)? onSubmitSuccess;

  const UiJsonWizardScreen({
    Key? key,
    required this.apiAbsoluteUrl,
    this.authToken,
    this.appClient = 'bioenlace-flutter',
    this.title,
    this.embedded = false,
    this.onDraftDelta,
    this.onSubmitSuccess,
  }) : super(key: key);

  @override
  State<UiJsonWizardScreen> createState() => _UiJsonWizardScreenState();
}

class _UiJsonWizardScreenState extends State<UiJsonWizardScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _root;
  /// Selección pendiente en listados `ui_json` embebidos (antes de Confirmar).
  String? _listEmbedSelectedId;
  /// Cuando se confirma/aplica un ítem, bloquear nuevas selecciones en este embed.
  bool _listEmbedLocked = false;
  bool _formSubmitted = false;
  final Map<String, String> _accum = {};
  final Map<String, List<Map<String, dynamic>>> _autoCache = {};
  final Map<String, Future<List<Map<String, dynamic>>>> _autoFutureCache = {};
  final Map<String, ValueNotifier<String>> _fieldValueNotifiers = {};

  ValueNotifier<String> _notifierFor(String name) {
    return _fieldValueNotifiers.putIfAbsent(name, () => ValueNotifier<String>(_accum[name] ?? ''));
  }

  List<dynamic> get _blocks => _root != null && _root!['blocks'] is List ? (_root!['blocks'] as List) : const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final n in _fieldValueNotifiers.values) {
      n.dispose();
    }
    _fieldValueNotifiers.clear();
    super.dispose();
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
        throw Exception(_messageFromErrorBody(res));
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
        _listEmbedSelectedId = null;
        _listEmbedLocked = false;
        _formSubmitted = false;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = _humanizeExceptionMessage(e);
        _loading = false;
      });
    }
  }

  void _seedAccum(Map<String, dynamic> def) {
    _accum.clear();
    final blocks = def['blocks'] is List ? (def['blocks'] as List) : const [];
    for (final bRaw in blocks) {
      if (bRaw is! Map) continue;
      final b = Map<String, dynamic>.from(bRaw);
      if (b['kind']?.toString() != 'fields') continue;
      final fields = b['fields'];
      if (fields is! List) continue;
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
    }
    final values = def['values'];
    if (values is Map) {
      values.forEach((k, v) {
        if (v != null) _accum[k.toString()] = v.toString();
      });
    }
  }

  List<Map<String, dynamic>> _allFieldDefs() {
    final out = <Map<String, dynamic>>[];
    for (final bRaw in _blocks) {
      if (bRaw is! Map) continue;
      final b = Map<String, dynamic>.from(bRaw);
      if (b['kind']?.toString() != 'fields') continue;
      final fields = b['fields'];
      if (fields is! List) continue;
      for (final raw in fields) {
        if (raw is! Map) continue;
        out.add(Map<String, dynamic>.from(raw));
      }
    }
    return out;
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

  String _autocompleteCacheKey(Map<String, dynamic> field) {
    final name = field['name']?.toString() ?? '';
    final keyParts = <String>[name];
    for (final dep in _dependsOn(field)) {
      keyParts.add('${dep}=${_accum[dep] ?? ''}');
    }
    return keyParts.join('&');
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

  Future<List<Map<String, dynamic>>> _autoLoadAutocomplete(Map<String, dynamic> field) async {
    final name = field['name']?.toString() ?? '';
    if (name.isEmpty) return [];
    final cacheKey = _autocompleteCacheKey(field);
    if (_autoFutureCache.containsKey(cacheKey)) {
      return _autoFutureCache[cacheKey]!;
    }
    final fut = () async {
      if (_autoCache.containsKey(cacheKey)) {
        return _autoCache[cacheKey]!;
      }
      final items = await _fetchAutocomplete(field);
      _autoCache[cacheKey] = items;
      return items;
    }();
    _autoFutureCache[cacheKey] = fut;
    return fut;
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
        final currentTrimmed = current?.trim();

        // Normalizar y deduplicar opciones por value para evitar:
        // - value vacío ("") considerado como selección real
        // - valores duplicados que rompen DropdownButtonFormField (assert de "exactly one item")
        final seen = <String>{};
        final items = <DropdownMenuItem<String>>[];
        for (final o in opts) {
          final om = o is Map ? Map<String, dynamic>.from(o) : {'value': o, 'label': o};
          // Soportar variantes comunes:
          // - {value,label} (estándar)
          // - {id,name} (catálogos legacy)
          final v = (om['value']?.toString() ?? om['id']?.toString() ?? '').trim();
          if (v.isEmpty) continue;
          if (seen.contains(v)) continue;
          seen.add(v);
          final lab = (om['label']?.toString() ?? om['name']?.toString() ?? v).trim();
          items.add(DropdownMenuItem<String>(value: v, child: Text(lab)));
        }

        String? effective = (currentTrimmed != null && currentTrimmed.isNotEmpty) ? currentTrimmed : null;
        if (effective != null && !seen.contains(effective)) {
          effective = null;
        }
        if (items.isEmpty) {
          return ListTile(
            title: Text(required ? '$label *' : label),
            subtitle: const Text('Sin opciones disponibles'),
          );
        }
        return DropdownButtonFormField<String>(
          decoration: InputDecoration(labelText: required ? '$label *' : label),
          // ignore: deprecated_member_use
          value: effective,
          hint: const Text('Seleccione...'),
          items: items,
          iconEnabledColor: Theme.of(context).colorScheme.primary,
          dropdownColor: Theme.of(context).colorScheme.surface,
          onChanged: (v) => setState(() {
            if (v != null) _accum[name] = v;
          }),
          validator: required ? (v) => v == null || v.isEmpty ? 'Requerido' : null : null,
        );
      case 'radio':
        final ropts = (field['options'] as List?) ?? [];
        final rseen = <String>{};
        final rentries = <MapEntry<String, String>>[];
        for (final o in ropts) {
          final om = o is Map ? Map<String, dynamic>.from(o) : {'value': o, 'label': o};
          final v = (om['value']?.toString() ?? om['id']?.toString() ?? '').trim();
          if (v.isEmpty || rseen.contains(v)) continue;
          rseen.add(v);
          final lab = (om['label']?.toString() ?? om['name']?.toString() ?? v).trim();
          rentries.add(MapEntry(v, lab));
        }
        final gv = _accum[name]?.trim();
        final effectiveRadio = (gv != null && gv.isNotEmpty && rseen.contains(gv)) ? gv : null;
        if (rentries.isEmpty) {
          return ListTile(
            title: Text(required ? '$label *' : label),
            subtitle: const Text('Sin opciones disponibles'),
          );
        }
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (label.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  required ? '$label *' : label,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
                ),
              ),
            ...rentries.map(
              (e) => RadioListTile<String>(
                dense: true,
                contentPadding: EdgeInsets.zero,
                title: Text(e.value),
                value: e.key,
                groupValue: effectiveRadio,
                onChanged: (val) => setState(() {
                  if (val != null) _accum[name] = val;
                }),
              ),
            ),
          ],
        );
      case 'number':
        return TextFormField(
          initialValue: _accum[name] ?? '',
          decoration: InputDecoration(labelText: required ? '$label *' : label),
          keyboardType: TextInputType.number,
          style: TextStyle(color: Theme.of(context).colorScheme.onSurface),
          cursorColor: Theme.of(context).colorScheme.primary,
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
        final n = _notifierFor(name);
        if (!_depsOk(field)) {
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Text(
              field['message']?.toString() ?? 'Complete: ${_dependsOn(field).join(", ")}',
              style: TextStyle(color: Colors.orange[800], fontSize: 13),
            ),
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(required ? '$label *' : label, style: const TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            FutureBuilder<List<Map<String, dynamic>>>(
              future: _autoLoadAutocomplete(field),
              builder: (ctx, snap) {
                if (snap.connectionState == ConnectionState.waiting) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: LinearProgressIndicator(),
                  );
                }
                final items = snap.data ?? [];
                if (items.isEmpty) {
                  return const Text('Sin resultados.');
                }

                // Listado horizontal en cards (sin search).
                final show = items.take(30).toList();
                final cacheKey = _autocompleteCacheKey(field);
                return ValueListenableBuilder<String>(
                  valueListenable: n,
                  builder: (_, id, __) {
                    return SizedBox(
                      height: 96,
                      child: ListView.separated(
                        key: PageStorageKey<String>('ui_json.autocomplete:$cacheKey'),
                        scrollDirection: Axis.horizontal,
                        itemCount: show.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 10),
                        itemBuilder: (_, idx) {
                          final it = show[idx];
                          final tid = it['id']?.toString() ?? '';
                          final text = it['text']?.toString() ?? tid;
                          final selected = tid.isNotEmpty && tid == id;
                          final borderColor =
                              selected ? Theme.of(context).colorScheme.primary : Theme.of(context).dividerColor;
                          return SizedBox(
                            width: 280,
                            child: Card(
                              elevation: 0,
                              margin: EdgeInsets.zero,
                              color: selected ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.08) : null,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                                side: BorderSide(color: borderColor, width: 1),
                              ),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: () {
                                  if (tid.isEmpty) return;
                                  if (tid == id) return;
                                  _accum[name] = tid;
                                  n.value = tid;
                                },
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          text,
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                      if (selected) ...[
                                        const SizedBox(width: 8),
                                        Icon(Icons.check_circle, color: Theme.of(context).colorScheme.primary),
                                      ],
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    );
                  },
                );
              },
            ),
          ],
        );
      default:
        return TextFormField(
          initialValue: _accum[name] ?? '',
          decoration: InputDecoration(labelText: required ? '$label *' : label),
          style: TextStyle(color: Theme.of(context).colorScheme.onSurface),
          cursorColor: Theme.of(context).colorScheme.primary,
          onChanged: (v) => _accum[name] = v,
          validator: required ? (v) => (v == null || v.isEmpty) ? 'Requerido' : null : null,
        );
    }
  }

  bool _validateRequiredFields() {
    for (final f in _allFieldDefs()) {
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
    if (!_validateRequiredFields()) return;
    setState(() => _loading = true);
    try {
      final uri = Uri.parse(widget.apiAbsoluteUrl);
      final res = await http
          .post(uri, headers: _headers(), body: _accum)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception(_messageFromErrorBody(res));
      }
      final j = jsonDecode(utf8.decode(res.bodyBytes));
      if (j is! Map) throw Exception('Respuesta inválida');
      final m = Map<String, dynamic>.from(j);
      if (m['kind'] == 'ui_submit_result' && m['success'] == true) {
        if (!mounted) return;
        setState(() {
          _loading = false;
          _formSubmitted = true;
        });
        final cb = widget.onSubmitSuccess;
        if (cb != null) {
          final data = m['data'];
          if (data is Map) {
            await cb(Map<String, dynamic>.from(data));
          } else {
            await cb({'ok': true});
          }
        }
        if (!widget.embedded) {
          Navigator.of(context).pop();
        }
        return;
      }
      if (m['kind'] == 'ui_definition') {
        _seedAccum(m);
        setState(() {
          _root = m;
          _listEmbedSelectedId = null;
          _loading = false;
          _formSubmitted = false;
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
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_humanizeExceptionMessage(e))),
      );
    }
  }

  Future<void> _applyListEmbedDraft(String draftField, String id) async {
    final cb = widget.onDraftDelta;
    if (cb != null) {
      await cb({draftField: id});
    }
  }

  @override
  Widget build(BuildContext context) {
    Widget wrap({required Widget body, required String title}) {
      if (widget.embedded) {
        return Card(margin: EdgeInsets.zero, child: body);
      }
      return Scaffold(
        appBar: AppBar(title: Text(title)),
        body: body,
      );
    }

    Widget embeddedLoading() => const Padding(
          padding: EdgeInsets.symmetric(vertical: 12, horizontal: 12),
          child: SizedBox(
            height: 28,
            width: 28,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        );

    if (_loading && _root == null) {
      return wrap(
        title: widget.title ?? 'Cargando…',
        body: widget.embedded ? embeddedLoading() : const Center(child: CircularProgressIndicator()),
      );
    }
    if (_error != null && _root == null) {
      return wrap(
        title: widget.title ?? 'Error',
        body: widget.embedded
            ? Padding(
                padding: const EdgeInsets.all(12),
                child: Text(_error!, style: Theme.of(context).textTheme.bodySmall),
              )
            : Center(child: Text(_error!)),
      );
    }

    if (_root == null || _root!['ui_type']?.toString() != 'ui_json') {
      return wrap(
        title: widget.title ?? 'UI',
        body: const Center(child: Text('UI inválida')),
      );
    }

    final screenTitle = widget.title ?? _root?['action_id']?.toString() ?? 'UI';
    final blocks = _blocks;
    final theme = Theme.of(context);

    Widget renderListBlock(Map<String, dynamic> b) {
      final itemsRaw = b['items'];
      final items = itemsRaw is List ? itemsRaw : const [];
      final selection = b['selection'] is Map ? Map<String, dynamic>.from(b['selection'] as Map) : const <String, dynamic>{};
      final requiresConfirmation = selection['requires_confirmation'] == true;
      final draftField = b['draft_field']?.toString() ?? '';
      final title = b['title']?.toString();
      const double listRowHeight = 88;
      const double cardWidth = 148;
      if (draftField.isEmpty) {
        return const Text('UI inválida: falta draft_field');
      }
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null && title.trim().isNotEmpty) ...[
            Text(title, style: theme.textTheme.titleSmall),
            const SizedBox(height: 8),
          ],
          SizedBox(
            height: listRowHeight,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: EdgeInsets.zero,
              itemCount: items.length,
              itemBuilder: (context, idx) {
                final it = items[idx];
                if (it is! Map) return const SizedBox.shrink();
                final m = Map<String, dynamic>.from(it);
                final id = m['id']?.toString() ?? '';
                final name = (m['name'] ?? m['label'] ?? id)?.toString() ?? id;
                if (id.isEmpty) return const SizedBox.shrink();
                final selected = _listEmbedSelectedId == id;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: SizedBox(
                    width: cardWidth,
                    child: Material(
                      elevation: selected ? 2 : 0,
                      borderRadius: BorderRadius.circular(10),
                      color: selected
                          ? theme.colorScheme.primaryContainer.withAlpha((0.35 * 255).round())
                          : theme.colorScheme.surfaceContainerHighest.withAlpha((0.55 * 255).round()),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(10),
                        onTap: () async {
                          if (_listEmbedLocked) return;
                          if (requiresConfirmation) {
                            setState(() => _listEmbedSelectedId = id);
                          } else {
                            // Un solo ítem: dar tiempo a percibir la UI antes de avanzar el flow.
                            if (items.length == 1) {
                              await Future<void>.delayed(const Duration(milliseconds: 480));
                              if (!mounted || _listEmbedLocked) return;
                            }
                            await _applyListEmbedDraft(draftField, id);
                            if (mounted) {
                              setState(() {
                                _listEmbedLocked = true;
                                _listEmbedSelectedId = null;
                              });
                            }
                          }
                        },
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(
                              color: selected ? theme.colorScheme.primary : theme.dividerColor,
                              width: selected ? 2 : 1,
                            ),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                            child: Center(
                              child: Text(
                                name,
                                textAlign: TextAlign.center,
                                maxLines: 3,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          if (requiresConfirmation) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                const Spacer(),
                FilledButton(
                  onPressed: (_listEmbedLocked || _listEmbedSelectedId == null)
                      ? null
                      : () async {
                          final id = _listEmbedSelectedId!;
                          await _applyListEmbedDraft(draftField, id);
                          if (mounted) {
                            setState(() {
                              _listEmbedLocked = true;
                              _listEmbedSelectedId = null;
                            });
                          }
                        },
                  child: Text(_listEmbedLocked ? 'Confirmado' : 'Confirmar'),
                ),
              ],
            ),
          ],
        ],
      );
    }

    Widget renderFieldsBlock(Map<String, dynamic> b) {
      final title = b['title']?.toString();
      final fieldsRaw = b['fields'];
      final fields = fieldsRaw is List ? fieldsRaw : const [];
      final useGrid = _fieldsBlockUsesBootstrapGrid(fields);

      final hiddenWidgets = <Widget>[];
      final visibleFields = <Map<String, dynamic>>[];
      for (final raw in fields) {
        if (raw is! Map) continue;
        final f = Map<String, dynamic>.from(raw);
        if (f['type']?.toString() == 'hidden') {
          hiddenWidgets.add(_buildField(f));
        } else {
          visibleFields.add(f);
        }
      }

      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null && title.trim().isNotEmpty) ...[
            Text(title, style: theme.textTheme.titleSmall),
            const SizedBox(height: 8),
          ],
          ...hiddenWidgets,
          if (!useGrid)
            ...visibleFields.map((f) {
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _buildField(f),
              );
            })
          else
            LayoutBuilder(
              builder: (context, constraints) {
                final maxW = constraints.maxWidth;
                final rows = _splitFieldsIntoBootstrapRows(visibleFields, maxW);
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: rows.map((row) {
                    final cols = row.map((f) => _effectiveLayoutCol(f, maxW)).toList();
                    final sum = cols.fold<int>(0, (a, b) => a + b);
                    final rowChildren = <Widget>[];
                    for (var i = 0; i < row.length; i++) {
                      rowChildren.add(
                        Expanded(
                          flex: cols[i],
                          child: Padding(
                            padding: EdgeInsets.only(
                              left: i == 0 ? 0 : 6,
                              right: i == row.length - 1 ? 0 : 6,
                            ),
                            child: _buildField(row[i]),
                          ),
                        ),
                      );
                    }
                    if (sum < 12) {
                      rowChildren.add(Expanded(flex: 12 - sum, child: const SizedBox.shrink()));
                    }
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: rowChildren,
                      ),
                    );
                  }).toList(),
                );
              },
            ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Spacer(),
              ElevatedButton(
                onPressed: (_loading || _formSubmitted) ? null : _submit,
                child: Text(_formSubmitted ? 'Confirmado' : 'Confirmar'),
              ),
            ],
          ),
        ],
      );
    }

    final body = _loading
        ? (widget.embedded ? embeddedLoading() : const Center(child: CircularProgressIndicator()))
        : SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final bRaw in blocks) ...[
                  if (bRaw is Map) ...[
                    if (bRaw['kind']?.toString() == 'list') renderListBlock(Map<String, dynamic>.from(bRaw)),
                    if (bRaw['kind']?.toString() == 'fields') renderFieldsBlock(Map<String, dynamic>.from(bRaw)),
                    const SizedBox(height: 16),
                  ],
                ],
              ],
            ),
          );

    return wrap(title: screenTitle, body: body);
  }
}
