<!DOCTYPE html>
<html>
<head>
    <title>Sales Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #000; }
        th, td { padding: 8px; text-align: left; }
        h2 { text-align: center; margin-bottom: 0; }
        p { text-align: center; margin: 0; }
    </style>
</head>
<body>
    <h2>Sales Report</h2>
    <p>From {{ $from_date }} to {{ $to_date }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Member Code</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Discount</th>
                <th>Total Pay</th>
                <th>Cashier</th>
            </tr>
        </thead>
        <tbody>
            @foreach($penjualan as $index => $sale)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $sale->created_at->format('Y-m-d') }}</td>
                <td>{{ $sale->member->kode_member ?? 'N/A' }}</td>
                <td>{{ $sale->total_item }}</td>
                <td>{{ number_format($sale->total_harga, 2) }}</td>
                <td>{{ $sale->diskon }}%</td>
                <td>{{ number_format($sale->bayar, 2) }}</td>
                <td>{{ $sale->user->name ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
