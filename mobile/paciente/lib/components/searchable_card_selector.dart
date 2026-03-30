import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared/shared.dart';

bool _endpointIsSlotsPaciente(String? endpoint) =>
    endpoint != null && endpoint.contains('slots-disponibles-como-paciente');

/// `YYYY-MM-DD` → `dd/MM/yyyy` para el subtítulo de la card.
String _formatFechaCardSlots(String isoDate) {
  final p = isoDate.split('-');
  if (p.length != 3) return isoDate;
  return '${p[2]}/${p[1]}/${p[0]}';
}

/// Aplana `por_dia` de GET …/turnos/slots-disponibles-como-paciente a ítems `id` / `text` para cards.
List<Map<String, dynamic>> _flattenSlotsPacientePorDia(Map<String, dynamic> data) {
  if (data['success'] != true) return [];
  final out = <Map<String, dynamic>>[];
  final porDia = data['por_dia'];
  if (porDia is! List) return out;
  for (final day in porDia) {
    if (day is! Map) continue;
    final fecha = day['fecha']?.toString() ?? '';
    void addFranja(List<dynamic>? slots, String franjaLabel) {
      for (final s in slots ?? []) {
        if (s is! Map) continue;
        final hora = s['hora']?.toString() ?? '';
        final idRrsa = s['id_rrhh_servicio_asignado']?.toString() ?? '';
        if (fecha.isEmpty || hora.isEmpty || idRrsa.isEmpty) continue;
        final id = '$idRrsa|$fecha|$hora';
        final fechaTxt = _formatFechaCardSlots(fecha);
        out.add({
          'id': id,
          'text': '$fechaTxt · $franjaLabel · $hora',
        });
      }
    }
    addFranja(day['manana'] as List<dynamic>?, 'Mañana');
    addFranja(day['tarde'] as List<dynamic>?, 'Tarde');
  }
  return out;
}

List<Map<String, dynamic>> _itemsListFromApiJson(Map<String, dynamic> data, String? endpoint) {
  if (_endpointIsSlotsPaciente(endpoint)) {
    return _flattenSlotsPacientePorDia(data);
  }
  dynamic items;
  if (data['results'] != null) {
    items = data['results'];
  } else if (data['data'] != null) {
    if (data['data'] is Map && (data['data'] as Map)['results'] != null) {
      items = (data['data'] as Map)['results'];
    } else if (data['data'] is List) {
      items = data['data'];
    } else {
      items = data['data'];
    }
  } else if (data['items'] != null) {
    items = data['items'];
  } else {
    items = data;
  }
  if (items is! List) return [];
  return items.map<Map<String, dynamic>>((item) {
    if (item is Map) {
      final id = item['id']?.toString() ?? item['value']?.toString();
      final text = item['text']?.toString() ?? item['name']?.toString() ?? item['label']?.toString() ?? id;
      return {'id': id, 'text': text};
    }
    return {'id': item.toString(), 'text': item.toString()};
  }).toList();
}

/// Widget genérico para seleccionar elementos con búsqueda y minicards
/// Puede usarse para efectores, recursos humanos, servicios, etc.
class SearchableCardSelector extends StatefulWidget {
  final String? label;
  final bool required;
  final String? description;
  final String? endpoint;
  final Map<String, dynamic>? params;
  final String? authToken;
  final Function(String? selectedId) onChanged;
  final String? initialValue;
  final IconData? icon;
  final String? emptyMessage;
  final String? noResultsMessage;
  final String? searchHint;
  final bool autoLoad;
  /// Opciones ya cargadas (p. ej. desde caché del formulario). Si no son null, no se dispara auto_load.
  final List<Map<String, dynamic>>? initialOptions;
  /// Se llama cuando se cargaron opciones desde el endpoint, para que el padre pueda cachear.
  final void Function(List<Map<String, dynamic>>)? onOptionsLoaded;
  /// Si false, no se muestra el campo de búsqueda (solo lista de opciones, p. ej. slots de horarios).
  final bool showSearch;
  /// Filtros opcionales declarados en el JSON del wizard (backend-driven).
  /// Estructura esperada: lista de objetos con id/label/type/default_first/source, etc.
  final dynamic filters;

  const SearchableCardSelector({
    Key? key,
    this.label,
    this.required = false,
    this.description,
    this.endpoint,
    this.params,
    this.authToken,
    required this.onChanged,
    this.initialValue,
    this.icon,
    this.emptyMessage,
    this.noResultsMessage,
    this.searchHint,
    this.autoLoad = false,
    this.initialOptions,
    this.onOptionsLoaded,
    this.showSearch = true,
    this.filters,
  }) : super(key: key);

  @override
  _SearchableCardSelectorState createState() => _SearchableCardSelectorState();
}

class _SearchableCardSelectorState extends State<SearchableCardSelector> {
  final TextEditingController _searchController = TextEditingController();
  final FocusNode _searchFocusNode = FocusNode();
  final GlobalKey<FormFieldState<String?>> _formFieldKey = GlobalKey<FormFieldState<String?>>();

  List<Map<String, dynamic>> _efectores = [];
  List<Map<String, dynamic>> _filteredEfectores = [];
  bool _isLoading = false;
  bool _isSearching = false;
  String? _selectedEfectorId;
  String? _selectedEfectorName;
  /// Evita disparar búsqueda al servidor cuando el texto se actualiza por selección de un cuadro (tap).
  bool _isSelectingItem = false;

  // --- Filtros (solo se activan si el field declara `filters`) ---
  final Map<String, String?> _selectedFilters = {};
  List<Map<String, dynamic>> _filterItemsDia = [];
  List<Map<String, dynamic>> _filterItemsFranja = [];
  List<dynamic> _slotsPorDiaRaw = [];

  @override
  void initState() {
    super.initState();
    _selectedEfectorId = widget.initialValue;
    // Si ya tenemos opciones (caché del padre), usarlas y no disparar carga al servidor
    if (widget.initialOptions != null) {
      _efectores = List<Map<String, dynamic>>.from(widget.initialOptions!);
      _filteredEfectores = List<Map<String, dynamic>>.from(widget.initialOptions!);
      _searchController.addListener(_onSearchChanged);
      return;
    }
    // Solo cargar automáticamente si autoLoad es true y la dependencia está satisfecha
    if (widget.autoLoad && _hasAllDependencies()) {
      _loadInitialItems();
    }
    _searchController.addListener(_onSearchChanged);
  }

  bool _hasFilters() {
    return widget.filters is List && (widget.filters as List).isNotEmpty;
  }

  bool _wantsFilter(String id) {
    if (!_hasFilters()) return false;
    for (final f in (widget.filters as List)) {
      if (f is Map && f['id']?.toString() == id) return true;
    }
    return false;
  }

  bool _defaultFirstFor(String id) {
    if (!_hasFilters()) return false;
    for (final f in (widget.filters as List)) {
      if (f is Map && f['id']?.toString() == id) {
        return f['default_first'] == true || f['defaultFirst'] == true;
      }
    }
    return false;
  }

  void _ensureDefaultFilterSelections() {
    if (!_hasFilters()) return;
    if (_wantsFilter('dia') && _defaultFirstFor('dia')) {
      if ((_selectedFilters['dia'] == null || _selectedFilters['dia']!.isEmpty) && _filterItemsDia.isNotEmpty) {
        _selectedFilters['dia'] = _filterItemsDia.first['value']?.toString() ?? _filterItemsDia.first['id']?.toString();
      }
    }
    if (_wantsFilter('franja') && _defaultFirstFor('franja')) {
      if ((_selectedFilters['franja'] == null || _selectedFilters['franja']!.isEmpty) && _filterItemsFranja.isNotEmpty) {
        _selectedFilters['franja'] = _filterItemsFranja.first['value']?.toString() ?? _filterItemsFranja.first['id']?.toString();
      }
    }
  }

  void _buildFiltersFromSlotsResponse(Map<String, dynamic> map) {
    // Enfoque backend-driven: si la API aún no devuelve available_filters, derivamos de por_dia.
    final porDia = map['por_dia'];
    _slotsPorDiaRaw = porDia is List ? porDia : [];

    if (_wantsFilter('dia')) {
      final out = <Map<String, dynamic>>[];
      for (final day in _slotsPorDiaRaw) {
        if (day is! Map) continue;
        final fecha = day['fecha']?.toString() ?? '';
        if (fecha.isEmpty) continue;
        out.add({'value': fecha, 'label': _formatFechaCardSlots(fecha)});
      }
      // dedupe conservando orden
      final seen = <String, bool>{};
      _filterItemsDia = out.where((x) {
        final v = x['value']?.toString() ?? '';
        if (v.isEmpty || seen[v] == true) return false;
        seen[v] = true;
        return true;
      }).toList();
    } else {
      _filterItemsDia = [];
    }

    if (_wantsFilter('franja')) {
      _filterItemsFranja = const [
        {'value': 'manana', 'label': 'Mañana'},
        {'value': 'tarde', 'label': 'Tarde'},
      ];
    } else {
      _filterItemsFranja = [];
    }

    _ensureDefaultFilterSelections();
  }

  List<Map<String, dynamic>> _flattenSlotsPacienteWithFilters() {
    // Si no hay filtros seleccionados, fallback al comportamiento anterior (todo).
    final dia = _selectedFilters['dia'];
    final franja = _selectedFilters['franja'];
    if ((dia == null || dia.isEmpty) && (franja == null || franja.isEmpty)) {
      final map = <String, dynamic>{'success': true, 'por_dia': _slotsPorDiaRaw};
      return _flattenSlotsPacientePorDia(map);
    }
    final out = <Map<String, dynamic>>[];
    for (final day in _slotsPorDiaRaw) {
      if (day is! Map) continue;
      final fecha = day['fecha']?.toString() ?? '';
      if (dia != null && dia.isNotEmpty && fecha != dia) continue;

      void addFranja(List<dynamic>? slots, String franjaLabel) {
        for (final s in slots ?? []) {
          if (s is! Map) continue;
          final hora = s['hora']?.toString() ?? '';
          final idRrsa = s['id_rrhh_servicio_asignado']?.toString() ?? '';
          if (fecha.isEmpty || hora.isEmpty || idRrsa.isEmpty) continue;
          final id = '$idRrsa|$fecha|$hora';
          final fechaTxt = _formatFechaCardSlots(fecha);
          out.add({'id': id, 'text': '$fechaTxt · $franjaLabel · $hora'});
        }
      }

      if (franja == null || franja.isEmpty) {
        addFranja(day['manana'] as List<dynamic>?, 'Mañana');
        addFranja(day['tarde'] as List<dynamic>?, 'Tarde');
      } else if (franja == 'manana') {
        addFranja(day['manana'] as List<dynamic>?, 'Mañana');
      } else if (franja == 'tarde') {
        addFranja(day['tarde'] as List<dynamic>?, 'Tarde');
      }
    }
    return out;
  }

  bool _hasAllDependencies() {
    // Considerar dependencias satisfechas si hay algún param con valor (excl. q, limit)
    if (widget.params == null || widget.params!.isEmpty) return false;
    for (final entry in widget.params!.entries) {
      if (entry.key == 'q' || entry.key == 'limit') continue;
      final v = entry.value;
      if (v != null && v.toString().trim() != '') return true;
    }
    return false;
  }

  @override
  void dispose() {
    _searchController.dispose();
    _searchFocusNode.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    if (!mounted) return;
    // No disparar búsqueda al servidor cuando el texto se puso por tap en un cuadro (solo selección).
    if (_isSelectingItem) return;

    final query = _searchController.text.trim().toLowerCase();

    if (query.isEmpty) {
      if (mounted) {
        setState(() {
          _filteredEfectores = _efectores;
          _isSearching = false;
        });
      }
    } else {
      if (mounted) {
        setState(() {
          _isSearching = true;
          _filteredEfectores = _efectores.where((item) {
            final name = (item['text'] ?? item['name'] ?? '').toString().toLowerCase();
            return name.contains(query);
          }).toList();
        });
      }

      // Solo buscar en el servidor cuando el usuario escribe en el campo, no cuando seleccionó un cuadro.
      if (widget.endpoint != null &&
          query.length >= 2 &&
          !_endpointIsSlotsPaciente(widget.endpoint)) {
        _searchItems(query);
      }
    }
  }

  Future<void> _loadInitialItems() async {
    if (widget.endpoint == null) return;
    
    if (!mounted) return;
    setState(() {
      _isLoading = true;
    });

    try {
      final params = Map<String, dynamic>.from(widget.params ?? {});
      if (!_endpointIsSlotsPaciente(widget.endpoint)) {
        params['limit'] = '5';
      }
      
      final uri = Uri.parse('${AppConfig.apiUrl}${widget.endpoint}');
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

      if (!mounted) return;

      if (response.statusCode == 200) {
        final raw = json.decode(response.body);
        final map = raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
        // Si hay filtros declarados y estamos en slots de paciente, construir filtros y aplicar.
        if (_endpointIsSlotsPaciente(widget.endpoint) && _hasFilters()) {
          _buildFiltersFromSlotsResponse(map);
        }
        final itemsList = _endpointIsSlotsPaciente(widget.endpoint) && _hasFilters()
            ? _flattenSlotsPacienteWithFilters()
            : _itemsListFromApiJson(map, widget.endpoint);

        if (mounted) {
          setState(() {
            _efectores = itemsList;
            _filteredEfectores = itemsList;
            _isLoading = false;
            
            // Si hay un valor inicial, encontrar el nombre
            if (_selectedEfectorId != null) {
              final item = _efectores.firstWhere(
                (e) => e['id']?.toString() == _selectedEfectorId,
                orElse: () => {},
              );
              if (item.isNotEmpty) {
                _selectedEfectorName = item['text']?.toString();
              }
            }
          });
          widget.onOptionsLoaded?.call(itemsList);
        }
      } else {
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _searchItems(String query) async {
    if (widget.endpoint == null) return;
    if (_endpointIsSlotsPaciente(widget.endpoint)) return;

    try {
      final params = Map<String, dynamic>.from(widget.params ?? {});
      params['q'] = query;
      params['limit'] = '50';
      
      final uri = Uri.parse('${AppConfig.apiUrl}${widget.endpoint}');
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

      if (!mounted) return;

      if (response.statusCode == 200) {
        final raw = json.decode(response.body);
        final map = raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
        final itemsList = _itemsListFromApiJson(map, widget.endpoint);

        if (mounted) {
          setState(() {
            _filteredEfectores = itemsList;
          });
        }
      }
    } catch (e) {
      // Error silencioso, mantener resultados locales
    }
  }

  void _selectItem(Map<String, dynamic> item) {
    if (!mounted) return;

    final itemId = item['id']?.toString();
    final itemName = item['text']?.toString();

    _isSelectingItem = true;
    setState(() {
      _selectedEfectorId = itemId;
      _selectedEfectorName = itemName;
      _searchController.text = itemName ?? '';
      _searchFocusNode.unfocus();
    });
    _isSelectingItem = false;

    if (!widget.showSearch) {
      _formFieldKey.currentState?.didChange(itemId);
    }
    widget.onChanged(itemId);
  }

  Widget _buildCardsContent() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Lista de efectores
        if (_isLoading)
          Center(
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: CircularProgressIndicator(),
            ),
          )
        else if (_filteredEfectores.isEmpty)
          Center(
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Text(
                _isSearching
                    ? (widget.noResultsMessage ?? 'No se encontraron resultados')
                    : (widget.emptyMessage ?? 'No hay opciones disponibles'),
                style: TextStyle(color: Colors.grey[600]),
              ),
            ),
          )
        else ...[
          if (!widget.showSearch || !_isSearching || _filteredEfectores.length <= 10)
            SizedBox(
              height: 120,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: _filteredEfectores.length,
                itemBuilder: (context, index) {
                  final efector = _filteredEfectores[index];
                  final isSelected = efector['id']?.toString() == _selectedEfectorId;
                  return Container(
                    width: 140,
                    margin: EdgeInsets.only(right: 12),
                    child: _buildItemCard(efector, isSelected),
                  );
                },
              ),
            )
          else
            Container(
              constraints: BoxConstraints(maxHeight: 300),
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: _filteredEfectores.length,
                itemBuilder: (context, index) {
                  final efector = _filteredEfectores[index];
                  final isSelected = efector['id']?.toString() == _selectedEfectorId;
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8.0),
                    child: _buildItemCard(efector, isSelected),
                  );
                },
              ),
            ),
        ],
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final content = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Label
        if (widget.label != null) ...[
          Text(
            widget.label! + (widget.required ? ' *' : ''),
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
        ],
        if (widget.description != null && widget.description!.isNotEmpty) ...[
          Text(
            widget.description!,
            style: TextStyle(color: Colors.grey[600], fontSize: 12),
          ),
          const SizedBox(height: 8),
        ],
        // Campo de búsqueda (oculto si showSearch es false, p. ej. solo slots)
        if (widget.showSearch)
          TextFormField(
            controller: _searchController,
            focusNode: _searchFocusNode,
            decoration: InputDecoration(
              hintText: widget.searchHint ?? widget.description ?? 'Buscar...',
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              prefixIcon: Icon(Icons.search),
              suffixIcon: _selectedEfectorId != null
                  ? IconButton(
                      icon: Icon(Icons.clear),
                      onPressed: () {
                        if (mounted) {
                          setState(() {
                            _selectedEfectorId = null;
                            _selectedEfectorName = null;
                            _searchController.clear();
                          });
                        }
                        widget.onChanged(null);
                      },
                    )
                  : null,
            ),
            validator: widget.required
                ? (value) => _selectedEfectorId == null ? 'Este campo es requerido' : null
                : null,
          ),
        if (widget.showSearch) const SizedBox(height: 16),
        if (!widget.showSearch && _hasFilters() && _endpointIsSlotsPaciente(widget.endpoint)) ...[
          if (_wantsFilter('dia') && _filterItemsDia.isNotEmpty) ...[
            _buildFilterChipsRow(
              label: 'Día',
              items: _filterItemsDia,
              selectedValue: _selectedFilters['dia'],
              onChanged: (v) {
                setState(() {
                  _selectedFilters['dia'] = v;
                  final itemsList = _flattenSlotsPacienteWithFilters();
                  _efectores = itemsList;
                  _filteredEfectores = itemsList;
                });
              },
            ),
            const SizedBox(height: 8),
          ],
          if (_wantsFilter('franja') && _filterItemsFranja.isNotEmpty) ...[
            _buildFilterChipsRow(
              label: 'Franja',
              items: _filterItemsFranja,
              selectedValue: _selectedFilters['franja'],
              onChanged: (v) {
                setState(() {
                  _selectedFilters['franja'] = v;
                  final itemsList = _flattenSlotsPacienteWithFilters();
                  _efectores = itemsList;
                  _filteredEfectores = itemsList;
                });
              },
            ),
            const SizedBox(height: 8),
          ],
        ],
        // Lista de opciones (cards)
        if (widget.showSearch)
          _buildCardsContent()
        else
          FormField<String?>(
            key: _formFieldKey,
            initialValue: _selectedEfectorId,
            validator: widget.required
                ? (v) => (v == null || v.toString().trim().isEmpty) ? 'Este campo es requerido' : null
                : null,
            builder: (state) => Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildCardsContent(),
                if (state.hasError)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(
                      state.errorText!,
                      style: TextStyle(color: Theme.of(context).colorScheme.error, fontSize: 12),
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
    return content;
  }

  Widget _buildFilterChipsRow({
    required String label,
    required List<Map<String, dynamic>> items,
    required String? selectedValue,
    required void Function(String value) onChanged,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
                color: Colors.grey[700],
              ),
        ),
        const SizedBox(height: 6),
        SizedBox(
          height: 40,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            itemCount: items.length,
            itemBuilder: (context, idx) {
              final it = items[idx];
              final value = it['value']?.toString() ?? it['id']?.toString() ?? '';
              final text = it['label']?.toString() ?? it['text']?.toString() ?? value;
              final selected = value.isNotEmpty && value == selectedValue;
              return Padding(
                padding: const EdgeInsets.only(right: 8.0),
                child: ChoiceChip(
                  label: Text(text, overflow: TextOverflow.ellipsis),
                  selected: selected,
                  onSelected: (_) {
                    if (value.isNotEmpty) onChanged(value);
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildItemCard(Map<String, dynamic> item, bool isSelected) {
    final itemName = item['text']?.toString() ?? 'Sin nombre';
    final defaultIcon = widget.icon ?? Icons.article;
    
    return Card(
      elevation: 0,
      color: isSelected 
          ? Theme.of(context).primaryColor.withOpacity(0.1)
          : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: isSelected 
              ? Theme.of(context).primaryColor
              : Colors.grey[300]!,
          width: isSelected ? 2 : 1,
        ),
      ),
      child: InkWell(
        onTap: () => _selectItem(item),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(12.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (isSelected)
                Icon(
                  Icons.check_circle,
                  color: Theme.of(context).primaryColor,
                  size: 24,
                )
              else
                Icon(
                  defaultIcon,
                  color: Colors.grey[600],
                  size: 24,
                ),
              const SizedBox(height: 8),
              Text(
                itemName,
                textAlign: TextAlign.center,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                  color: isSelected 
                      ? Theme.of(context).primaryColor
                      : Colors.black87,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
