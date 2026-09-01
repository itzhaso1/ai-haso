import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';

class EmailComposeScreen extends ConsumerStatefulWidget {
  const EmailComposeScreen({
    super.key,
    this.replyTo,
    this.prefillTo,
    this.prefillSubject,
    this.accountId,
  });

  final EmailMessageModel? replyTo;
  final String? prefillTo;
  final String? prefillSubject;
  final int? accountId;

  @override
  ConsumerState<EmailComposeScreen> createState() => _EmailComposeScreenState();
}

class _EmailComposeScreenState extends ConsumerState<EmailComposeScreen> {
  final _to = TextEditingController();
  final _subject = TextEditingController();
  final _body = TextEditingController();
  List<EmailAccountModel> _accounts = [];
  int? _accountId;
  bool _sending = false;
  bool _loadingAccounts = true;

  @override
  void initState() {
    super.initState();
    _to.text = widget.prefillTo ?? widget.replyTo?.sender ?? '';
    _subject.text = widget.prefillSubject ??
        (widget.replyTo?.subject == null ? '' : 'Re: ${widget.replyTo!.subject}');
    _accountId = widget.accountId ?? widget.replyTo?.emailAccountId;
    _loadAccounts();
  }

  Future<void> _loadAccounts() async {
    try {
      final accounts = await ref.read(emailRepositoryProvider).accounts();
      if (!mounted) return;
      setState(() {
        _accounts = accounts;
        _accountId ??= accounts.isNotEmpty ? accounts.first.id : null;
        _loadingAccounts = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingAccounts = false);
    }
  }

  @override
  void dispose() {
    _to.dispose();
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_accountId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('اختر حساب الإرسال أولاً.')),
      );
      return;
    }
    setState(() => _sending = true);
    try {
      await ref.read(emailRepositoryProvider).send(
            emailAccountId: _accountId!,
            to: _to.text.trim(),
            subject: _subject.text.trim(),
            body: _body.text.trim(),
            replyToMessageId: widget.replyTo?.id,
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم إرسال الرسالة')));
        context.pop();
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تعذر الإرسال')));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.replyTo == null ? 'رسالة جديدة' : 'رد')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_loadingAccounts)
            const LinearProgressIndicator(minHeight: 2)
          else if (_accounts.isEmpty)
            const Text('لا توجد حسابات بريد مرتبطة. أضف حساباً من لوحة الإدارة.')
          else
            DropdownMenu<int>(
              initialSelection: _accountId,
              label: const Text('حساب الإرسال'),
              expandedInsets: EdgeInsets.zero,
              onSelected: (v) => setState(() => _accountId = v),
              dropdownMenuEntries: [
                for (final a in _accounts)
                  DropdownMenuEntry(value: a.id, label: '${a.name} · ${a.email}'),
              ],
            ),
          const SizedBox(height: 12),
          TextField(
            controller: _to,
            decoration: const InputDecoration(labelText: 'إلى'),
            textDirection: TextDirection.ltr,
            textAlign: TextAlign.left,
          ),
          const SizedBox(height: 12),
          TextField(controller: _subject, decoration: const InputDecoration(labelText: 'الموضوع')),
          const SizedBox(height: 12),
          TextField(controller: _body, decoration: const InputDecoration(labelText: 'النص'), minLines: 8, maxLines: 16),
          const SizedBox(height: 16),
          FilledButton(onPressed: _sending ? null : _send, child: Text(_sending ? 'جارٍ الإرسال...' : 'إرسال')),
        ],
      ),
    );
  }
}
