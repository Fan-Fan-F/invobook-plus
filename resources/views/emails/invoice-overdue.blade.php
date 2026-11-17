<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Overdue Invoice Reminder</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; padding: 20px;">
    <h2>Payment Reminder</h2>
    <p>Dear {{ $invoice->client?->name ?? 'Valued Customer' }},</p>
    <p>This is a friendly reminder that invoice <strong>{{ $invoice->invoice_number }}</strong> was due on <strong>{{ $invoice->due_date->format('Y-m-d') }}</strong> and remains unpaid.</p>
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>Total Amount:</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $invoice->currency->format($invoice->total_cents) }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>Amount Paid:</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $invoice->currency->format($invoice->paid_cents) }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>Outstanding Balance:</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd; color: #d9534f; font-weight: bold;">{{ $invoice->currency->format($invoice->balanceCents()) }}</td>
        </tr>
    </table>
    <p>Please arrange payment at your earliest convenience. If you have already made this payment, please disregard this notice.</p>
    <p>Thank you for your business.</p>
</body>
</html>
