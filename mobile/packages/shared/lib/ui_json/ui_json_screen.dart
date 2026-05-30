import 'dart:async';
import 'dart:convert';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../theme/tokens/tokens.dart';
import 'ui_json_list_presentation.dart';
import 'laboratory_pdf_download_widget.dart';
import 'prescription_pdf_download_widget.dart';
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

/// Web/desktop: arrastre con ratón y rueda/trackpad en listas horizontales anidadas (p. ej. chat).
class _UiJsonWebScrollBehavior extends MaterialScrollBehavior {
  const _UiJsonWebScrollBehavior();

  @override
  Set<PointerDeviceKind> get dragDevices => {
        PointerDeviceKind.touch,
        PointerDeviceKind.mouse,
        PointerDeviceKind.stylus,
        PointerDeviceKind.trackpad,
        PointerDeviceKind.unknown,
      };
}

/// Scroll horizontal con [ScrollController] propio + rueda (dx o Shift+dy), para Flutter web.
class _HorizontalScrollInteraction extends StatefulWidget {
  const _HorizontalScrollInteraction({
    required this.height,
    required this.builder,
  });

  final double height;
  final Widget Function(BuildContext context, ScrollController controller) builder;

  @override
  State<_HorizontalScrollInteraction> createState() => _HorizontalScrollInteractionState();
}

class _HorizontalScrollInteractionState extends State<_HorizontalScrollInteraction> {
  late final ScrollController _controller = ScrollController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onPointerSignal(PointerSignalEvent event) {
    if (event is! PointerScrollEvent) return;
    if (!_controller.hasClients) return;
    final maxExtent = _controller.position.maxScrollExtent;
    final shift = HardwareKeyboard.instance.isLogicalKeyPressed(LogicalKeyboardKey.shiftLeft) ||
        HardwareKeyboard.instance.isLogicalKeyPressed(LogicalKeyboardKey.shiftRight);
    double delta = 0;
    if (event.scrollDelta.dx != 0) {
      delta = -event.scrollDelta.dx;
    } else if (shift && event.scrollDelta.dy != 0) {
      delta = -event.scrollDelta.dy;
    }
    if (delta == 0) return;
    final next = (_controller.offset + delta).clamp(0.0, maxExtent);
    _controller.jumpTo(next.toDouble());
  }

  @override
  Widget build(BuildContext context) {
    return ScrollConfiguration(
      behavior: const _UiJsonWebScrollBehavior(),
      child: Listener(
        onPointerSignal: _onPointerSignal,
        child: SizedBox(
          height: widget.height,
          child: widget.builder(context, _controller),
        ),
      ),
    );
  }
}

/// Resuelve ruta devuelta por el backend (`/api/v1/...`) contra [AppConfig.apiUrl].
String resolveApiAbsoluteUrl(String routeOrPath) {
  final r = AppConfig.normalizeApiV1Path(routeOrPath);
  if (r.startsWith('http://') || r.startsWith('https://')) {
    return r;
  }
  final base = AppConfig.apiUrl.replaceAll(RegExp(r'/$'), '');
  final origin = Uri.parse('$base/').origin;
  if (r.startsWith('/api/')) {
    return origin + r;
  }
  if (r.startsWith('/')) {
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

/// Cliente UI JSON para respuestas `kind: ui_definition` (GET descriptor + POST submit).
///
/// Los `custom_widget` se resuelven **solo en el cliente** según `widget_id` (p. ej. `weekly_scheduler`).
/// No se descargan implementaciones desde la web.
class UiJsonScreen extends StatefulWidget {
  final String apiAbsoluteUrl;
  final String? authToken;

  /// Valor de cabecera `X-App-Client` (p. ej. `bioenlace-medico`, `bioenlace-paciente`).
  final String appClient;

  /// Título visible para el usuario (por ejemplo action_name/display_name).
  final String? title;

  /// Si true, renderiza sin Scaffold/AppBar para embebido en chat (solo el body,
  /// sin `Card` ni borde extra; el host define el marco visual).
  final bool embedded;

  /// Callback opcional para listados inline: aplicar draft_delta en el host (chat) y continuar flow.
  final Future<void> Function(Map<String, dynamic> draftDelta)? onDraftDelta;

  /// Callback opcional para submits de formularios: permite que el host (chat) avance el flow.
  final Future<void> Function(Map<String, dynamic> submitData)? onSubmitSuccess;

  /// Embebido: cancelar sin POST. Solo tiene efecto en bloques **`fields`** (no en listas horizontales).
  final VoidCallback? onCancel;

  /// Definición ya obtenida (p. ej. cacheada en el mensaje del chat): no repetir GET al hacer scroll.
  final Map<String, dynamic>? initialDefinition;

  /// Persistir la definición en el host tras el primer GET exitoso.
  final void Function(Map<String, dynamic> definition)? onDefinitionLoaded;

  /// Embebido en chat: el host puede mostrar `flow_submit` cuando la UI terminó de cargar.
  final VoidCallback? onEmbeddedReady;

  /// Solo en encadenamiento de flow: lista de 1 ítem → auto-elegir y avanzar (primera carga del paso).
  final bool enableFlowChainAutoAdvance;

  /// Paso terminal del flow (con `flow_submit`): no auto-POST; la web tampoco encadena en ese caso.
  final bool isTerminalFlowStep;

  const UiJsonScreen({
    Key? key,
    required this.apiAbsoluteUrl,
    this.authToken,
    this.appClient = 'bioenlace-flutter',
    this.title,
    this.embedded = false,
    this.onDraftDelta,
    this.onSubmitSuccess,
    this.onCancel,
    this.initialDefinition,
    this.onDefinitionLoaded,
    this.onEmbeddedReady,
    this.enableFlowChainAutoAdvance = false,
    this.isTerminalFlowStep = false,
  }) : super(key: key);

  @override
  State<UiJsonScreen> createState() => _UiJsonScreenState();
}

class _UiJsonScreenState extends State<UiJsonScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _root;
  /// Selección pendiente en listados `ui_json` embebidos (antes de Confirmar).
  String? _listEmbedSelectedId;
  /// Bloqueo transitorio mientras se está aplicando el delta al padre. Se libera tras el
  /// await para permitir cambiar la elección (Cambio 1: re-tap del list).
  bool _listEmbedLocked = false;
  bool _formSubmitted = false;
  bool _flowChainAutoScheduled = false;
  final Map<String, String> _accum = {};
  final Map<String, List<Map<String, dynamic>>> _autoCache = {};
  final Map<String, Future<List<Map<String, dynamic>>>> _autoFutureCache = {};
  final Map<String, ValueNotifier<String>> _fieldValueNotifiers = {};

  ValueNotifier<String> _notifierFor(String name) {
    return _fieldValueNotifiers.putIfAbsent(name, () => ValueNotifier<String>(_accum[name] ?? ''));
  }

  List<dynamic> get _blocks => _root != null && _root!['blocks'] is List ? (_root!['blocks'] as List) : const [];

  /// Si el backend envía `display_order` en **todos** los bloques, ordena por él (p. ej. mañana antes que tarde).
  List<dynamic> _blocksOrderedForRender(List<dynamic> raw) {
    if (raw.isEmpty) return raw;
    if (!raw.every((b) {
      if (b is! Map) return false;
      return (b)['display_order'] != null;
    })) {
      return raw;
    }
    int orderKey(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }
    final out = List<dynamic>.from(raw);
    out.sort((a, b) => orderKey((a as Map)['display_order']).compareTo(orderKey((b as Map)['display_order'])));
    return out;
  }

  @override
  void initState() {
    super.initState();
    final cached = widget.initialDefinition;
    if (cached != null && cached['kind']?.toString() == 'ui_definition') {
      _hydrateFromDefinition(Map<String, dynamic>.from(cached), fromNetwork: false);
    } else {
      _load();
    }
  }

  @override
  void didUpdateWidget(UiJsonScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.apiAbsoluteUrl != widget.apiAbsoluteUrl) {
      _flowChainAutoScheduled = false;
      _load();
      return;
    }
    if (!oldWidget.enableFlowChainAutoAdvance && widget.enableFlowChainAutoAdvance) {
      _flowChainAutoScheduled = false;
      _scheduleFlowChainSingleListPick();
    }
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
    final h = AppConfig.jsonHeaders(
      bearerToken: widget.authToken,
      appClient: widget.appClient,
    );
    if (!json) {
      h.remove('Content-Type');
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
      _hydrateFromDefinition(m, fromNetwork: true);
    } catch (e) {
      setState(() {
        _error = _humanizeExceptionMessage(e);
        _loading = false;
      });
      _scheduleEmbeddedReady();
    }
  }

  void _scheduleEmbeddedReady() {
    if (!widget.embedded || widget.onEmbeddedReady == null) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      widget.onEmbeddedReady?.call();
    });
  }

  void _hydrateFromDefinition(Map<String, dynamic> m, {required bool fromNetwork}) {
    _seedAccum(m);
    setState(() {
      _root = m;
      if (fromNetwork) {
        _listEmbedSelectedId = null;
        _listEmbedLocked = false;
        _formSubmitted = false;
      }
      _loading = false;
      _error = null;
    });
    if (fromNetwork) {
      widget.onDefinitionLoaded?.call(Map<String, dynamic>.from(m));
    }
    if (widget.enableFlowChainAutoAdvance && !widget.isTerminalFlowStep) {
      _scheduleFlowChainSingleListPick();
    }
    _scheduleEmbeddedReady();
  }

  /// Encadenamiento de flow: un solo ítem en lista → elegir y avanzar (primera carga del paso).
  void _scheduleFlowChainSingleListPick() {
    if (_flowChainAutoScheduled ||
        !widget.enableFlowChainAutoAdvance ||
        widget.isTerminalFlowStep ||
        !widget.embedded ||
        widget.onDraftDelta == null ||
        _listEmbedLocked ||
        _listEmbedSelectedId != null) {
      return;
    }
    _flowChainAutoScheduled = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        _flowChainAutoScheduled = false;
        return;
      }
      WidgetsBinding.instance.addPostFrameCallback((_) {
        unawaited(_runFlowChainSingleListPick());
      });
    });
  }

  Future<void> _runFlowChainSingleListPick() async {
    if (!mounted ||
        !widget.enableFlowChainAutoAdvance ||
        widget.isTerminalFlowStep ||
        _listEmbedLocked ||
        _listEmbedSelectedId != null ||
        _root == null) {
      _flowChainAutoScheduled = false;
      return;
    }

    Map<String, dynamic>? pickedItem;
    String draftField = '';
    String pickedId = '';

    for (final bRaw in _blocksOrderedForRender(_blocks)) {
      if (bRaw is! Map) continue;
      final b = Map<String, dynamic>.from(bRaw);
      if (b['kind']?.toString() != 'list') continue;
      final itemsRaw = b['items'];
      final items = itemsRaw is List ? itemsRaw : const [];
      if (items.length != 1) continue;
      final selection =
          b['selection'] is Map ? Map<String, dynamic>.from(b['selection'] as Map) : const <String, dynamic>{};
      if (selection['requires_confirmation'] == true) continue;
      draftField = b['draft_field']?.toString() ?? '';
      if (draftField.isEmpty) continue;
      final it = items.first;
      if (it is! Map) continue;
      pickedItem = Map<String, dynamic>.from(it);
      pickedId = pickedItem['id']?.toString() ?? '';
      if (pickedId.isEmpty) continue;
      break;
    }

    if (pickedItem == null || pickedId.isEmpty || draftField.isEmpty) {
      _flowChainAutoScheduled = false;
      return;
    }

    setState(() {
      _listEmbedSelectedId = pickedId;
      _listEmbedLocked = true;
    });
    try {
      await Future<void>.delayed(const Duration(milliseconds: 480));
      if (!mounted) return;
      await _applyListEmbedDraft(draftField, pickedId, item: pickedItem);
    } finally {
      if (mounted) {
        setState(() => _listEmbedLocked = false);
      }
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
        if (wid == 'laboratory_pdf_download') {
          final iv = field['initial_values'] is Map
              ? Map<String, dynamic>.from(field['initial_values'] as Map)
              : <String, dynamic>{};
          return LaboratoryPdfDownloadWidget(
            pdfPath: iv['pdf_url']?.toString() ?? '',
            filename: iv['filename']?.toString() ?? 'informe-laboratorio.pdf',
            authToken: widget.authToken,
            appClient: 'paciente-flutter',
          );
        }
        if (wid == 'prescription_pdf_download') {
          final iv = field['initial_values'] is Map
              ? Map<String, dynamic>.from(field['initial_values'] as Map)
              : <String, dynamic>{};
          return PrescriptionPdfDownloadWidget(
            pdfPath: iv['pdf_url']?.toString() ?? '',
            filename: iv['filename']?.toString() ?? 'receta.pdf',
            authToken: widget.authToken,
            appClient: 'paciente-flutter',
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
        final dropdownKey = ValueKey<String>('ui_json.select.$name.${items.length}.${effective ?? ""}');
        return DropdownButtonFormField<String>(
          key: dropdownKey,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: required ? '$label *' : label,
            border: const OutlineInputBorder(),
          ),
          // ignore: deprecated_member_use
          value: effective,
          hint: const Text('Seleccioná una opción'),
          items: items,
          selectedItemBuilder: (context) {
            return items
                .map(
                  (item) => Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: DefaultTextStyle.merge(
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyLarge,
                      child: item.child,
                    ),
                  ),
                )
                .toList();
          },
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
      case 'textarea':
        final readonly = field['readonly'] == true;
        final rowsRaw = field['rows'];
        final lines = rowsRaw is int ? rowsRaw : int.tryParse('$rowsRaw') ?? 4;
        final clamped = lines.clamp(2, 14).toInt();
        final initialVal = field['value']?.toString() ?? _accum[name] ?? '';
        if (initialVal.isNotEmpty) {
          _accum.putIfAbsent(name, () => initialVal);
        }
        return TextFormField(
          initialValue: initialVal,
          maxLines: readonly ? clamped : 4,
          minLines: readonly ? clamped : null,
          readOnly: readonly,
          enableSuggestions: !readonly,
          decoration: InputDecoration(
            labelText: label.isNotEmpty ? (required ? '$label *' : label) : null,
            border: const OutlineInputBorder(),
            alignLabelWithHint: true,
            filled: readonly,
          ),
          style: TextStyle(color: Theme.of(context).colorScheme.onSurface),
          onChanged: readonly
              ? null
              : (v) => setState(() {
                    _accum[name] = v;
                  }),
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
                    return _HorizontalScrollInteraction(
                      height: 96,
                      builder: (_, scrollController) => ListView.separated(
                        controller: scrollController,
                        key: PageStorageKey<String>('ui_json.autocomplete:$cacheKey'),
                        scrollDirection: Axis.horizontal,
                        primary: false,
                        physics: const BouncingScrollPhysics(),
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
        final dataRaw = m['data'];
        if (dataRaw is Map) {
          final msgOk = dataRaw['mensaje']?.toString().trim() ?? '';
          if (msgOk.isNotEmpty && mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(msgOk),
                duration: const Duration(seconds: 4),
              ),
            );
          }
        }
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

  Future<void> _applyListEmbedDraft(
    String draftField,
    String id, {
    Map<String, dynamic>? item,
  }) async {
    final cb = widget.onDraftDelta;
    if (cb != null) {
      final delta = <String, dynamic>{draftField: id};
      if (item != null && item.isNotEmpty) {
        delta['_flow_item_$draftField'] = item;
      }
      await cb(delta);
    }
  }

  @override
  Widget build(BuildContext context) {
    Widget wrap({required Widget body, required String title}) {
      if (widget.embedded) {
        return body;
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
        body: widget.embedded
            ? const Padding(
                padding: EdgeInsets.all(12),
                child: Text('UI inválida'),
              )
            : const Center(child: Text('UI inválida')),
      );
    }

    final screenTitle = widget.title ?? _root?['action_id']?.toString() ?? 'UI';
    final blocks = _blocksOrderedForRender(_blocks);
    final theme = Theme.of(context);

    Widget renderListBlock(Map<String, dynamic> b) {
      final itemsRaw = b['items'];
      final items = itemsRaw is List ? itemsRaw : const [];
      final selection = b['selection'] is Map ? Map<String, dynamic>.from(b['selection'] as Map) : const <String, dynamic>{};
      final requiresConfirmation = selection['requires_confirmation'] == true;
      final draftField = b['draft_field']?.toString() ?? '';
      final title = b['title']?.toString();
      final emptyMessage = (b['empty_message'] ?? b['list_empty_message'])?.toString().trim();
      final pres = UiJsonListPresentationMetrics.fromBlock(b);
      final listRowHeight = pres.rowHeight;
      final cardWidth = pres.tileWidth;
      final tileMaxLines = pres.maxLines;
      if (draftField.isEmpty) {
        return const Text('UI inválida: falta draft_field');
      }
      if (items.isEmpty && !_loading) {
        final msg = (emptyMessage != null && emptyMessage.isNotEmpty)
            ? emptyMessage
            : 'No hay opciones para elegir en este momento. Si buscabas un servicio concreto, puede que aún no esté habilitado para turnos; consultá con tu centro de salud.';
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (title != null && title.trim().isNotEmpty) ...[
              Text(title, style: theme.textTheme.titleSmall),
              const SizedBox(height: 4),
            ],
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 4),
              child: Text(msg, style: theme.textTheme.bodyMedium),
            ),
          ],
        );
      }
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null && title.trim().isNotEmpty) ...[
            Text(title, style: theme.textTheme.titleSmall),
            const SizedBox(height: 4),
          ],
          _HorizontalScrollInteraction(
            height: listRowHeight,
            builder: (ctx, scrollController) => ListView.builder(
              controller: scrollController,
              scrollDirection: Axis.horizontal,
              primary: false,
              physics: const BouncingScrollPhysics(),
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
                final tokens = context.bio;
                final primary = IntentPalette.of(UiIntent.primary);
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: SizedBox(
                    width: cardWidth,
                    child: Material(
                      elevation: 0,
                      borderRadius: BorderRadius.circular(BioRadius.sm),
                      color: selected ? primary.softBg : tokens.paperSurfaceSunken,
                      child: InkWell(
                        borderRadius: BorderRadius.circular(BioRadius.sm),
                        onTap: () async {
                          if (_listEmbedLocked) return;
                          // Ya elegido: no re-aplicar draft ni avanzar el flow.
                          if (_listEmbedSelectedId == id) return;
                          if (requiresConfirmation) {
                            setState(() => _listEmbedSelectedId = id);
                            return;
                          }
                          setState(() {
                            _listEmbedSelectedId = id;
                            _listEmbedLocked = true;
                          });
                          try {
                            if (items.length == 1) {
                              await Future<void>.delayed(const Duration(milliseconds: 480));
                              if (!mounted) return;
                            }
                            await _applyListEmbedDraft(draftField, id, item: m);
                          } finally {
                            if (mounted) {
                              setState(() => _listEmbedLocked = false);
                            }
                          }
                        },
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(BioRadius.sm),
                            border: Border.all(
                              color: selected ? primary.base : tokens.paperBorderDefault,
                              width: selected ? BorderWidth.medium : BorderWidth.thin,
                            ),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: BioSpacing.sm,
                              vertical: BioSpacing.sm,
                            ),
                            child: Center(
                              child: Text(
                                name,
                                textAlign: TextAlign.center,
                                maxLines: tileMaxLines,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  fontWeight:
                                      selected ? FontWeight.w700 : FontWeight.w500,
                                  color: selected ? primary.softFg : tokens.textBody,
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
            const SizedBox(height: 8),
            Row(
              children: [
                const Spacer(),
                FilledButton(
                  onPressed: (_listEmbedLocked || _listEmbedSelectedId == null)
                      ? null
                      : () async {
                          final id = _listEmbedSelectedId!;
                          Map<String, dynamic>? picked;
                          for (final it in items) {
                            if (it is Map && it['id']?.toString() == id) {
                              picked = Map<String, dynamic>.from(it);
                              break;
                            }
                          }
                          setState(() => _listEmbedLocked = true);
                          try {
                            await _applyListEmbedDraft(draftField, id, item: picked);
                          } finally {
                            if (mounted) {
                              setState(() => _listEmbedLocked = false);
                            }
                          }
                        },
                  child: const Text('Confirmar'),
                ),
              ],
            ),
          ],
        ],
      );
    }

    Widget renderMessageBlock(Map<String, dynamic> b) {
      final title = b['title']?.toString();
      final text = b['text']?.toString() ?? '';
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null && title.trim().isNotEmpty) ...[
            Text(title, style: theme.textTheme.titleSmall),
            const SizedBox(height: 8),
          ],
          SelectableText(
            text,
            style: theme.textTheme.bodyMedium?.copyWith(height: 1.35),
          ),
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
          const SizedBox(height: 4),
          if (b['hide_submit'] != true && b['hide_submit'] != 1 && b['hide_submit'] != '1')
            Row(
              children: [
                if (widget.embedded && widget.onCancel != null)
                  TextButton(
                    onPressed: (_loading || _formSubmitted) ? null : widget.onCancel,
                    child: const Text('Cancelar'),
                  ),
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

    /// Padding del bloque embebido en chat; reducir aquí el aire alrededor de listas/forms.
    const blocksPadding = EdgeInsets.fromLTRB(8, 0, 8, 0);
    final blocksColumn = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final bRaw in blocks) ...[
          if (bRaw is Map) ...[
            if (bRaw['kind']?.toString() == 'list') renderListBlock(Map<String, dynamic>.from(bRaw)),
            if (bRaw['kind']?.toString() == 'message') renderMessageBlock(Map<String, dynamic>.from(bRaw)),
            if (bRaw['kind']?.toString() == 'fields') renderFieldsBlock(Map<String, dynamic>.from(bRaw)),
            const SizedBox(height: 8),
          ],
        ],
      ],
    );

    final body = _loading
        ? (widget.embedded ? embeddedLoading() : const Center(child: CircularProgressIndicator()))
        : widget.embedded
            // Chat embebido: alto = contenido (sin hueco inferior); el scroll lo hace el ListView padre.
            ? Padding(
                padding: blocksPadding,
                child: blocksColumn,
              )
            : SingleChildScrollView(
                padding: blocksPadding,
                child: blocksColumn,
              );

    return wrap(title: screenTitle, body: body);
  }
}
