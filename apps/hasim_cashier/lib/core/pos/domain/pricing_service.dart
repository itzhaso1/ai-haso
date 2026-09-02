import '../pos_errors.dart';

/// Single rounding policy for the entire POS: half-up to 2 decimal places.
class Money {
  const Money._();

  static double round(num value) {
    return (value * 100).round() / 100.0;
  }
}

class PricedLine {
  const PricedLine({
    required this.productLocalId,
    required this.name,
    required this.quantity,
    required this.unitPrice,
    this.itemDiscount = 0,
    this.taxRate = 0,
    this.sku,
    this.barcode,
    this.cost = 0,
    this.productServerId,
  });

  final String productLocalId;
  final int? productServerId;
  final String name;
  final int quantity;
  final double unitPrice;
  final double itemDiscount;
  final double taxRate;
  final String? sku;
  final String? barcode;
  final double cost;

  double get lineSubtotal => Money.round(quantity * unitPrice);
}

class PriceBreakdown {
  const PriceBreakdown({
    required this.lines,
    required this.subtotal,
    required this.itemDiscountTotal,
    required this.orderDiscount,
    required this.taxAmount,
    required this.total,
    required this.lineResults,
  });

  final List<PricedLine> lines;
  final double subtotal;
  final double itemDiscountTotal;
  final double orderDiscount;
  final double taxAmount;
  final double total;
  final List<PricedLineResult> lineResults;
}

class PricedLineResult {
  const PricedLineResult({
    required this.line,
    required this.lineSubtotal,
    required this.discountAmount,
    required this.taxAmount,
    required this.total,
  });

  final PricedLine line;
  final double lineSubtotal;
  final double discountAmount;
  final double taxAmount;
  final double total;
}

class PricingService {
  const PricingService();

  PriceBreakdown quote({
    required List<PricedLine> lines,
    double orderDiscountAmount = 0,
    double orderDiscountPercent = 0,
    double fallbackTaxRate = 0,
  }) {
    if (lines.isEmpty) {
      return const PriceBreakdown(
        lines: [],
        subtotal: 0,
        itemDiscountTotal: 0,
        orderDiscount: 0,
        taxAmount: 0,
        total: 0,
        lineResults: [],
      );
    }
    for (final line in lines) {
      if (line.quantity <= 0) {
        throw const InvalidDiscount();
      }
      if (line.unitPrice < 0 || line.itemDiscount < 0) {
        throw const InvalidDiscount();
      }
      if (line.itemDiscount > line.lineSubtotal) {
        throw const InvalidDiscount();
      }
    }

    var subtotal = 0.0;
    var itemDiscountTotal = 0.0;
    for (final line in lines) {
      subtotal = Money.round(subtotal + line.lineSubtotal);
      itemDiscountTotal = Money.round(itemDiscountTotal + line.itemDiscount);
    }
    final afterItems = Money.round(subtotal - itemDiscountTotal);
    var orderDiscount = 0.0;
    if (orderDiscountPercent > 0) {
      if (orderDiscountPercent > 100) throw const InvalidDiscount();
      orderDiscount = Money.round(afterItems * (orderDiscountPercent / 100));
    }
    if (orderDiscountAmount > 0) {
      orderDiscount = Money.round(orderDiscount + orderDiscountAmount);
    }
    if (orderDiscount > afterItems) throw const InvalidDiscount();

    final taxable = Money.round(afterItems - orderDiscount);
    final results = <PricedLineResult>[];
    var taxTotal = 0.0;
    final weightBase = afterItems <= 0 ? 1.0 : afterItems;

    for (final line in lines) {
      final lineNet = Money.round(line.lineSubtotal - line.itemDiscount);
      final share = lineNet / weightBase;
      final lineOrderDiscount = Money.round(orderDiscount * share);
      final lineTaxable = Money.round(lineNet - lineOrderDiscount);
      final rate = line.taxRate > 0 ? line.taxRate : fallbackTaxRate;
      if (rate < 0 || rate > 100) throw const InvalidDiscount();
      final lineTax = Money.round(lineTaxable * (rate / 100));
      taxTotal = Money.round(taxTotal + lineTax);
      results.add(
        PricedLineResult(
          line: line,
          lineSubtotal: line.lineSubtotal,
          discountAmount: Money.round(line.itemDiscount + lineOrderDiscount),
          taxAmount: lineTax,
          total: Money.round(lineTaxable + lineTax),
        ),
      );
    }

    final total = Money.round(taxable + taxTotal);
    if (total < 0) throw const InvalidDiscount();

    return PriceBreakdown(
      lines: lines,
      subtotal: subtotal,
      itemDiscountTotal: itemDiscountTotal,
      orderDiscount: orderDiscount,
      taxAmount: taxTotal,
      total: total,
      lineResults: results,
    );
  }
}
