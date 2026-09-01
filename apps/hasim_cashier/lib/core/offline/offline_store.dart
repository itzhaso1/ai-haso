import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';
import 'package:uuid/uuid.dart';

enum SyncStatus { pending, synced, failed }

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

  List<Map<String, dynamic>> pendingOrders() {
    return _orders.values
        .whereType<String>()
        .map((raw) => Map<String, dynamic>.from(jsonDecode(raw) as Map))
        .where((e) => e['status'] != SyncStatus.synced.name)
        .toList();
  }

  Future<void> markSynced(String localId, {int? serverOrderId}) async {
    final raw = _orders.get(localId);
    if (raw is! String) return;
    final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
    map['status'] = SyncStatus.synced.name;
    map['server_order_id'] = serverOrderId;
    map['synced_at'] = DateTime.now().toIso8601String();
    await _orders.put(localId, jsonEncode(map));
  }

  Future<void> markFailed(String localId, String error) async {
    final raw = _orders.get(localId);
    if (raw is! String) return;
    final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
    map['status'] = SyncStatus.failed.name;
    map['last_error'] = error;
    map['attempts'] = ((map['attempts'] as int?) ?? 0) + 1;
    await _orders.put(localId, jsonEncode(map));
  }

  Future<void> retry(String localId) async {
    final raw = _orders.get(localId);
    if (raw is! String) return;
    final map = Map<String, dynamic>.from(jsonDecode(raw) as Map);
    map['status'] = SyncStatus.pending.name;
    await _orders.put(localId, jsonEncode(map));
  }
}
