import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/offline/offline_store.dart';

@immutable
class CartLine {
  const CartLine({
    required this.menuItemId,
    required this.name,
    required this.unitPrice,
    required this.quantity,
    this.note,
  });

  final int menuItemId;
  final String name;
  final double unitPrice;
  final int quantity;
  final String? note;

  double get lineTotal => unitPrice * quantity;

  CartLine copyWith({int? quantity, String? note}) => CartLine(
        menuItemId: menuItemId,
        name: name,
        unitPrice: unitPrice,
        quantity: quantity ?? this.quantity,
        note: note ?? this.note,
      );
}

enum OrderChannel { table, takeaway, delivery }

extension OrderChannelLabel on OrderChannel {
  String get labelAr => switch (this) {
        OrderChannel.table => 'طاولة',
        OrderChannel.takeaway => 'خارجي',
        OrderChannel.delivery => 'توصيل',
      };
}

class CartState {
  const CartState({
    this.lines = const [],
    this.channel = OrderChannel.takeaway,
    this.tableId,
    this.customerId,
    this.discountAmount = 0,
    this.notes,
    this.taxRate = 0,
  });

  final List<CartLine> lines;
  final OrderChannel channel;
  final int? tableId;
  final int? customerId;
  final double discountAmount;
  final String? notes;
  final double taxRate;

  double get subtotal =>
      lines.fold<double>(0, (sum, line) => sum + line.lineTotal);

  double get taxAmount {
    final taxable = (subtotal - discountAmount).clamp(0, double.infinity);
    return taxable * (taxRate / 100);
  }

  double get total =>
      (subtotal - discountAmount + taxAmount).clamp(0, double.infinity);

  CartState copyWith({
    List<CartLine>? lines,
    OrderChannel? channel,
    int? tableId,
    int? customerId,
    double? discountAmount,
    String? notes,
    double? taxRate,
    bool clearTable = false,
    bool clearCustomer = false,
  }) {
    return CartState(
      lines: lines ?? this.lines,
      channel: channel ?? this.channel,
      tableId: clearTable ? null : (tableId ?? this.tableId),
      customerId: clearCustomer ? null : (customerId ?? this.customerId),
      discountAmount: discountAmount ?? this.discountAmount,
      notes: notes ?? this.notes,
      taxRate: taxRate ?? this.taxRate,
    );
  }
}

class CartController extends StateNotifier<CartState> {
  CartController() : super(const CartState());

  void setTaxRate(double rate) => state = state.copyWith(taxRate: rate);

  void setChannel(OrderChannel channel) {
    state = state.copyWith(
      channel: channel,
      clearTable: channel != OrderChannel.table,
    );
  }

  void setTable(int? tableId) => state = state.copyWith(tableId: tableId);

  void setCustomer(int? customerId) => state = customerId == null
      ? state.copyWith(clearCustomer: true)
      : state.copyWith(customerId: customerId);

  void addItem({
    required int menuItemId,
    required String name,
    required double unitPrice,
  }) {
    final existingIndex =
        state.lines.indexWhere((line) => line.menuItemId == menuItemId);
    if (existingIndex >= 0) {
      final lines = [...state.lines];
      final current = lines[existingIndex];
      lines[existingIndex] = current.copyWith(quantity: current.quantity + 1);
      state = state.copyWith(lines: lines);
      return;
    }
    state = state.copyWith(lines: [
      ...state.lines,
      CartLine(
        menuItemId: menuItemId,
        name: name,
        unitPrice: unitPrice,
        quantity: 1,
      ),
    ]);
  }

  void setQuantity(int menuItemId, int quantity) {
    if (quantity <= 0) {
      removeItem(menuItemId);
      return;
    }
    final lines = state.lines
        .map((line) => line.menuItemId == menuItemId
            ? line.copyWith(quantity: quantity)
            : line)
        .toList();
    state = state.copyWith(lines: lines);
  }

  void removeItem(int menuItemId) {
    state = state.copyWith(
      lines: state.lines.where((line) => line.menuItemId != menuItemId).toList(),
    );
  }

  void setDiscount(double amount) =>
      state = state.copyWith(discountAmount: amount.clamp(0, double.infinity));

  void setNotes(String? notes) => state = state.copyWith(notes: notes);

  void clear() => state = CartState(taxRate: state.taxRate);

  Map<String, dynamic> toOrderPayload({required String clientReference}) {
    return {
      'order_type': switch (state.channel) {
        OrderChannel.table => 'table',
        OrderChannel.takeaway => 'takeaway',
        OrderChannel.delivery => 'delivery',
      },
      if (state.channel == OrderChannel.table && state.tableId != null)
        'dining_table_id': state.tableId,
      if (state.customerId != null) 'customer_id': state.customerId,
      'discount_amount': state.discountAmount,
      if (state.notes != null && state.notes!.isNotEmpty) 'notes': state.notes,
      'client_reference': clientReference,
      'items': state.lines
          .map((line) => {
                'pos_menu_item_id': line.menuItemId,
                'quantity': line.quantity,
              })
          .toList(),
    };
  }
}

final cartControllerProvider =
    StateNotifierProvider<CartController, CartState>((ref) => CartController());

final catalogItemsProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final api = ref.watch(cashierApiProvider);
  final workspaceId = ref.watch(workspaceIdProvider);
  try {
    final data = await api.get('/catalog/items', query: {'per_page': 100});
    final items = data['items'];
    if (items is List) {
      final list = items
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
      await OfflineStore.instance.cacheCatalog(list, workspaceId: workspaceId);
      return list;
    }
  } catch (_) {
    final offline = OfflineStore.instance.readCatalog(workspaceId: workspaceId);
    if (offline.isNotEmpty) return offline;
  }
  return OfflineStore.instance.readCatalog(workspaceId: workspaceId);
});

final categoriesProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final api = ref.watch(cashierApiProvider);
  final workspaceId = ref.watch(workspaceIdProvider);
  try {
    final data = await api.get('/catalog/categories');
    final categories = data['categories'];
    if (categories is List) {
      final list = categories
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
      await OfflineStore.instance.cacheCategories(
        list,
        workspaceId: workspaceId,
      );
      return list;
    }
  } catch (_) {
    final offline =
        OfflineStore.instance.readCategories(workspaceId: workspaceId);
    if (offline.isNotEmpty) return offline;
  }
  return OfflineStore.instance.readCategories(workspaceId: workspaceId);
});
