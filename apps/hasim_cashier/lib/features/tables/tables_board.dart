import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:connectivity_plus/connectivity_plus.dart';

import '../../core/api/cashier_api.dart';
import '../../core/auth/auth_controller.dart';
import '../../core/navigation/pos_shell_nav.dart';
import '../../core/offline/conflict_strategy.dart';
import '../../core/permissions/cashier_permissions.dart';
import '../../core/permissions/permissions_provider.dart';
import '../../core/pos/pos_labels.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/widgets/hasim_widgets.dart';
import '../cart/cart_controller.dart';
import 'table_action_wizards.dart';
import 'table_order_editor.dart';

/// Tables board matching Laravel `workspace/pos/tables/index` + `show` actions.
class TablesBoard extends ConsumerStatefulWidget {
  const TablesBoard({super.key});

  @override
  ConsumerState<TablesBoard> createState() => _TablesBoardState();
}

class _TablesBoardState extends ConsumerState<TablesBoard> {
  List<Map<String, dynamic>> _tables = const [];
  Map<String, dynamic>? _selected;
  Map<String, dynamic>? _detail;
  var _loading = true;
  var _detailLoading = false;
  int? _openMenuId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(cashierApiProvider).get('/tables');
      final list = <Map<String, dynamic>>[];
      if (data['tables'] is List) {
        for (final item in data['tables'] as List) {
          if (item is Map) list.add(Map<String, dynamic>.from(item));
        }
      }
      setState(() {
        _tables = list;
        _loading = false;
        if (_selected != null) {
          final id = _selected!['id'];
          _selected = list.cast<Map<String, dynamic>?>().firstWhere(
                (t) => t?['id'] == id,
                orElse: () => null,
              );
        }
      });
      if (_selected != null) {
        await _loadDetail((_selected!['id'] as num).toInt());
      }
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  Future<void> _loadDetail(int tableId) async {
    setState(() => _detailLoading = true);
    try {
      final data =
          await ref.read(cashierApiProvider).get('/tables/$tableId');
      if (!mounted) return;
      setState(() {
        _detail = data;
        _detailLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _detailLoading = false);
    }
  }

  Future<void> _select(Map<String, dynamic> table) async {
    setState(() {
      _selected = table;
      _openMenuId = null;
      _detail = null;
    });
    await _loadDetail((table['id'] as num).toInt());
  }

  int? get _sessionId {
    final fromDetail = (_detail?['session_id'] as num?)?.toInt();
    if (fromDetail != null) return fromDetail;
    return (_selected?['session_id'] as num?)?.toInt();
  }

  int? get _tableId => (_selected?['id'] as num?)?.toInt();

  List<Map<String, dynamic>> get _detailOrders {
    final raw = _detail?['orders'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  List<Map<String, dynamic>> get _splitItems {
    final items = <Map<String, dynamic>>[];
    for (final order in _detailOrders) {
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

  Future<void> _openSession() async {
    final id = _tableId;
    if (id == null) return;
    if (!_canManageTables) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية إدارة الطاولات.')),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
    try {
      await ref.read(cashierApiProvider).post('/tables/$id/sessions/open');
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

  bool get _canManageTables {
    final perms = ref.read(cashierPermissionsProvider);
    if (perms.isNotEmpty) {
      return CashierPermissions.canManageTables(perms);
    }
    final session = ref.read(authControllerProvider).valueOrNull;
    return CashierPermissions.canManageTables(session?.permissions);
  }

  Future<bool> _ensureOnlineForTableAction() async {
    if (ConflictStrategy.forDomain('table_action') !=
        ConflictPolicy.requireOnline) {
      return true;
    }
    final result = await Connectivity().checkConnectivity();
    final offline = result.isEmpty ||
        result.every((r) => r == ConnectivityResult.none);
    if (offline) {
      if (!mounted) return false;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'عمليات الطاولات تتطلب اتصالًا بالإنترنت (Laravel هو مصدر الحقيقة).',
          ),
        ),
      );
      return false;
    }
    return true;
  }

  Future<void> _closeSession() async {
    final tableId = _tableId;
    final sessionId = _sessionId;
    if (tableId == null || sessionId == null) return;
    if (!_canManageTables) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية إدارة الطاولات.')),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => CloseTableWizard(
        tableName: '${_selected?['name'] ?? ''}',
      ),
    );
    if (ok != true) return;
    try {
      await ref
          .read(cashierApiProvider)
          .post('/tables/$tableId/sessions/$sessionId/close');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم إغلاق الجلسة.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _cancelSession() async {
    final tableId = _tableId;
    final sessionId = _sessionId;
    if (tableId == null || sessionId == null) return;
    if (!_canManageTables) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية إدارة الطاولات.')),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('إلغاء الطاولة؟'),
        content: const Text('سيتم إلغاء الجلسة والطلبات المرتبطة.'),
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
          .post('/tables/$tableId/sessions/$sessionId/cancel');
      if (!mounted) return;
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _pickTargetTable({required String title}) async {
    final tableId = _tableId;
    final sessionId = _sessionId;
    if (tableId == null || sessionId == null) return;
    if (!_canManageTables) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية إدارة الطاولات.')),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
    final others = _tables
        .where((t) => t['id'] != tableId)
        .map((t) => Map<String, dynamic>.from(t))
        .toList();
    final isMerge = title.contains('دمج');
    final targetId = await showDialog<int>(
      context: context,
      builder: (ctx) => TableTransferWizard(
        title: isMerge ? 'دمج الطاولات' : 'نقل الطاولة',
        currentTableName: '${_selected?['name'] ?? ''}',
        candidates: others,
        confirmLabel: isMerge ? 'تأكيد الدمج' : 'تأكيد النقل',
      ),
    );
    if (targetId == null) return;
    final path = isMerge ? 'merge' : 'transfer';
    try {
      await ref.read(cashierApiProvider).post(
        '/tables/$tableId/sessions/$sessionId/$path',
        data: {'target_table_id': targetId},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(isMerge ? 'تم الدمج.' : 'تم النقل.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _splitBill() async {
    final tableId = _tableId;
    final sessionId = _sessionId;
    if (tableId == null || sessionId == null) return;
    if (!_canManageTables) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية إدارة الطاولات.')),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
    final items = _splitItems;
    if (items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا توجد أصناف لتقسيم الحساب.')),
      );
      return;
    }
    final total = ((_detail?['total'] as num?) ??
            (_selected?['total'] as num?) ??
            0)
        .toDouble();
    final selected = await showDialog<List<Map<String, dynamic>>>(
      context: context,
      builder: (ctx) => SplitBillWizard(items: items, sessionTotal: total),
    );
    if (selected == null || selected.isEmpty) return;

    // Laravel requires ≥2 groups covering 100% of item quantities.
    final selectedById = <int, int>{};
    for (final row in selected) {
      final id = (row['order_item_id'] as num).toInt();
      selectedById[id] = (row['quantity'] as num).toInt();
    }
    final groupA = <Map<String, dynamic>>[];
    final groupB = <Map<String, dynamic>>[];
    for (final item in items) {
      final id = (item['order_item_id'] as num).toInt();
      final maxQty = (item['quantity'] as num).toInt();
      final qtyA = selectedById[id] ?? 0;
      final qtyB = maxQty - qtyA;
      if (qtyA > 0) {
        groupA.add({'order_item_id': id, 'quantity': qtyA});
      }
      if (qtyB > 0) {
        groupB.add({'order_item_id': id, 'quantity': qtyB});
      }
    }
    if (groupA.isEmpty || groupB.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'يجب فصل جزء من الأصناف مع الإبقاء على جزء آخر (حسابان على الأقل).',
          ),
        ),
      );
      return;
    }

    try {
      await ref.read(cashierApiProvider).post(
        '/tables/$tableId/sessions/$sessionId/split',
        data: {
          'groups': [
            {'items': groupA},
            {'items': groupB},
          ],
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم تقسيم الحساب.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _editNote() async {
    final tableId = _tableId;
    final sessionId = _sessionId;
    if (tableId == null || sessionId == null) return;
    if (!await _ensureOnlineForTableAction()) return;
    final controller = TextEditingController(
      text: '${_detail?['notes'] ?? _selected?['notes'] ?? ''}',
    );
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('ملاحظة الجلسة'),
        content: TextField(
          controller: controller,
          maxLines: 4,
          decoration: const InputDecoration(
            hintText: 'اكتب ملاحظة للطاولة / الجلسة',
          ),
        ),
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
        '/tables/$tableId/sessions/$sessionId/note',
        data: {'notes': controller.text.trim()},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم حفظ الملاحظة.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _applyDiscount() async {
    final tableId = _tableId;
    final sessionId = _sessionId;
    if (tableId == null || sessionId == null) return;
    final perms = ref.read(cashierPermissionsProvider);
    if (!CashierPermissions.canDiscount(perms) && perms.isNotEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية تطبيق خصم.')),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
    final controller = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('خصم الجلسة'),
        content: TextField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(
            labelText: 'مبلغ الخصم',
            hintText: '0.00',
          ),
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
        '/tables/$tableId/sessions/$sessionId/discount',
        data: {'discount_amount': amount},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم تطبيق الخصم.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  void _addOrderForTable() {
    final id = _tableId;
    if (id == null) return;
    if (_sessionId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('افتح الجلسة أولًا قبل إضافة طلب.')),
      );
      return;
    }
    ref.read(cartControllerProvider.notifier).setChannel(OrderChannel.table);
    ref.read(cartControllerProvider.notifier).setTable(id);
    requestPosShellTab(ref, PosShellTab.cashier);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('تم اختيار ${_selected?['name']} — أكمل الطلب من الكاشير'),
      ),
    );
  }

  bool _canMutateOrder(Map<String, dynamic> order) {
    final status = order['pos_status'] as String?;
    final paid = order['payment_status'] == 'paid';
    final invoiced = order['pos_cashier_invoice_id'] != null;
    return status != 'cancelled' &&
        status != 'completed' &&
        !paid &&
        !invoiced;
  }

  Future<void> _editOrder(Map<String, dynamic> order) async {
    if (!_canMutateOrder(order)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'لا يمكن تعديل هذا الطلب (مدفوع / مفوتر / ملغي / مكتمل).',
          ),
        ),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
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
            'لا يمكن حذف هذا الطلب (مدفوع / مفوتر / ملغي / مكتمل).',
          ),
        ),
      );
      return;
    }
    if (!await _ensureOnlineForTableAction()) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('هل أنت متأكد من حذف هذا الطلب؟'),
        content: Text(
          'سيتم حذف الطلب #${order['order_number'] ?? order['id']} من الطاولة نهائيًا (إلغاء في Laravel).',
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

  List<Widget> _lineWidgets(Map<String, dynamic> selected) {
    final raw = selected['lines'];
    if (raw is! List || raw.isEmpty) {
      return [
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 16),
          child: Text(
            'لا توجد عناصر في هذه الجلسة.',
            textAlign: TextAlign.center,
            style: TextStyle(color: HasimColors.muted, fontSize: 12),
          ),
        ),
      ];
    }
    return raw.whereType<Map>().map((line) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          decoration: BoxDecoration(
            color: HasimColors.surfaceSoft,
            borderRadius: BorderRadius.circular(HasimRadius.sm),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  '${line['name']} × ${line['quantity']}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Text(
                ((line['total'] as num?) ?? 0).toStringAsFixed(2),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      );
    }).toList();
  }

  Widget _actionsPanel() {
    if (!_canManageTables) {
      return const HsCard(
        child: Text(
          'لا تملك صلاحية إدارة الطاولات (tables.manage).',
          style: TextStyle(fontSize: 12, color: HasimColors.muted),
        ),
      );
    }
    final hasSession = _sessionId != null;
    return HsCard(
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
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
          if (!hasSession)
            _ActionTile(
              label: 'فتح جلسة',
              icon: Icons.lock_open_outlined,
              color: HasimColors.ctaDark,
              onTap: _openSession,
            )
          else ...[
            _ActionTile(
              label: 'إضافة طلب',
              icon: Icons.add_circle_outline,
              onTap: _addOrderForTable,
            ),
            _ActionTile(
              label: 'ملاحظة الجلسة',
              icon: Icons.sticky_note_2_outlined,
              onTap: _editNote,
            ),
            _ActionTile(
              label: 'خصم الجلسة',
              icon: Icons.percent,
              onTap: _applyDiscount,
            ),
            _ActionTile(
              label: 'نقل الطاولة',
              icon: Icons.swap_horiz,
              onTap: () => _pickTargetTable(title: 'نقل الطاولة إلى'),
            ),
            _ActionTile(
              label: 'دمج طاولة',
              icon: Icons.merge_type,
              onTap: () => _pickTargetTable(title: 'دمج مع طاولة'),
            ),
            _ActionTile(
              label: 'تقسيم الحساب',
              icon: Icons.call_split,
              onTap: _splitBill,
            ),
            _ActionTile(
              label: 'إغلاق الطاولة',
              icon: Icons.lock_outline,
              onTap: _closeSession,
            ),
            _ActionTile(
              label: 'إلغاء الطاولة',
              icon: Icons.delete_outline,
              color: HasimColors.danger,
              onTap: _cancelSession,
            ),
          ],
        ],
      ),
    );
  }

  Widget _detailsPanel() {
    if (_selected == null) {
      return const HsCard(
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 40),
          child: Center(
            child: Text(
              'اختر طاولة لعرض تفاصيل طلباتها.',
              style: TextStyle(color: HasimColors.muted, fontSize: 12),
            ),
          ),
        ),
      );
    }

    final selected = _detail ?? _selected!;
    final occupied = selected['status'] == 'occupied';
    final ordersCount = _detailOrders.length;
    final itemsCount = (_detail?['lines'] is List)
        ? (_detail!['lines'] as List).fold<int>(
            0,
            (s, e) =>
                s + (((e is Map ? e['quantity'] : null) as num?)?.toInt() ?? 0),
          )
        : ((_selected?['lines'] is List)
            ? (_selected!['lines'] as List).length
            : 0);
    final total = ((selected['total'] as num?) ?? 0).toDouble();

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        HsCard(
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
                      '${selected['name']}',
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  occupied
                      ? HsBadge.occupied(
                          PosLabels.tableStatus(selected['status'] as String?),
                        )
                      : HsBadge.available(
                          PosLabels.tableStatus(selected['status'] as String?),
                        ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                _sessionId != null ? 'جلسة مفتوحة' : 'لا توجد جلسة نشطة',
                style: const TextStyle(fontSize: 12, color: HasimColors.muted),
              ),
              if (selected['customer_name'] != null) ...[
                const SizedBox(height: 4),
                Text(
                  'العميل: ${selected['customer_name']}',
                  style: const TextStyle(fontSize: 12),
                ),
              ],
              if ((selected['notes'] as String?)?.isNotEmpty == true) ...[
                const SizedBox(height: 4),
                Text(
                  'ملاحظة: ${selected['notes']}',
                  style: const TextStyle(fontSize: 12, color: HasimColors.muted),
                ),
              ],
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _StatBox(value: '$ordersCount', label: 'طلبات'),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _StatBox(value: '$itemsCount', label: 'أصناف'),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _StatBox(
                      value: total.toStringAsFixed(2),
                      label: 'الإجمالي',
                      valueColor: HasimColors.ctaDark,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              HsPrimaryButton(
                label: '+ إضافة طلب',
                onPressed: _sessionId == null ? null : _addOrderForTable,
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        _actionsPanel(),
        const SizedBox(height: 8),
        HsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'تفاصيل الطلب',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              if (_detailLoading)
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                )
              else ...[
                ..._lineWidgets(selected),
                const Divider(),
                Row(
                  children: [
                    const Text(
                      'الإجمالي',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const Spacer(),
                    Text(
                      total.toStringAsFixed(2),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
                if (_detailOrders.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  const Text(
                    'طلبات الجلسة',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12),
                  ),
                  const SizedBox(height: 6),
                  for (final order in _detailOrders)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          border: Border.all(color: HasimColors.border),
                          borderRadius: BorderRadius.circular(HasimRadius.md),
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
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
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
                                const SizedBox(width: 8),
                                Text(
                                  ((order['total_amount'] as num?) ?? 0)
                                      .toStringAsFixed(2),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 12,
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
                      ),
                    ),
                ],
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _tableCard(Map<String, dynamic> table) {
    final selected = _selected?['id'] == table['id'];
    final occupied = table['status'] == 'occupied';
    final menuOpen = _openMenuId == table['id'];
    final total = ((table['total'] as num?) ?? 0).toDouble();
    final hasSession = table['session_id'] != null;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(HasimRadius.md),
      child: InkWell(
        borderRadius: BorderRadius.circular(HasimRadius.md),
        onTap: () => _select(table),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(HasimRadius.md),
            border: Border.all(
              color: selected ? HasimColors.cta : HasimColors.border,
              width: selected ? 1.5 : 1,
            ),
          ),
          padding: const EdgeInsets.all(12),
          child: Stack(
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${table['name']}',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    PosLabels.tableStatus(table['status'] as String?),
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: occupied
                          ? HasimColors.occupied
                          : HasimColors.available,
                    ),
                  ),
                  const Spacer(),
                    Text(
                      'الطلبات النشطة: ${table['open_orders_count'] ?? (table['lines'] is List ? (table['lines'] as List).length : 0)}',
                      style: const TextStyle(fontSize: 11, color: HasimColors.muted),
                    ),
                  if (total > 0)
                    Text(
                      'الإجمالي: ${total.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                ],
              ),
              PositionedDirectional(
                top: 0,
                end: 0,
                child: PopupMenuButton<String>(
                  padding: EdgeInsets.zero,
                  onOpened: () => setState(
                    () => _openMenuId = (table['id'] as num).toInt(),
                  ),
                  onCanceled: () => setState(() => _openMenuId = null),
                  onSelected: (value) async {
                    setState(() => _openMenuId = null);
                    await _select(table);
                    switch (value) {
                      case 'view':
                        break;
                      case 'add':
                        _addOrderForTable();
                      case 'open':
                        await _openSession();
                      case 'close':
                        await _closeSession();
                      case 'transfer':
                        await _pickTargetTable(title: 'نقل الطاولة إلى');
                      case 'merge':
                        await _pickTargetTable(title: 'دمج مع طاولة');
                      case 'split':
                        await _splitBill();
                    }
                  },
                  itemBuilder: (ctx) => [
                    const PopupMenuItem(value: 'view', child: Text('عرض الطلب')),
                    if (hasSession) ...[
                      const PopupMenuItem(
                        value: 'add',
                        child: Text('إضافة طلب'),
                      ),
                      const PopupMenuItem(
                        value: 'transfer',
                        child: Text('نقل الطاولة'),
                      ),
                      const PopupMenuItem(
                        value: 'merge',
                        child: Text('دمج طاولة'),
                      ),
                      const PopupMenuItem(
                        value: 'split',
                        child: Text('تقسيم الحساب'),
                      ),
                      const PopupMenuItem(
                        value: 'close',
                        child: Text(
                          'إغلاق الطاولة',
                          style: TextStyle(color: HasimColors.danger),
                        ),
                      ),
                    ] else
                      const PopupMenuItem(
                        value: 'open',
                        child: Text('فتح جلسة'),
                      ),
                  ],
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      border: Border.all(
                        color: menuOpen ? HasimColors.cta : HasimColors.border,
                      ),
                      borderRadius: BorderRadius.circular(HasimRadius.sm),
                    ),
                    child: const Text(
                      '⋯',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _grid() {
    if (_tables.isEmpty) {
      return const HsEmpty(title: 'لا توجد طاولات حتى الآن.');
    }
    final width = MediaQuery.sizeOf(context).width;
    return RefreshIndicator(
      onRefresh: _load,
      child: GridView.builder(
        padding: const EdgeInsets.all(4),
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: width >= 1200
              ? 3
              : width >= 700
                  ? 2
                  : 2,
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: 1.35,
        ),
        itemCount: _tables.length,
        itemBuilder: (context, index) => _tableCard(_tables[index]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    final width = MediaQuery.sizeOf(context).width;
    final desktop = width >= 1000;

    if (!desktop) {
      return Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Expanded(
              flex: 3,
              child: HsCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'الطاولات',
                      style:
                          TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Expanded(child: _grid()),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
            Expanded(flex: 3, child: _detailsPanel()),
          ],
        ),
      );
    }

    // RTL: details RIGHT first, cards CENTER (matches web lg:grid).
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(width: 320, child: _detailsPanel()),
          const SizedBox(width: 12),
          Expanded(
            child: HsCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'الطاولات',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Expanded(child: _grid()),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatBox extends StatelessWidget {
  const _StatBox({
    required this.value,
    required this.label,
    this.valueColor = HasimColors.ink,
  });

  final String value;
  final String label;
  final Color valueColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        color: HasimColors.surfaceSoft,
        borderRadius: BorderRadius.circular(HasimRadius.md),
        border: Border.all(color: const Color(0xFFF1F5F9)),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w800,
              color: valueColor,
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
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.label,
    required this.icon,
    required this.onTap,
    this.color = HasimColors.ink,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: color,
                ),
              ),
            ),
            Icon(icon, size: 18, color: color.withValues(alpha: 0.55)),
          ],
        ),
      ),
    );
  }
}
