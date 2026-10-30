import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

abstract interface class SecureKeyValueStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

class _FlutterSecureKeyValueStore implements SecureKeyValueStore {
  const _FlutterSecureKeyValueStore(this._storage);

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

/// Stores bearer tokens in platform secure storage and migrates legacy preferences.
class AuthTokenStore {
  AuthTokenStore({SecureKeyValueStore? secureStorage})
    : _secureStorage =
          secureStorage ??
          const _FlutterSecureKeyValueStore(FlutterSecureStorage());

  static const tokenKey = 'auth_token';
  static final instance = AuthTokenStore();

  final SecureKeyValueStore _secureStorage;

  Future<String?> read() async {
    final secureToken = await _secureStorage.read(tokenKey);
    final prefs = await SharedPreferences.getInstance();

    if (secureToken != null && secureToken.isNotEmpty) {
      await _removeLegacyToken(prefs);
      return secureToken;
    }

    final legacyToken = prefs.getString(tokenKey);
    if (legacyToken == null || legacyToken.isEmpty) {
      return null;
    }

    await _secureStorage.write(tokenKey, legacyToken);
    await _removeLegacyToken(prefs);
    return legacyToken;
  }

  Future<void> write(String token) async {
    if (token.isEmpty) {
      await delete();
      return;
    }

    await _secureStorage.write(tokenKey, token);
    await _removeLegacyToken(await SharedPreferences.getInstance());
  }

  Future<void> delete() async {
    await _removeLegacyToken(await SharedPreferences.getInstance());
    await _secureStorage.delete(tokenKey);
  }

  Future<void> _removeLegacyToken(SharedPreferences prefs) async {
    if (prefs.containsKey(tokenKey) && !await prefs.remove(tokenKey)) {
      throw StateError('No se pudo quitar el token heredado de preferencias.');
    }
  }
}
