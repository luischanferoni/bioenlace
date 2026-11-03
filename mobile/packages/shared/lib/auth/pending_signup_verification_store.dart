import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'auth_token_store.dart';

/// Keeps a Didit verification ID securely while identity creation is retryable.
class PendingSignupVerificationStore {
  PendingSignupVerificationStore({SecureKeyValueStore? secureStorage})
    : _secureStorage =
          secureStorage ??
          const _FlutterSecureKeyValueStore(FlutterSecureStorage());

  static const verificationKey = 'pending_signup_verification_id';
  static final instance = PendingSignupVerificationStore();

  final SecureKeyValueStore _secureStorage;

  Future<String?> read() async {
    final value = await _secureStorage.read(verificationKey);
    return value == null || value.isEmpty ? null : value;
  }

  Future<void> write(String verificationId) async {
    final normalizedId = verificationId.trim();
    if (normalizedId.isEmpty) {
      throw ArgumentError.value(
        verificationId,
        'verificationId',
        'No puede estar vacío.',
      );
    }
    await _secureStorage.write(verificationKey, normalizedId);
  }

  Future<void> delete() => _secureStorage.delete(verificationKey);
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
