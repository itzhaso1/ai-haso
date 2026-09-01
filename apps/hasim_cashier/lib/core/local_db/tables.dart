import 'package:drift/drift.dart';

/// Local POS tables — every business row carries workspace_id (+ device where relevant).
/// Laravel remains Source of Truth; SQLite is the daily runtime store after Initial Sync.

class LocalDevices extends Table {
  TextColumn get deviceId => text()();
  IntColumn get accountId => integer().nullable()();
  IntColumn get workspaceId => integer().nullable()();
  IntColumn get userId => integer().nullable()();
  TextColumn get name => text().withDefault(const Constant('كاشير حاسم'))();
  TextColumn get platform => text().withDefault(const Constant('cashier'))();
  DateTimeColumn get registeredAt => dateTime().nullable()();
  DateTimeColumn get lastSeenAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {deviceId};
}

class LocalCategories extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  IntColumn get serverId => integer().nullable()();
  TextColumn get name => text()();
  IntColumn get sortOrder => integer().withDefault(const Constant(0))();
  BoolColumn get isActive => boolean().withDefault(const Constant(true))();
  BoolColumn get isDeleted => boolean().withDefault(const Constant(false))();
  DateTimeColumn get updatedAt => dateTime()();
  IntColumn get serverVersion => integer().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalProducts extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  IntColumn get serverId => integer().nullable()();
  TextColumn get categoryLocalId => text().nullable()();
  IntColumn get categoryServerId => integer().nullable()();
  TextColumn get name => text()();
  TextColumn get sku => text().nullable()();
  TextColumn get barcode => text().nullable()();
  TextColumn get itemType => text().nullable()();
  RealColumn get price => real().withDefault(const Constant(0))();
  BoolColumn get isActive => boolean().withDefault(const Constant(true))();
  BoolColumn get isDeleted => boolean().withDefault(const Constant(false))();
  TextColumn get payloadJson => text().withDefault(const Constant('{}'))();
  DateTimeColumn get updatedAt => dateTime()();
  IntColumn get serverVersion => integer().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalTables extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  IntColumn get serverId => integer().nullable()();
  TextColumn get name => text()();
  TextColumn get status => text().withDefault(const Constant('available'))();
  IntColumn get capacity => integer().nullable()();
  IntColumn get sessionServerId => integer().nullable()();
  TextColumn get payloadJson => text().withDefault(const Constant('{}'))();
  DateTimeColumn get updatedAt => dateTime()();
  IntColumn get serverVersion => integer().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalCustomers extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  IntColumn get serverId => integer().nullable()();
  TextColumn get name => text()();
  TextColumn get phone => text().nullable()();
  TextColumn get payloadJson => text().withDefault(const Constant('{}'))();
  DateTimeColumn get updatedAt => dateTime()();
  TextColumn get syncStatus => text().withDefault(const Constant('synced'))();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalOrders extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  TextColumn get deviceId => text()();
  IntColumn get serverId => integer().nullable()();
  TextColumn get clientReference => text()();
  TextColumn get orderType => text()();
  IntColumn get tableServerId => integer().nullable()();
  TextColumn get tableLocalId => text().nullable()();
  TextColumn get notes => text().nullable()();
  RealColumn get subtotal => real().withDefault(const Constant(0))();
  RealColumn get taxAmount => real().withDefault(const Constant(0))();
  RealColumn get discountAmount => real().withDefault(const Constant(0))();
  RealColumn get totalAmount => real().withDefault(const Constant(0))();
  TextColumn get posStatus => text().withDefault(const Constant('new'))();
  TextColumn get paymentStatus => text().withDefault(const Constant('unpaid'))();
  TextColumn get syncStatus => text().withDefault(const Constant('pending'))();
  TextColumn get lastError => text().nullable()();
  IntColumn get retryCount => integer().withDefault(const Constant(0))();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();
  DateTimeColumn get syncedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalOrderItems extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  TextColumn get orderLocalId => text()();
  IntColumn get serverId => integer().nullable()();
  IntColumn get productServerId => integer().nullable()();
  TextColumn get productLocalId => text().nullable()();
  TextColumn get name => text()();
  IntColumn get quantity => integer()();
  RealColumn get unitPrice => real()();
  RealColumn get discountAmount => real().withDefault(const Constant(0))();
  RealColumn get totalAmount => real()();
  BoolColumn get isRemoved => boolean().withDefault(const Constant(false))();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalPayments extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  TextColumn get deviceId => text()();
  IntColumn get serverId => integer().nullable()();
  TextColumn get orderLocalId => text().nullable()();
  TextColumn get invoiceLocalId => text().nullable()();
  TextColumn get method => text()();
  RealColumn get amount => real()();
  TextColumn get syncStatus => text().withDefault(const Constant('pending'))();
  TextColumn get clientReference => text()();
  DateTimeColumn get createdAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalInvoices extends Table {
  TextColumn get localId => text()();
  IntColumn get workspaceId => integer()();
  TextColumn get deviceId => text()();
  IntColumn get serverId => integer().nullable()();
  TextColumn get invoiceNumber => text().nullable()();
  RealColumn get totalAmount => real().withDefault(const Constant(0))();
  TextColumn get syncStatus => text().withDefault(const Constant('pending'))();
  TextColumn get payloadJson => text().withDefault(const Constant('{}'))();
  DateTimeColumn get createdAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class LocalSettings extends Table {
  TextColumn get key => text()();
  IntColumn get workspaceId => integer()();
  TextColumn get valueJson => text()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {workspaceId, key};
}

class LocalPermissions extends Table {
  TextColumn get key => text()();
  IntColumn get workspaceId => integer()();
  IntColumn get userId => integer()();
  BoolColumn get allowed => boolean().withDefault(const Constant(false))();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {workspaceId, userId, key};
}

class SyncQueueItems extends Table {
  IntColumn get id => integer().autoIncrement()();
  IntColumn get workspaceId => integer()();
  TextColumn get deviceId => text()();
  TextColumn get entityType => text()();
  TextColumn get entityId => text()();
  TextColumn get operation => text()();
  TextColumn get payloadJson => text()();
  TextColumn get clientReference => text()();
  TextColumn get status => text().withDefault(const Constant('pending'))();
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  TextColumn get lastError => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();
  DateTimeColumn get nextAttemptAt => dateTime().nullable()();
}

class SyncConflicts extends Table {
  IntColumn get id => integer().autoIncrement()();
  IntColumn get workspaceId => integer()();
  TextColumn get entityType => text()();
  TextColumn get entityId => text()();
  TextColumn get strategy => text()();
  TextColumn get localJson => text()();
  TextColumn get serverJson => text()();
  TextColumn get status => text().withDefault(const Constant('open'))();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get resolvedAt => dateTime().nullable()();
}

class SyncMetadata extends Table {
  TextColumn get key => text()();
  IntColumn get workspaceId => integer()();
  TextColumn get deviceId => text().nullable()();
  TextColumn get value => text()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {workspaceId, key};
}
