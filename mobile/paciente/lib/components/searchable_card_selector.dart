import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared/shared.dart';

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
      if (widget.endpoint != null && query.length >= 2) {
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
      // Cargar items iniciales sin query para mostrar los primeros
      params['limit'] = '5';
      
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
        final data = json.decode(response.body);
        // Manejar diferentes estructuras de respuesta:
        // 1. { "results": [...] }
        // 2. { "data": { "results": [...] } }
        // 3. { "data": [...] }
        // 4. { "items": [...] }
        // 5. [...] (array directo)
        dynamic items;
        if (data['results'] != null) {
          items = data['results'];
        } else if (data['data'] != null) {
          if (data['data'] is Map && data['data']['results'] != null) {
            items = data['data']['results'];
          } else if (data['data'] is List) {
            items = data['data'];
          } else {
            items = data['data'];
          }
        } else if (data['items'] != null) {
          items = data['items'];
        } else if (data is List) {
          items = data;
        } else {
          items = data;
        }
        
        List<Map<String, dynamic>> itemsList = [];
        if (items is List) {
          itemsList = items.map((item) {
            if (item is Map) {
              final id = item['id']?.toString() ?? item['value']?.toString();
              final text = item['text']?.toString() ?? item['name']?.toString() ?? item['label']?.toString() ?? id;
              return {
                'id': id,
                'text': text,
              };
            }
            return {'id': item.toString(), 'text': item.toString()};
          }).toList();
        }
        
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
        final data = json.decode(response.body);
        // Manejar diferentes estructuras de respuesta:
        // 1. { "results": [...] }
        // 2. { "data": { "results": [...] } }
        // 3. { "data": [...] }
        // 4. { "items": [...] }
        // 5. [...] (array directo)
        dynamic items;
        if (data['results'] != null) {
          items = data['results'];
        } else if (data['data'] != null) {
          if (data['data'] is Map && data['data']['results'] != null) {
            items = data['data']['results'];
          } else if (data['data'] is List) {
            items = data['data'];
          } else {
            items = data['data'];
          }
        } else if (data['items'] != null) {
          items = data['items'];
        } else if (data is List) {
          items = data;
        } else {
          items = data;
        }
        
        List<Map<String, dynamic>> itemsList = [];
        if (items is List) {
          itemsList = items.map((item) {
            if (item is Map) {
              final id = item['id']?.toString() ?? item['value']?.toString();
              final text = item['text']?.toString() ?? item['name']?.toString() ?? item['label']?.toString() ?? id;
              return {
                'id': id,
                'text': text,
              };
            }
            return {'id': item.toString(), 'text': item.toString()};
          }).toList();
        }
        
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

  Widget _buildItemCard(Map<String, dynamic> item, bool isSelected) {
    final itemName = item['text']?.toString() ?? 'Sin nombre';
    final defaultIcon = widget.icon ?? Icons.article;
    
    return Card(
      elevation: isSelected ? 4 : 1,
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
                maxLines: 2,
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
