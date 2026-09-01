/// Cashier permission helpers — Laravel bootstrap `permissions` is source of truth.
library;

class CashierPermissions {
  const CashierPermissions._();

  static bool can(Map<String, dynamic>? permissions, String key) {
    if (permissions == null || permissions.isEmpty) return false;
    return permissions[key] == true;
  }

  static bool canManageTables(Map<String, dynamic>? p) =>
      can(p, 'tables.manage') || can(p, 'pos.manage');

  static bool canCreateOrders(Map<String, dynamic>? p) =>
      can(p, 'orders.create') || can(p, 'orders.manage') || can(p, 'pos.use');

  static bool canDiscount(Map<String, dynamic>? p) =>
      can(p, 'orders.discount') || can(p, 'orders.manage') || can(p, 'pos.manage');

  static bool canRefund(Map<String, dynamic>? p) =>
      can(p, 'orders.refund') || can(p, 'orders.manage') || can(p, 'pos.manage');

  static bool canViewReports(Map<String, dynamic>? p) =>
      can(p, 'reports.view') || can(p, 'pos.manage');
}
