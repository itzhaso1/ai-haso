import 'package:equatable/equatable.dart';

class UserModel extends Equatable {
  const UserModel({
    required this.id,
    required this.name,
    this.email,
    this.phone,
    this.locale,
    this.timezone,
    this.avatarUrl,
  });

  final int id;
  final String name;
  final String? email;
  final String? phone;
  final String? locale;
  final String? timezone;
  final String? avatarUrl;

  factory UserModel.fromJson(Map<String, dynamic> json) => UserModel(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        email: json['email']?.toString(),
        phone: json['phone']?.toString(),
        locale: json['locale']?.toString(),
        timezone: json['timezone']?.toString(),
        avatarUrl: json['avatar_url']?.toString(),
      );

  UserModel copyWith({
    String? name,
    String? email,
    String? phone,
    String? locale,
    String? timezone,
    String? avatarUrl,
  }) {
    return UserModel(
      id: id,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      locale: locale ?? this.locale,
      timezone: timezone ?? this.timezone,
      avatarUrl: avatarUrl ?? this.avatarUrl,
    );
  }

  @override
  List<Object?> get props => [id, name, email, phone, avatarUrl];
}

class WorkspaceModel extends Equatable {
  const WorkspaceModel({
    required this.id,
    required this.name,
    this.type,
    this.slug,
  });

  final int id;
  final String name;
  final String? type;
  final String? slug;

  factory WorkspaceModel.fromJson(Map<String, dynamic> json) => WorkspaceModel(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        type: json['type']?.toString(),
        slug: json['slug']?.toString(),
      );

  @override
  List<Object?> get props => [id, name, type, slug];
}

class CustomerModel extends Equatable {
  const CustomerModel({required this.id, required this.name, this.phone, this.email});

  final int id;
  final String name;
  final String? phone;
  final String? email;

  factory CustomerModel.fromJson(Map<String, dynamic> json) => CustomerModel(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        phone: json['phone']?.toString(),
        email: json['email']?.toString(),
      );

  @override
  List<Object?> get props => [id, name];
}

class MessageAttachmentModel extends Equatable {
  const MessageAttachmentModel({
    required this.id,
    required this.kind,
    required this.originalName,
    this.mimeType,
    this.sizeBytes,
    this.downloadUrl,
  });

  final int id;
  final String kind;
  final String originalName;
  final String? mimeType;
  final int? sizeBytes;
  final String? downloadUrl;

  factory MessageAttachmentModel.fromJson(Map<String, dynamic> json) =>
      MessageAttachmentModel(
        id: (json['id'] as num).toInt(),
        kind: json['kind']?.toString() ?? 'file',
        originalName: json['original_name']?.toString() ?? 'ملف',
        mimeType: json['mime_type']?.toString(),
        sizeBytes: (json['size_bytes'] as num?)?.toInt(),
        downloadUrl: json['download_url']?.toString(),
      );

  @override
  List<Object?> get props => [id];
}

class MessageModel extends Equatable {
  const MessageModel({
    required this.id,
    required this.conversationId,
    required this.direction,
    required this.messageType,
    required this.content,
    this.aiGenerated = false,
    this.createdAt,
    this.userName,
    this.customerName,
    this.attachments = const [],
    this.localPending = false,
    this.localFailed = false,
    this.clientId,
  });

  final int id;
  final int conversationId;
  final String direction;
  final String messageType;
  final String content;
  final bool aiGenerated;
  final DateTime? createdAt;
  final String? userName;
  final String? customerName;
  final List<MessageAttachmentModel> attachments;
  final bool localPending;
  final bool localFailed;
  final String? clientId;

  bool get isOutbound => direction == 'outbound';

  factory MessageModel.fromJson(Map<String, dynamic> json) {
    final attachmentsRaw = json['attachments'];
    final attachments = <MessageAttachmentModel>[];
    if (attachmentsRaw is List) {
      for (final item in attachmentsRaw) {
        if (item is Map<String, dynamic>) {
          attachments.add(MessageAttachmentModel.fromJson(item));
        }
      }
    }

    return MessageModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      conversationId: (json['conversation_id'] as num?)?.toInt() ?? 0,
      direction: json['direction']?.toString() ?? 'inbound',
      messageType: json['message_type']?.toString() ?? 'text',
      content: json['content']?.toString() ?? '',
      aiGenerated: json['ai_generated'] == true,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
      userName: json['user'] is Map ? json['user']['name']?.toString() : null,
      customerName: json['customer'] is Map ? json['customer']['name']?.toString() : null,
      attachments: attachments,
    );
  }

  MessageModel copyWith({
    int? id,
    bool? localPending,
    bool? localFailed,
    String? content,
    List<MessageAttachmentModel>? attachments,
    String? clientId,
  }) {
    return MessageModel(
      id: id ?? this.id,
      conversationId: conversationId,
      direction: direction,
      messageType: messageType,
      content: content ?? this.content,
      aiGenerated: aiGenerated,
      createdAt: createdAt,
      userName: userName,
      customerName: customerName,
      attachments: attachments ?? this.attachments,
      localPending: localPending ?? this.localPending,
      localFailed: localFailed ?? this.localFailed,
      clientId: clientId ?? this.clientId,
    );
  }

  Map<String, dynamic> toCacheJson() => {
        'id': id,
        'conversation_id': conversationId,
        'direction': direction,
        'message_type': messageType,
        'content': content,
        'ai_generated': aiGenerated,
        'created_at': createdAt?.toIso8601String(),
        'attachments': attachments
            .map((a) => {
                  'id': a.id,
                  'kind': a.kind,
                  'original_name': a.originalName,
                  'mime_type': a.mimeType,
                  'size_bytes': a.sizeBytes,
                  'download_url': a.downloadUrl,
                })
            .toList(),
      };

  @override
  List<Object?> get props => [id, clientId, content, localPending, localFailed];
}

class ConversationModel extends Equatable {
  const ConversationModel({
    required this.id,
    required this.channel,
    required this.status,
    this.externalId,
    this.aiEnabled = false,
    this.lastMessageAt,
    this.customer,
    this.unreadCount = 0,
    this.lastMessage,
    this.muted = false,
    this.archived = false,
  });

  final int id;
  final String channel;
  final String status;
  final String? externalId;
  final bool aiEnabled;
  final DateTime? lastMessageAt;
  final CustomerModel? customer;
  final int unreadCount;
  final MessageModel? lastMessage;
  final bool muted;
  final bool archived;

  String get title => customer?.name ?? externalId ?? 'محادثة #$id';

  String get preview {
    final text = lastMessage?.content.trim() ?? '';
    if (text.isNotEmpty) return text;
    return 'لا توجد رسائل';
  }

  String get channelLabel {
    switch (channel) {
      case 'whatsapp':
        return 'واتساب';
      case 'email':
        return 'بريد';
      case 'web':
        return 'ويب';
      case 'manual':
        return 'يدوي';
      default:
        return channel;
    }
  }

  factory ConversationModel.fromJson(Map<String, dynamic> json) {
    MessageModel? last;
    final lm = json['last_message'];
    if (lm is Map<String, dynamic>) {
      last = MessageModel.fromJson(lm);
    }

    return ConversationModel(
      id: (json['id'] as num).toInt(),
      channel: json['channel']?.toString() ?? 'web',
      status: json['status']?.toString() ?? 'open',
      externalId: json['external_id']?.toString(),
      aiEnabled: json['ai_enabled'] == true,
      lastMessageAt: DateTime.tryParse(json['last_message_at']?.toString() ?? ''),
      customer: json['customer'] is Map<String, dynamic>
          ? CustomerModel.fromJson(json['customer'] as Map<String, dynamic>)
          : null,
      unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
      lastMessage: last,
      muted: json['muted'] == true,
      archived: json['archived'] == true,
    );
  }

  Map<String, dynamic> toCacheJson() => {
        'id': id,
        'channel': channel,
        'status': status,
        'external_id': externalId,
        'ai_enabled': aiEnabled,
        'last_message_at': lastMessageAt?.toIso8601String(),
        'unread_count': unreadCount,
        'muted': muted,
        'archived': archived,
        if (customer != null)
          'customer': {
            'id': customer!.id,
            'name': customer!.name,
            'phone': customer!.phone,
            'email': customer!.email,
          },
        if (lastMessage != null) 'last_message': lastMessage!.toCacheJson(),
      };

  @override
  List<Object?> get props => [id, unreadCount, lastMessageAt, muted, archived];
}

class HomeSnapshot extends Equatable {
  const HomeSnapshot({
    this.unreadConversations = 0,
    this.unreadEmail = 0,
    this.todaysBookingsCount = 0,
    this.upcomingBookingsCount = 0,
    this.unreadNotifications = 0,
  });

  final int unreadConversations;
  final int unreadEmail;
  final int todaysBookingsCount;
  final int upcomingBookingsCount;
  final int unreadNotifications;

  factory HomeSnapshot.fromJson(Map<String, dynamic> json) => HomeSnapshot(
        unreadConversations: (json['unread_conversations'] as num?)?.toInt() ?? 0,
        unreadEmail: (json['unread_email'] as num?)?.toInt() ?? 0,
        todaysBookingsCount: (json['todays_bookings_count'] as num?)?.toInt() ?? 0,
        upcomingBookingsCount: (json['upcoming_bookings_count'] as num?)?.toInt() ?? 0,
        unreadNotifications: (json['unread_notifications'] as num?)?.toInt() ?? 0,
      );

  @override
  List<Object?> get props => [
        unreadConversations,
        unreadEmail,
        todaysBookingsCount,
        upcomingBookingsCount,
        unreadNotifications,
      ];
}

class EmailMessageModel extends Equatable {
  const EmailMessageModel({
    required this.id,
    required this.sender,
    required this.recipient,
    this.subject,
    this.type,
    this.preview,
    this.body,
    this.isRead = true,
    this.isStarred = false,
    this.createdAt,
    this.emailAccountId,
  });

  final int id;
  final String sender;
  final String recipient;
  final String? subject;
  final String? type;
  final String? preview;
  final String? body;
  final bool isRead;
  final bool isStarred;
  final DateTime? createdAt;
  final int? emailAccountId;

  factory EmailMessageModel.fromJson(Map<String, dynamic> json) => EmailMessageModel(
        id: (json['id'] as num).toInt(),
        sender: json['sender']?.toString() ?? '',
        recipient: json['recipient']?.toString() ?? '',
        subject: json['subject']?.toString(),
        type: json['type']?.toString(),
        preview: json['preview']?.toString(),
        body: json['body']?.toString(),
        isRead: json['is_read'] != false,
        isStarred: json['is_starred'] == true,
        createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
        emailAccountId: (json['email_account_id'] as num?)?.toInt(),
      );

  @override
  List<Object?> get props => [id, isRead, isStarred];
}

class AppointmentModel extends Equatable {
  const AppointmentModel({
    required this.id,
    this.bookingNumber,
    this.startsAt,
    this.endsAt,
    this.appointmentStatus,
    this.paymentStatus,
    this.customerName,
    this.customerPhone,
    this.notes,
    this.serviceName,
    this.staffName,
    this.sourceChannel,
  });

  final int id;
  final String? bookingNumber;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final String? appointmentStatus;
  final String? paymentStatus;
  final String? customerName;
  final String? customerPhone;
  final String? notes;
  final String? serviceName;
  final String? staffName;
  final String? sourceChannel;

  String get statusLabel {
    switch (appointmentStatus) {
      case 'confirmed':
        return 'مؤكد';
      case 'cancelled':
        return 'ملغي';
      case 'completed':
        return 'مكتمل';
      case 'pending':
        return 'قيد الانتظار';
      default:
        return appointmentStatus ?? '—';
    }
  }

  factory AppointmentModel.fromJson(Map<String, dynamic> json) => AppointmentModel(
        id: (json['id'] as num).toInt(),
        bookingNumber: json['booking_number']?.toString(),
        startsAt: DateTime.tryParse(json['starts_at']?.toString() ?? ''),
        endsAt: DateTime.tryParse(json['ends_at']?.toString() ?? ''),
        appointmentStatus: json['appointment_status']?.toString() ?? json['status']?.toString(),
        paymentStatus: json['payment_status']?.toString(),
        customerName: json['customer_name']?.toString() ??
            (json['customer'] is Map ? json['customer']['name']?.toString() : null),
        customerPhone: json['customer_phone']?.toString(),
        notes: json['notes']?.toString(),
        serviceName: json['service'] is Map ? json['service']['name']?.toString() : null,
        staffName: json['staff'] is Map ? json['staff']['name']?.toString() : null,
        sourceChannel: json['source_channel']?.toString() ?? json['source']?.toString(),
      );

  @override
  List<Object?> get props => [id, appointmentStatus, startsAt];
}

class AppNotificationModel extends Equatable {
  const AppNotificationModel({
    required this.id,
    required this.type,
    this.data = const {},
    this.readAt,
    this.createdAt,
  });

  final String id;
  final String type;
  final Map<String, dynamic> data;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get isRead => readAt != null;

  String get title {
    final t = data['title'] ?? data['subject'] ?? type;
    return t.toString();
  }

  String get body {
    final b = data['body'] ?? data['message'] ?? '';
    return b.toString();
  }

  factory AppNotificationModel.fromJson(Map<String, dynamic> json) => AppNotificationModel(
        id: json['id']?.toString() ?? '',
        type: json['type']?.toString() ?? '',
        data: json['data'] is Map<String, dynamic>
            ? json['data'] as Map<String, dynamic>
            : {},
        readAt: DateTime.tryParse(json['read_at']?.toString() ?? ''),
        createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
      );

  @override
  List<Object?> get props => [id, readAt];
}

class DeviceSessionModel extends Equatable {
  const DeviceSessionModel({
    required this.id,
    required this.name,
    this.deviceName,
    this.deviceType,
    this.lastUsedAt,
    this.isCurrent = false,
  });

  final int id;
  final String name;
  final String? deviceName;
  final String? deviceType;
  final DateTime? lastUsedAt;
  final bool isCurrent;

  factory DeviceSessionModel.fromJson(Map<String, dynamic> json) => DeviceSessionModel(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? 'جلسة',
        deviceName: json['device_name']?.toString(),
        deviceType: json['device_type']?.toString(),
        lastUsedAt: DateTime.tryParse(json['last_used_at']?.toString() ?? ''),
        isCurrent: json['is_current'] == true,
      );

  @override
  List<Object?> get props => [id];
}

class LoginResult {
  LoginResult({
    required this.token,
    required this.user,
    required this.workspaces,
    this.workspace,
  });

  final String token;
  final UserModel user;
  final WorkspaceModel? workspace;
  final List<WorkspaceModel> workspaces;

  factory LoginResult.fromJson(Map<String, dynamic> json) {
    final workspaces = <WorkspaceModel>[];
    final rawList = json['workspaces'];
    if (rawList is List) {
      for (final item in rawList) {
        if (item is Map<String, dynamic>) {
          workspaces.add(WorkspaceModel.fromJson(item));
        }
      }
    }

    return LoginResult(
      token: json['token']?.toString() ?? '',
      user: UserModel.fromJson(json['user'] as Map<String, dynamic>),
      workspace: json['workspace'] is Map<String, dynamic>
          ? WorkspaceModel.fromJson(json['workspace'] as Map<String, dynamic>)
          : null,
      workspaces: workspaces,
    );
  }
}

List<Map<String, dynamic>> asMapList(dynamic raw) {
  if (raw is List) {
    return raw.whereType<Map<String, dynamic>>().toList();
  }
  if (raw is Map<String, dynamic>) {
    // Laravel Resource::collection sometimes wraps
    if (raw['data'] is List) {
      return (raw['data'] as List).whereType<Map<String, dynamic>>().toList();
    }
  }
  return const [];
}

Map<String, dynamic>? asMap(dynamic raw) {
  if (raw is Map<String, dynamic>) {
    if (raw.containsKey('data') && raw['data'] is Map<String, dynamic>) {
      return raw['data'] as Map<String, dynamic>;
    }
    return raw;
  }
  return null;
}

class EmailAccountModel extends Equatable {
  const EmailAccountModel({
    required this.id,
    required this.name,
    required this.email,
    this.brandColor,
    this.logoUrl,
  });

  final int id;
  final String name;
  final String email;
  final String? brandColor;
  final String? logoUrl;

  factory EmailAccountModel.fromJson(Map<String, dynamic> json) => EmailAccountModel(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        email: json['email']?.toString() ?? '',
        brandColor: json['brand_color']?.toString(),
        logoUrl: json['logo_url']?.toString(),
      );

  @override
  List<Object?> get props => [id];
}

class ChannelModel extends Equatable {
  const ChannelModel({
    required this.key,
    required this.name,
    this.icon,
    this.connected = false,
    this.status = 'disconnected',
    this.statusLabel,
    this.hint,
    this.manageUrl,
    this.canConnectInApp = false,
  });

  final String key;
  final String name;
  final String? icon;
  final bool connected;
  final String status;
  final String? statusLabel;
  final String? hint;
  final String? manageUrl;
  final bool canConnectInApp;

  factory ChannelModel.fromJson(Map<String, dynamic> json) => ChannelModel(
        key: json['key']?.toString() ?? '',
        name: json['name']?.toString() ?? '',
        icon: json['icon']?.toString(),
        connected: json['connected'] == true,
        status: json['status']?.toString() ?? 'disconnected',
        statusLabel: json['status_label']?.toString(),
        hint: json['hint']?.toString(),
        manageUrl: json['manage_url']?.toString(),
        canConnectInApp: json['can_connect_in_app'] == true,
      );

  @override
  List<Object?> get props => [key, status, connected];
}

class PlanCatalogItem extends Equatable {
  const PlanCatalogItem({
    required this.id,
    required this.code,
    required this.name,
    this.description,
    this.tier,
    this.billingPeriod,
    this.price,
    this.currency,
    this.features = const [],
    this.limits = const {},
  });

  final int id;
  final String code;
  final String name;
  final String? description;
  final String? tier;
  final String? billingPeriod;
  final num? price;
  final String? currency;
  final List<String> features;
  final Map<String, dynamic> limits;

  factory PlanCatalogItem.fromJson(Map<String, dynamic> json) => PlanCatalogItem(
        id: (json['id'] as num?)?.toInt() ?? 0,
        code: json['code']?.toString() ?? '',
        name: json['name']?.toString() ?? '',
        description: json['description']?.toString(),
        tier: json['tier']?.toString(),
        billingPeriod: json['billing_period']?.toString(),
        price: json['price'] as num?,
        currency: json['currency']?.toString(),
        features: (json['features'] is List)
            ? (json['features'] as List).map((e) => e.toString()).toList()
            : const [],
        limits: json['limits'] is Map<String, dynamic>
            ? json['limits'] as Map<String, dynamic>
            : const {},
      );

  @override
  List<Object?> get props => [id, code];
}

class PlanSnapshot extends Equatable {
  const PlanSnapshot({
    this.features = const [],
    this.limits = const {},
    this.meters = const {},
    this.raw = const {},
  });

  final List<String> features;
  final Map<String, dynamic> limits;
  final Map<String, dynamic> meters;
  final Map<String, dynamic> raw;

  factory PlanSnapshot.fromJson(Map<String, dynamic> json) {
    final featuresRaw = json['features'];
    List<String> features = const [];
    if (featuresRaw is List) {
      features = featuresRaw.map((e) => e.toString()).toList();
    } else if (featuresRaw is Map) {
      features = featuresRaw.entries
          .where((e) => e.value == true)
          .map((e) => e.key.toString())
          .toList();
    }
    return PlanSnapshot(
      features: features,
      limits: json['limits'] is Map<String, dynamic>
          ? json['limits'] as Map<String, dynamic>
          : const {},
      meters: json['meters'] is Map<String, dynamic>
          ? json['meters'] as Map<String, dynamic>
          : const {},
      raw: json,
    );
  }

  @override
  List<Object?> get props => [features, limits, meters];
}

class PlansCatalog extends Equatable {
  const PlansCatalog({
    this.plans = const [],
    this.comparison = const [],
  });

  final List<PlanCatalogItem> plans;
  final List<Map<String, dynamic>> comparison;

  factory PlansCatalog.fromJson(Map<String, dynamic> json) {
    final plans = asMapList(json['plans']).map(PlanCatalogItem.fromJson).toList();
    final comparisonRaw = json['comparison'] ?? json['comparison_rows'];
    final comparison = comparisonRaw is List
        ? comparisonRaw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
        : <Map<String, dynamic>>[];
    return PlansCatalog(plans: plans, comparison: comparison);
  }

  @override
  List<Object?> get props => [plans, comparison];
}
