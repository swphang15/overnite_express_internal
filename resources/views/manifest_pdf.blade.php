<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifest Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        tfoot td { font-weight: bold; background-color: #e0e0e0; }
    </style>
</head>
<body>
    <h2 style="text-align: center;">Manifest Report</h2>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Consignment Note</th>
                <th>Delivery Date</th>
                <th>Qty</th>
                <th>U/ Price RM</th>
                <th>Total RM</th>
            </tr>
        </thead>
        <tbody>
            @php $totalAmount = 0; @endphp
            @foreach($manifests as $index => $manifest)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $manifest->from }} - {{ $manifest->to }}</td>
                <td>{{ $manifest->cn_no }}</td>
                <td>{{ $manifest->date }}</td>
                <td>{{ $manifest->pcs }}</td>
                <td>{{ number_format($manifest->price_per_kg, 2) }}</td>
                <td>{{ number_format($manifest->total_price, 2) }}</td>
            </tr>
            @php $totalAmount += $manifest->total_price; @endphp
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align: left;">Total Price:</td>
                <td>{{ number_format($totalAmount, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
