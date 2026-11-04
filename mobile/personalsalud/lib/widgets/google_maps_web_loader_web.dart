import 'dart:async';
import 'dart:js_interop';

import 'package:flutter/foundation.dart';
import 'package:web/web.dart' as web;

@JS('google.maps')
external JSAny? get _googleMaps;

/// `MapTypeId` sólo existe cuando la librería terminó de cargar (con
/// `loading=async` el objeto `google.maps` aparece antes, pero vacío).
@JS('google.maps.MapTypeId')
external JSAny? get _googleMapsMapTypeId;

/// Carga la JS API de Google Maps en web (`google.maps`).
///
/// La key se inyecta en el build: `--dart-define=GOOGLE_MAPS_WEB_API_KEY=…`.
/// En Android/iOS no hace nada: el SDK nativo toma la key del manifest/Info.plist.
class GoogleMapsWebLoader {
  /// `GOOGLE_MAPS_WEB_API_KEY` o, como alias, `GOOGLE_MAPS_API_KEY`.
  static const String _key = String.fromEnvironment(
    'GOOGLE_MAPS_WEB_API_KEY',
    defaultValue: String.fromEnvironment('GOOGLE_MAPS_API_KEY'),
  );
  static const Duration _timeout = Duration(seconds: 12);

  static Future<bool>? _pending;
  static bool _loaded = false;

  /// `false` sólo cuando corre en web sin key configurada.
  static bool get isConfigured => !kIsWeb || _key.isNotEmpty;

  static Future<bool> ensureLoaded() {
    if (!kIsWeb) return Future.value(true);
    if (_loaded) return Future.value(true);
    if (_key.isEmpty) return Future.value(false);

    return _pending ??= _load().then((ready) {
      if (!ready) _pending = null; // permite reintentar
      return ready;
    });
  }

  static Future<bool> _load() async {
    // Si quedó un script inyectado que no expuso `google.maps`, lo reemplazamos.
    final existing = web.document.querySelector('script[data-bioenlace-maps]');
    if (existing != null && !_isReady()) {
      existing.remove();
    }
    final alreadyInjected =
        web.document.querySelector('script[data-bioenlace-maps]') != null;

    if (!alreadyInjected) {
      final loaded = Completer<void>();
      final script =
          web.document.createElement('script') as web.HTMLScriptElement
            ..src =
                'https://maps.googleapis.com/maps/api/js'
                '?key=$_key&v=weekly&language=es&region=AR'
            ..async = true
            ..defer = true;
      script.setAttribute('data-bioenlace-maps', 'true');
      script.addEventListener(
        'load',
        ((web.Event _) {
          if (!loaded.isCompleted) loaded.complete();
        }).toJS,
      );
      script.addEventListener(
        'error',
        ((web.Event _) {
          if (!loaded.isCompleted) loaded.complete();
        }).toJS,
      );
      web.document.head!.appendChild(script);

      await loaded.future.timeout(_timeout, onTimeout: () {});
    }

    // El `load` del script puede adelantarse a la inicialización de las clases
    // (`MapTypeId`); esperamos hasta 2 s a que estén disponibles.
    for (var attempt = 0; attempt < 20 && !_isReady(); attempt++) {
      await Future<void>.delayed(const Duration(milliseconds: 100));
    }

    _loaded = _isReady();
    if (!_loaded) {
      debugPrint(
        'Google Maps web no se cargó: revisá GOOGLE_MAPS_WEB_API_KEY, que la '
        '"Maps JavaScript API" esté habilitada y las restricciones de referrer.',
      );
    }

    return _loaded;
  }

  static bool _isReady() {
    try {
      return _googleMaps != null && _googleMapsMapTypeId != null;
    } catch (_) {
      return false;
    }
  }
}
