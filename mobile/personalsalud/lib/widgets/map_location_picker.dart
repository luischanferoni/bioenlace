import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:shared/shared.dart';

/// Selector de ubicación sobre Google Maps.
///
/// El usuario toca el mapa o arrastra el marcador; el widget notifica las
/// coordenadas y el padre resuelve país/provincia/localidad contra el backend.
class MapLocationPicker extends StatefulWidget {
  const MapLocationPicker({
    super.key,
    required this.onChanged,
    this.initialLatitude,
    this.initialLongitude,
    this.height = 260,
    this.enabled = true,
  });

  final ValueChanged<LatLng> onChanged;
  final double? initialLatitude;
  final double? initialLongitude;
  final double height;
  final bool enabled;

  @override
  State<MapLocationPicker> createState() => _MapLocationPickerState();
}

class _MapLocationPickerState extends State<MapLocationPicker> {
  /// Centro por defecto: CABA (el usuario mueve el marcador).
  static const LatLng _fallbackCenter = LatLng(-34.603722, -58.381592);

  late LatLng _center;
  LatLng? _marker;
  GoogleMapController? _controller;
  bool _locating = false;

  @override
  void initState() {
    super.initState();
    final lat = widget.initialLatitude;
    final lng = widget.initialLongitude;
    if (lat != null && lng != null) {
      _marker = LatLng(lat, lng);
      _center = _marker!;
    } else {
      _center = _fallbackCenter;
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  void _select(LatLng position) {
    if (!widget.enabled) return;
    setState(() {
      _marker = position;
      _center = position;
    });
    widget.onChanged(position);
  }

  Future<void> _useMyLocation() async {
    if (_locating) return;
    setState(() => _locating = true);
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        if (!mounted) return;
        _showMessage('Habilitá la ubicación para centrar el mapa.');
        return;
      }
      final position = await Geolocator.getCurrentPosition();
      if (!mounted) return;
      final target = LatLng(position.latitude, position.longitude);
      await _controller?.animateCamera(CameraUpdate.newLatLngZoom(target, 16));
      _select(target);
    } catch (error) {
      if (!mounted) return;
      _showMessage('No pudimos obtener tu ubicación.');
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.maybeOf(
      context,
    )?.showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final marker = _marker;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(
            height: widget.height,
            child: AbsorbPointer(
              absorbing: !widget.enabled,
              child: GoogleMap(
                initialCameraPosition: CameraPosition(
                  target: _center,
                  zoom: marker == null ? 11 : 16,
                ),
                onMapCreated: (controller) => _controller = controller,
                onTap: _select,
                myLocationButtonEnabled: false,
                zoomControlsEnabled: false,
                compassEnabled: false,
                mapToolbarEnabled: false,
                markers: marker == null
                    ? const {}
                    : {
                        Marker(
                          markerId: const MarkerId('efector'),
                          position: marker,
                          draggable: widget.enabled,
                          onDragEnd: _select,
                        ),
                      },
              ),
            ),
          ),
        ),
        BioSpacing.gapH(BioSpacing.sm),
        Row(
          children: [
            Expanded(
              child: Text(
                marker == null
                    ? 'Tocá el mapa para ubicar el efector.'
                    : 'Arrastrá el marcador para ajustar la ubicación.',
                style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
              ),
            ),
            TextButton.icon(
              onPressed: widget.enabled && !_locating ? _useMyLocation : null,
              icon: _locating
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.my_location, size: 18),
              label: const Text('Mi ubicación'),
            ),
          ],
        ),
      ],
    );
  }
}
