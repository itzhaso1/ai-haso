import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../core/api/cashier_api.dart';
import '../../core/auth/auth_controller.dart';
import '../../core/offline/conflict_strategy.dart';
import '../../core/network/cashier_link.dart';
import '../../core/permissions/cashier_permissions.dart';
import '../../core/permissions/permissions_provider.dart';
import '../../core/pos/pos_labels.dart';
import '../../core/printing/printer_service.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/widgets/hasim_widgets.dart';
import 'table_action_wizards.dart';
import 'table_add_order_sheet.dart';
import 'table_order_editor.dart';
import 'table_workspace.dart';

/// Full-screen table workspace — mirrors Laravel `tables/show`.
class TableDetailScreen extends ConsumerStatefulWidget {
  const TableDetailScreen({super.key, required this.tableId});

  final int tableId;

  @override
  ConsumerState<TableDetailScreen> createState() => _TableDetailScreenState();
}

class _TableDetailScreenState extends ConsumerState<TableDetailScreen> {
  Map<String, dynamic>? _detail;
  List<Map<String, dynamic>> _allTables = const [];
  var _loading = true;
  var _closing = false;
  String? _error;
  String _filter = 'all';
  final _search = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  int? get _sessionId => (_detail?['session_id'] as num?)?.toInt();

  bool get _hasSession => _sessionId != null;

  String get _customerLabel {
    final fromTable = _detail?['customer_name'];
    if (fromTable is String && fromTable.trim().isNotEmpty) {
      return fromTable.trim();
    }
    for (final order in _orders) {
      final customer = order['customer'];
      if (customer is Map) {
        final name = customer['name'];
        if (name != null && '$name'.trim().isNotEmpty) return '$name'.trim();
      }
    }
    return '—';
  }

  List<Map<String, dynamic>> get _orders {
    final raw = _detail?['orders'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  List<Map<String, dynamic>> get _filteredOrders {
    final q = _search.text.trim().toLowerCase();
    return _orders.where((order) {
      final status = order['pos_status'] as String? ?? '';
      final paid = order['payment_status'] == 'paid';
      final key = status == 'cancelled'
          ? 'cancelled'
          : (paid ? 'paid' : 'open');
      if (_filter != 'all' && _filter != key) return false;
      if (q.isEmpty) return true;
      final hay =
          '${order['order_number']}|${order['customer']?['name']}|$status'
              .toLowerCase();
      return hay.contains(q);
    }).toList();
  }

  List<Map<String, dynamic>> get _splitItems {
    final items = <Map<String, dynamic>>[];
    for (final order in _orders) {
      if (order['pos_status'] == 'cancelled') continue;
      final orderItems = order['items'];
      if (orderItems is! List) continue;
      for (final item in orderItems.whereType<Map>()) {
        items.add({
          'order_item_id': item['id'],
          'name':
              '${item['product_name']}${item['variant_name'] != null ? ' - ${item['variant_name']}' : ''}',
          'quantity': (item['quantity'] as num?)?.toInt() ?? 1,
          'unit_price': (item['unit_price'] as num?)?.toDouble() ?? 0,
          'total': (item['total_amount'] as num?)?.toDouble() ?? 0,
          'selected_qty': 0,
        });
      }
    }
    return items;
  }

  bool get _canManageTables {
    final perms = ref.read(cashierPermissionsProvider);
    if (perms.isNotEmpty) return CashierPermissions.canManageTables(perms);
    final session = ref.read(authControllerProvider).valueOrNull;
    return CashierPermissions.canManageTables(session?.permissions);
  }

  Future<bool> _ensureOnline() async {
    if (ConflictStrategy.forDomain('table_action') !=
        ConflictPolicy.requireOnline) {
      return true;
    }
    final link = ref.read(cashierLinkProvider);
    if (!link.allowMutations) {
      if (!mounted) return false;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'عمليات الطاولات تتطلب اتصالًا بالخادم (Laravel مصدر الحقيقة).',
          ),
        ),
      );
      return false;
    }
    return true;
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final detail = await ref
          .read(cashierApiProvider)
          .get('/tables/${widget.tableId}');
      final tablesData = await ref.read(cashierApiProvider).get('/tables');
      final list = <Map<String, dynamic>>[];
      if (tablesData['tables'] is List) {
        for (final t in tablesData['tables'] as List) {
          if (t is Map) list.add(Map<String, dynamic>.from(t));
        }
      }
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _allTables = list;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.message;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString();
      });
    }
  }

  String _durationLabel() {
    final opened = _detail?['opened_at'] as String?;
    if (opened == null) return '—';
    final at = DateTime.tryParse(opened);
    if (at == null) return '—';
    final d = DateTime.now().difference(at.toLocal());
    final h = d.inHours.toString().padLeft(2, '0');
    final m = (d.inMinutes % 60).toString().padLeft(2, '0');
    final s = (d.inSeconds % 60).toString().padLeft(2, '0');
    return '$h:$m:$s';
  }

  bool _canMutateOrder(Map<String, dynamic> order) {
    final status = order['pos_status'] as String?;
    // Mirror Laravel assertOrderMutable: not cancelled/completed/paid/invoiced.
    return status != 'cancelled' &&
        status != 'completed' &&
        order['payment_status'] != 'paid' &&
        order['pos_cashier_invoice_id'] == null;
  }

  String _openedAtLabel() {
    final opened = _detail?['opened_at'] as String?;
    if (opened == null || opened.isEmpty) return '—';
    final at = DateTime.tryParse(opened);
    if (at == null) return opened;
    final local = at.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${local.year}-${two(local.month)}-${two(local.day)} '
        '${two(local.hour)}:${two(local.minute)}';
  }

  Future<void> _showQr() async {
    if (!await _ensureOnline()) return;
    final url = _detail?['menu_url'] as String?;
    final token = _detail?['qr_token'] as String?;
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('QR المنيو'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              url ?? 'لا يوجد رابط QR لهذه الطاولة.',
              style: const TextStyle(fontSize: 12),
            ),
            if (token != null) ...[
              const SizedBox(height: 8),
              Text(
                'Token: $token',
                style: const TextStyle(fontSize: 11, color: HasimColors.muted),
              ),
            ],
          ],
        ),
        actions: [
          if (url != null)
            TextButton(
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: url));
                if (!ctx.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('تم نسخ رابط المنيو.')),
                );
              },
              child: const Text('نسخ الرابط'),
            ),
          if (_canManageTables)
            TextButton(
              onPressed: () async {
                Navigator.pop(ctx);
                await _regenerateQr();
              },
              child: const Text('تجديد QR'),
            ),
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('إغلاق'),
          ),
        ],
      ),
    );
  }

  Future<void> _regenerateQr() async {
    if (!_canManageTables) return;
    if (!await _ensureOnline()) return;
    try {
      final data = await ref
          .read(cashierApiProvider)
          .post('/tables/${widget.tableId}/qr/regenerate');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            data['menu_url'] != null
                ? 'تم تجديد QR.\n${data['menu_url']}'
                : 'تم تجديد QR.',
          ),
        ),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _openSession() async {
    if (!_canManageTables) return;
    if (!await _ensureOnline()) return;
    try {
      await ref
          .read(cashierApiProvider)
          .post('/tables/${widget.tableId}/sessions/open');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم فتح جلسة الطاولة.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _addOrder() async {
    if (!_hasSession) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('افتح الجلسة أولًا قبل إضافة طلب.')),
      );
      return;
    }
    if (!await _ensureOnline()) return;
    final created = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (ctx) => TableAddOrderSheet(
        tableId: widget.tableId,
        tableName: '${_detail?['name'] ?? 'الطاولة'}',
      ),
    );
    if (created == null) return;
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'تم حفظ الطلب #${created['order_number'] ?? created['id']} على الطاولة.',
        ),
      ),
    );
    await _load();
  }

  Future<void> _editOrder(Map<String, dynamic> order) async {
    if (!_canMutateOrder(order)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'لا يمكن تعديل طلب مدفوع أو مرتبط بفاتورة أو مكتمل/ملغي.',
          ),
        ),
      );
      return;
    }
    if (!await _ensureOnline()) return;
    final updated = await showDialog<Map<String, dynamic>>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => TableOrderEditorDialog(order: order),
    );
    if (updated == null) return;
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('تم حفظ تعديلات الطلب.')),
    );
    await _load();
  }

  Future<void> _deleteOrder(Map<String, dynamic> order) async {
    if (!_canMutateOrder(order)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'لا يمكن حذف طلب مدفوع أو مرتبط بفاتورة أو مكتمل/ملغي.',
          ),
        ),
      );
      return;
    }
    if (!await _ensureOnline()) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('هل أنت متأكد من حذف هذا الطلب؟'),
        content: Text(
          'سيتم حذف الطلب #${order['order_number'] ?? order['id']} من الطاولة.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: HasimColors.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('حذف الطلب'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    final id = (order['id'] as num?)?.toInt();
    if (id == null) return;
    try {
      await ref.read(cashierApiProvider).delete('/orders/$id');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم حذف الطلب.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _editNote() async {
    if (!_hasSession) return;
    if (!await _ensureOnline()) return;
    final controller = TextEditingController(text: '${_detail?['notes'] ?? ''}');
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('ملاحظة الجلسة'),
        content: TextField(controller: controller, maxLines: 4),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(cashierApiProvider).post(
        '/tables/${widget.tableId}/sessions/$_sessionId/note',
        data: {'notes': controller.text.trim()},
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _applyDiscount() async {
    if (!_hasSession) return;
    final perms = ref.read(cashierPermissionsProvider);
    if (!CashierPermissions.canDiscount(perms) && perms.isNotEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية تطبيق خصم.')),
      );
      return;
    }
    if (!await _ensureOnline()) return;
    final controller = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('خصم الجلسة'),
        content: TextField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'مبلغ الخصم'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('تطبيق'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    final amount = double.tryParse(controller.text.trim()) ?? -1;
    if (amount < 0) return;
    try {
      await ref.read(cashierApiProvider).post(
        '/tables/${widget.tableId}/sessions/$_sessionId/discount',
        data: {'discount_amount': amount},
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _transferOrMerge({required bool merge}) async {
    if (!_hasSession || !_canManageTables) return;
    if (!await _ensureOnline()) return;
    final others = _allTables
        .where((t) => t['id'] != widget.tableId)
        .map((t) => Map<String, dynamic>.from(t))
        .toList();
    final targetId = await showDialog<int>(
      context: context,
      builder: (ctx) => TableTransferWizard(
        title: merge ? 'دمج الطاولات' : 'نقل الطاولة',
        currentTableName: '${_detail?['name'] ?? ''}',
        candidates: others,
        confirmLabel: merge ? 'تأكيد الدمج' : 'تأكيد النقل',
      ),
    );
    if (targetId == null) return;
    final path = merge ? 'merge' : 'transfer';
    try {
      await ref.read(cashierApiProvider).post(
        '/tables/${widget.tableId}/sessions/$_sessionId/$path',
        data: {'target_table_id': targetId},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(merge ? 'تم الدمج.' : 'تم النقل.')),
      );
      if (merge) {
        closeTableWorkspace(ref);
      } else {
        openTableWorkspace(ref, targetId);
        await _load();
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _splitBill() async {
    if (!_hasSession || !_canManageTables) return;
    if (!await _ensureOnline()) return;
    final items = _splitItems;
    if (items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا توجد أصناف لتقسيم الحساب.')),
      );
      return;
    }
    final total = ((_detail?['total'] as num?) ?? 0).toDouble();
    final selected = await showDialog<List<Map<String, dynamic>>>(
      context: context,
      builder: (ctx) => SplitBillWizard(items: items, sessionTotal: total),
    );
    if (selected == null || selected.isEmpty) return;
    final selectedById = <int, int>{};
    for (final row in selected) {
      selectedById[(row['order_item_id'] as num).toInt()] =
          (row['quantity'] as num).toInt();
    }
    final groupA = <Map<String, dynamic>>[];
    final groupB = <Map<String, dynamic>>[];
    for (final item in items) {
      final id = (item['order_item_id'] as num).toInt();
      final maxQty = (item['quantity'] as num).toInt();
      final qtyA = selectedById[id] ?? 0;
      final qtyB = maxQty - qtyA;
      if (qtyA > 0) groupA.add({'order_item_id': id, 'quantity': qtyA});
      if (qtyB > 0) groupB.add({'order_item_id': id, 'quantity': qtyB});
    }
    if (groupA.isEmpty || groupB.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('يجب فصل جزء مع الإبقاء على جزء (حسابان على الأقل).'),
        ),
      );
      return;
    }
    try {
      await ref.read(cashierApiProvider).post(
        '/tables/${widget.tableId}/sessions/$_sessionId/split',
        data: {
          'groups': [
            {'items': groupA},
            {'items': groupB},
          ],
        },
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _closeSession() async {
    if (!_hasSession || !_canManageTables || _closing) return;
    if (!await _ensureOnline()) return;
    final result = await showDialog<CloseTableResult>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => CloseTableFlow(
        tableName: '${_detail?['name'] ?? ''}',
        subtotal: ((_detail?['subtotal'] as num?) ?? 0).toDouble(),
        discount: ((_detail?['discount_amount'] as num?) ?? 0).toDouble(),
        tax: ((_detail?['tax_amount'] as num?) ?? 0).toDouble(),
        total: ((_detail?['total'] as num?) ?? 0).toDouble(),
        ordersCount: (_detail?['orders_count'] as num?)?.toInt() ??
            _orders.where((o) => o['pos_status'] != 'cancelled').length,
        orders: _orders
            .where((o) => o['pos_status'] != 'cancelled')
            .map(
              (o) => {
                'label': '#${o['order_number'] ?? o['id']}',
                'total': ((o['total_amount'] as num?) ?? 0).toDouble(),
              },
            )
            .toList(),
      ),
    );
    if (result == null) return;
    setState(() => _closing = true);
    final idempotencyKey = const Uuid().v4();
    try {
      final data = await ref.read(cashierApiProvider).post(
        '/tables/${widget.tableId}/sessions/$_sessionId/close',
        data: {
          if (result.paymentMethod != null)
            'payment_method': result.paymentMethod,
        },
        idempotencyKey: idempotencyKey,
      );
      if (!mounted) return;
      final invoice = data['invoice'] is Map
          ? Map<String, dynamic>.from(data['invoice'] as Map)
          : null;
      if (invoice != null) {
        await _afterCloseInvoiceDialog(invoice);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تم إغلاق الجلسة بدون فواتير.')),
        );
      }
      closeTableWorkspace(ref);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _closing = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;
      setState(() => _closing = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _afterCloseInvoiceDialog(Map<String, dynamic> invoice) async {
    final choice = await showDialog<String>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        title: const Text('تم إغلاق الطاولة'),
        content: Text(
          'تم إنشاء الفاتورة ${invoice['invoice_number'] ?? invoice['id']}\n'
          'الإجمالي: ${((invoice['total_amount'] as num?) ?? 0).toStringAsFixed(2)}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, 'skip'),
            child: const Text('تم بدون طباعة'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, 'print'),
            child: const Text('طباعة الفاتورة'),
          ),
        ],
      ),
    );
    if (choice != 'print') return;
    try {
      final show = await ref
          .read(cashierApiProvider)
          .get('/invoices/${invoice['id']}');
      final full = show['invoice'] is Map
          ? Map<String, dynamic>.from(show['invoice'] as Map)
          : invoice;
      final printer = await ref.read(printerServiceFutureProvider.future);
      final result = await printer.printInvoice(full);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.success ? 'تمت الطباعة.' : result.message,
          ),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _cancelSession() async {
    if (!_hasSession || !_canManageTables) return;
    if (!await _ensureOnline()) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('إلغاء الطاولة؟'),
        content: const Text('سيتم إلغاء الجلسة وكل الطلبات غير المفوترة.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('رجوع'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: HasimColors.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('إلغاء الطاولة'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref
          .read(cashierApiProvider)
          .post('/tables/${widget.tableId}/sessions/$_sessionId/cancel');
      closeTableWorkspace(ref);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null || _detail == null) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: HsEmpty(
          title: 'تعذر فتح الطاولة',
          subtitle: _error,
          actionLabel: 'إعادة المحاولة',
          onAction: _load,
        ),
      );
    }

    final occupied = _detail!['status'] == 'occupied';
    final width = MediaQuery.sizeOf(context).width;
    final isWide = width >= 1000;

    return Column(
      children: [
        Material(
          color: HasimColors.surface,
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 8, 8, 8),
              child: Row(
                children: [
                  IconButton(
                    tooltip: 'رجوع للطاولات',
                    onPressed: () => closeTableWorkspace(ref),
                    icon: const Icon(Icons.arrow_forward),
                  ),
                  Expanded(
                    child: Text(
                      '${_detail!['name']}',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: _load,
                    icon: const Icon(Icons.refresh),
                  ),
                ],
              ),
            ),
          ),
        ),
        _identityStrip(occupied),
        Expanded(
          child: isWide
              ? Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // RTL: first child = right = table info (larger share).
                    Expanded(
                      flex: 7,
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                        children: [
                          _infoCard(occupied),
                          const SizedBox(height: 10),
                          _actionsCard(),
                        ],
                      ),
                    ),
                    const VerticalDivider(width: 1),
                    // Orders panel — smaller share.
                    Expanded(
                      flex: 3,
                      child: _ordersPanel(),
                    ),
                  ],
                )
              : ListView(
                  padding: const EdgeInsets.all(12),
                  children: [
                    _infoCard(occupied),
                    const SizedBox(height: 10),
                    _actionsCard(),
                    const SizedBox(height: 10),
                    SizedBox(
                      height: MediaQuery.sizeOf(context).height * 0.42,
                      child: _ordersPanel(),
                    ),
                  ],
                ),
        ),
      ],
    );
  }

  Widget _identityStrip(bool occupied) {
    return Material(
      color: HasimColors.surface,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: HasimColors.border)),
        ),
        child: Wrap(
          spacing: 16,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            occupied
                ? HsBadge.occupied(
                    PosLabels.tableStatus(_detail!['status'] as String?),
                  )
                : HsBadge.available(
                    PosLabels.tableStatus(_detail!['status'] as String?),
                  ),
            _identityChip('العميل', _customerLabel),
            _identityChip(
              'الإجمالي',
              ((_detail!['total'] as num?) ?? 0).toStringAsFixed(2),
              highlight: true,
            ),
            _identityChip(
              'الجلسة',
              _hasSession ? 'مفتوحة · ${_durationLabel()}' : 'لا توجد جلسة',
            ),
          ],
        ),
      ),
    );
  }

  Widget _identityChip(String label, String value, {bool highlight = false}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: HasimColors.muted,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w900,
            color: highlight ? HasimColors.ctaDark : HasimColors.ink,
          ),
        ),
      ],
    );
  }

  Widget _infoCard(bool occupied) {
    return HsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'معلومات الطاولة',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${_detail!['name']}',
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              occupied
                  ? HsBadge.occupied(
                      PosLabels.tableStatus(_detail!['status'] as String?),
                    )
                  : HsBadge.available(
                      PosLabels.tableStatus(_detail!['status'] as String?),
                    ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            'العميل: $_customerLabel',
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            _hasSession
                ? 'جلسة مفتوحة · المدة ${_durationLabel()}'
                : 'لا توجد جلسة نشطة',
            style: const TextStyle(fontSize: 12, color: HasimColors.muted),
          ),
          if (_hasSession) ...[
            const SizedBox(height: 4),
            Text(
              'وقت الفتح: ${_openedAtLabel()}',
              style: const TextStyle(fontSize: 12, color: HasimColors.muted),
            ),
          ],
          if ((_detail!['notes'] as String?)?.isNotEmpty == true) ...[
            const SizedBox(height: 4),
            Text(
              'ملاحظة: ${_detail!['notes']}',
              style: const TextStyle(fontSize: 12),
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _stat(
                  '${_detail!['orders_count'] ?? _detail!['open_orders_count'] ?? 0}',
                  'طلبات',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _stat('${_detail!['items_count'] ?? 0}', 'أصناف'),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _stat(
                  (( _detail!['total'] as num?) ?? 0).toStringAsFixed(2),
                  'الإجمالي',
                  highlight: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _moneyRow('المجموع الفرعي', (_detail!['subtotal'] as num?) ?? 0),
          _moneyRow('الخصم', (_detail!['discount_amount'] as num?) ?? 0),
          _moneyRow('الضريبة', (_detail!['tax_amount'] as num?) ?? 0),
          _moneyRow('الإجمالي النهائي', (_detail!['total'] as num?) ?? 0,
              bold: true),
          if (_hasSession) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: HsPrimaryButton(
                    label: '+ إضافة طلب',
                    onPressed: _addOrder,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: HsOutlineButton(
                    label: 'إغلاق الطاولة',
                    onPressed: _closeSession,
                  ),
                ),
              ],
            ),
          ] else if (_canManageTables) ...[
            const SizedBox(height: 12),
            HsPrimaryButton(label: 'فتح جلسة', onPressed: _openSession),
          ],
        ],
      ),
    );
  }

  Widget _stat(String value, String label, {bool highlight = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: HasimColors.surfaceSoft,
        borderRadius: BorderRadius.circular(HasimRadius.md),
        border: Border.all(color: HasimColors.border),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.w900,
              color: highlight ? HasimColors.ctaDark : HasimColors.ink,
            ),
          ),
          Text(
            label,
            style: const TextStyle(fontSize: 10, color: HasimColors.muted),
          ),
        ],
      ),
    );
  }

  Widget _moneyRow(String label, num value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: bold ? 13 : 12,
              fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
          const Spacer(),
          Text(
            value.toDouble().toStringAsFixed(2),
            style: TextStyle(
              fontSize: bold ? 13 : 12,
              fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
              color: bold ? HasimColors.ctaDark : HasimColors.ink,
            ),
          ),
        ],
      ),
    );
  }

  Widget _actionsCard() {
    if (!_canManageTables) {
      return const HsCard(
        child: Text(
          'لا تملك صلاحية إدارة الطاولات.',
          style: TextStyle(fontSize: 12, color: HasimColors.muted),
        ),
      );
    }
    return HsCard(
      padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            child: Text(
              'خيارات الطاولة',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
            ),
          ),
          if (!_hasSession) ...[
            _action('QR المنيو', Icons.qr_code_2_outlined, _showQr),
          ] else ...[
            Theme(
              data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
              child: ExpansionTile(
                initiallyExpanded: false,
                tilePadding: const EdgeInsets.symmetric(horizontal: 8),
                childrenPadding: EdgeInsets.zero,
                title: const Text(
                  'المزيد',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                ),
                children: [
                  _action(
                    'إضافة ملاحظة',
                    Icons.sticky_note_2_outlined,
                    _editNote,
                  ),
                  _action('خصم', Icons.percent, _applyDiscount),
                  _action('QR المنيو', Icons.qr_code_2_outlined, _showQr),
                  _action(
                    'نقل الطاولة',
                    Icons.swap_horiz,
                    () => _transferOrMerge(merge: false),
                  ),
                  _action(
                    'دمج طاولة',
                    Icons.merge_type,
                    () => _transferOrMerge(merge: true),
                  ),
                  _action('تقسيم الحساب', Icons.call_split, _splitBill),
                ],
              ),
            ),
            _action(
              'إلغاء الطاولة',
              Icons.delete_outline,
              _cancelSession,
              danger: true,
            ),
          ],
        ],
      ),
    );
  }

  Widget _action(
    String label,
    IconData icon,
    VoidCallback onTap, {
    bool danger = false,
  }) {
    return ListTile(
      dense: true,
      leading: Icon(
        icon,
        color: danger ? HasimColors.danger : HasimColors.ink,
        size: 20,
      ),
      title: Text(
        label,
        style: TextStyle(
          fontWeight: FontWeight.w700,
          fontSize: 13,
          color: danger ? HasimColors.danger : HasimColors.ink,
        ),
      ),
      trailing: const Icon(Icons.chevron_left, size: 18),
      onTap: onTap,
    );
  }

  Widget _ordersPanel() {
    final filtered = _filteredOrders;
    return Padding(
      padding: const EdgeInsets.all(12),
      child: HsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'تفاصيل طلبات الطاولة',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _search,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(
                hintText: 'ابحث في الطلبات…',
                isDense: true,
                prefixIcon: Icon(Icons.search, size: 18),
              ),
            ),
            const SizedBox(height: 8),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final f in [
                    ('all', 'الكل'),
                    ('open', 'مفتوحة'),
                    ('paid', 'مدفوعة'),
                    ('cancelled', 'ملغية'),
                  ])
                    Padding(
                      padding: const EdgeInsetsDirectional.only(end: 6),
                      child: FilterChip(
                        label: Text(f.$2),
                        selected: _filter == f.$1,
                        onSelected: (_) => setState(() => _filter = f.$1),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: filtered.isEmpty
                  ? const Center(
                      child: Text(
                        'لا توجد طلبات في هذه الجلسة.',
                        style: TextStyle(color: HasimColors.muted),
                      ),
                    )
                  : ListView.separated(
                      itemCount: filtered.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        final order = filtered[index];
                        final items = order['items'] is List
                            ? (order['items'] as List).whereType<Map>()
                            : const Iterable<Map>.empty();
                        return Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            border: Border.all(color: HasimColors.border),
                            borderRadius:
                                BorderRadius.circular(HasimRadius.md),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      '#${order['order_number'] ?? order['id']}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                  ),
                                  HsBadge(
                                    label: PosLabels.status(
                                      order['pos_status'] as String?,
                                    ),
                                    background: HasimColors.ctaSoft,
                                    foreground: HasimColors.ctaDark,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              for (final item in items)
                                Padding(
                                  padding: const EdgeInsets.only(bottom: 4),
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          '${item['product_name']}'
                                          '${item['variant_name'] != null ? ' - ${item['variant_name']}' : ''}',
                                          style: const TextStyle(fontSize: 12),
                                        ),
                                      ),
                                      Text(
                                        '× ${item['quantity']}',
                                        style: const TextStyle(fontSize: 12),
                                      ),
                                      const SizedBox(width: 8),
                                      Text(
                                        ((item['unit_price'] as num?) ?? 0)
                                            .toStringAsFixed(2),
                                        style: const TextStyle(
                                          fontSize: 11,
                                          color: HasimColors.muted,
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      Text(
                                        ((item['total_amount'] as num?) ?? 0)
                                            .toStringAsFixed(2),
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              const Divider(),
                              Row(
                                children: [
                                  Text(
                                    'خصم ${((order['discount_amount'] as num?) ?? 0).toStringAsFixed(2)}',
                                    style: const TextStyle(fontSize: 11),
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    'ضريبة ${((order['tax_amount'] as num?) ?? 0).toStringAsFixed(2)}',
                                    style: const TextStyle(fontSize: 11),
                                  ),
                                  const Spacer(),
                                  Text(
                                    ((order['total_amount'] as num?) ?? 0)
                                        .toStringAsFixed(2),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w900,
                                      color: HasimColors.ctaDark,
                                    ),
                                  ),
                                ],
                              ),
                              if (_canMutateOrder(order)) ...[
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton.icon(
                                        onPressed: () => _editOrder(order),
                                        icon: const Icon(Icons.edit_outlined,
                                            size: 16),
                                        label: const Text('تعديل الطلب'),
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Expanded(
                                      child: OutlinedButton.icon(
                                        onPressed: () => _deleteOrder(order),
                                        style: OutlinedButton.styleFrom(
                                          foregroundColor: HasimColors.danger,
                                        ),
                                        icon: const Icon(Icons.delete_outline,
                                            size: 16),
                                        label: const Text('حذف الطلب'),
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ],
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class CloseTableResult {
  const CloseTableResult({this.paymentMethod});
  final String? paymentMethod;
}

/// Close flow: review totals → payment method → confirm.
class CloseTableFlow extends StatefulWidget {
  const CloseTableFlow({
    super.key,
    required this.tableName,
    required this.subtotal,
    required this.discount,
    required this.tax,
    required this.total,
    required this.ordersCount,
    this.orders = const [],
  });

  final String tableName;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final int ordersCount;
  final List<Map<String, dynamic>> orders;

  @override
  State<CloseTableFlow> createState() => _CloseTableFlowState();
}

class _CloseTableFlowState extends State<CloseTableFlow> {
  var _step = 0;
  String _payment = 'cash';

  static const _methods = [
    ('cash', 'نقداً'),
    ('card', 'بطاقة'),
    ('transfer', 'تحويل'),
    ('cashier', 'كاشير'),
    ('pay_now', 'ادفع الآن'),
    ('pay_later', 'آجل'),
  ];

  @override
  Widget build(BuildContext context) {
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(HasimRadius.lg),
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420, maxHeight: 640),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _step == 0
                    ? 'مراجعة الحساب'
                    : _step == 1
                        ? 'طريقة الدفع'
                        : 'تأكيد إغلاق الطاولة',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              Text(
                'الطاولة: ${widget.tableName}',
                style: const TextStyle(color: HasimColors.muted),
              ),
              const SizedBox(height: 12),
              Flexible(
                child: SingleChildScrollView(
                  child: _step == 0
                      ? Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _row('عدد الطلبات', '${widget.ordersCount}'),
                            if (widget.orders.isNotEmpty) ...[
                              const SizedBox(height: 6),
                              for (final order in widget.orders)
                                _row(
                                  '${order['label']}',
                                  ((order['total'] as num?) ?? 0)
                                      .toStringAsFixed(2),
                                ),
                              const Divider(),
                            ],
                            _row(
                              'المجموع الفرعي',
                              widget.subtotal.toStringAsFixed(2),
                            ),
                            _row('الخصم', widget.discount.toStringAsFixed(2)),
                            _row('الضريبة', widget.tax.toStringAsFixed(2)),
                            _row(
                              'الإجمالي',
                              widget.total.toStringAsFixed(2),
                              bold: true,
                            ),
                            const SizedBox(height: 8),
                            const Text(
                              'سيتم إنشاء فاتورة نهائية عند الإغلاق فقط.',
                              style: TextStyle(
                                fontSize: 12,
                                color: HasimColors.muted,
                              ),
                            ),
                          ],
                        )
                      : _step == 1
                          ? Column(
                              children: [
                                for (final m in _methods)
                                  RadioListTile<String>(
                                    value: m.$1,
                                    groupValue: _payment,
                                    title: Text(m.$2),
                                    onChanged: (v) =>
                                        setState(() => _payment = v ?? 'cash'),
                                  ),
                              ],
                            )
                          : Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'الدفع: ${_methods.firstWhere((e) => e.$1 == _payment).$2}',
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  'الإجمالي النهائي: ${widget.total.toStringAsFixed(2)}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                    color: HasimColors.ctaDark,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                const Text(
                                  'بعد التأكيد تُغلق الجلسة وتُصدر فاتورة الكاشير مرة واحدة.',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: HasimColors.muted,
                                  ),
                                ),
                              ],
                            ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: HsOutlineButton(
                      label: _step == 0 ? 'إلغاء' : 'رجوع',
                      onPressed: () {
                        if (_step == 0) {
                          Navigator.pop(context);
                        } else {
                          setState(() => _step -= 1);
                        }
                      },
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: HsPrimaryButton(
                      label: _step == 2 ? 'إتمام الإغلاق' : 'التالي',
                      onPressed: () {
                        if (_step < 2) {
                          setState(() => _step += 1);
                          return;
                        }
                        Navigator.pop(
                          context,
                          CloseTableResult(paymentMethod: _payment),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _row(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
          const Spacer(),
          Text(
            value,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
              color: bold ? HasimColors.ctaDark : HasimColors.ink,
            ),
          ),
        ],
      ),
    );
  }
}
