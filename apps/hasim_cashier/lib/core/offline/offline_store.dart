import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';
import 'package:uuid/uuid.dart';

enum SyncStatus { pending, syncing, synced, failed }

/// Local-first offline store for catalog cache + pending POS orders.
class OfflineStore {
  OfflineStore._();
  static final OfflineStore instance = OfflineStore._();

  static const catalogBox = 'cashier_catalog';
  static const ordersBox = 'cashier_pending_orders';

  late Box _catalog;
  late Box _orders;
  final _uuid = const Uuid();

  Future<void> init() async {
    _catalog = await Hive.openBox(catalogBox);
    _orders = await Hive.openBox(ordersBox);
  }

  Future<void> cacheCatalog(List<Map<String, dynamic>> items) async {
    await _catalog.put('items', jsonEncode(items));
    await _catalog.put('cached_at', DateTime.now().toIso8601String());
  }

  List<Map<String, dynamic>> readCatalog() {
    final raw = _catalog.get('items');
    if (raw is! String || raw.isEmpty) return const [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return const [];
    return decoded
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  Future<void> cacheCategories(List<Map<String, dynamic>> categories) async {
    await _catalog.put('categories', jsonEncode(categories));
  }

  List<Map<String, dynamic>> readCategories() {
    final raw = _catalog.get('categories');
    if (raw is! String || raw.isEmpty) return const [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return const [];
    return decoded
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  String? catalogCachedAt() => _catalog.get('cached_at') as String?;

  Future<String> enqueueOrder(Map<String, dynamic> payload) async {
    final id = _uuid.v4();
    final clientReference = payload['client_reference'] as String? ?? id;
    final record = {
      'local_id': id,
      'client_reference': clientReference,
      'payload': {
        ...payload,
        'client_reference': clientReference,
      },
      'status': SyncStatus.pending.name,
      'attempts': 0,
      'last_error': null,
      'created_at': DateTime.now().toIso8601String(),
    };
    await _orders.put(id, jsonEncode(record));
    return id;
  }

  List<Map<String, dynamic>> allOrderRecords() {
    return _orders.values
        .whereType<String>()
        .map((raw) => Map<String, dynamic>.from(jsonDecode(raw) as Map))
        .toList()
      ..sort((a, b) => '${b['created_at']}'.compareTo('${a['created_at']}'));
  }

  List<Map<String, dynamic>> pendingOrders() {
    return allOrderRecords()
        .where(
          (e) =>
              e['status'] == SyncStatus.pending.name ||
              e['status'] == SyncStatus.failed.name ||
              e['status'] == SyncStatus.syncing.name,
        )
        .toList();
  }

  int pendingCount() => pendingOrders().length;

  Future<void> markSyncing(String localId) async {
    final raw = _orders.get(localId);
    if (raw is! String) return;
    final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
    map['status'] = SyncStatus.syncing.name;
    await _orders.put(localId, jsonEncode(map));
  }

  Future<void> markSynced(String localId, {int? serverOrderId}) async {
    final raw = _orders.get(localId);
    if (raw is! String) return;
    final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
    map['status'] = SyncStatus.synced.name;
    map['server_order_id'] = serverOrderId;
    map['synced_at'] = DateTime.now().toIso8601String();
    map['last_error'] = null;
    await _orders.put(localId, jsonEncode(map));
  }

  Future<void> markFailed(String localId, String error) async {
    final raw = _orders.get(localId);
    if (raw is! String) return;
    final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
    map['status'] = SyncStatus.failed.name;
    map['last_error'] = error;
    map['attempts'] = ((map['attempts'] as num?)?.toInt() ?? 0) + 1;
    await _orders.put(localId, jsonEncode(map));
  }

  Future<void> retry(String localId) async {
    final raw = _orders.get(localId);
    if (raw is! String) return;
    final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
    map['status'] = SyncStatus.pending.name;
    await _orders.put(localId, jsonEncode(map));
  }

  /// Never silently delete failed/pending — only prune old synced rows.
  Future<int> pruneSynced({Duration olderThan = const Duration(days: 7)}) async {
    final cutoff = DateTime.now().subtract(olderThan);
    var removed = 0;
    for (final key in _orders.keys.toList()) {
      final raw = _orders.get(key);
      if (raw is! String) continue;
      final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      if (map['status'] != SyncStatus.synced.name) continue;
      final syncedAt = DateTime.tryParse('${map['synced_at'] ?? ''}');
      if (syncedAt != null && syncedAt.isBefore(cutoff)) {
        await _orders.delete(key);
        removed++;
      }
    }
    return removed;
  }
}
