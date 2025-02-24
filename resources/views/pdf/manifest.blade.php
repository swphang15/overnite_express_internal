<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifest Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h2, h3 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .details p {
            margin: 5px 0;
        }
    </style>
</head>
<body>

    <h2>Manifest Report</h2>
    <h3>ID: {{ $manifest->id }}</h3>

    <div class="details">
        <p><strong>Origin:</strong> {{ $manifest->origin }}</p>
        <p><strong>Consignor:</strong> {{ $manifest->consignor->name ?? 'N/A' }}</p>
        <p><strong>Consignee:</strong> {{ $manifest->consignee->name ?? 'N/A' }}</p>
        <p><strong>CN No:</strong> {{ $manifest->cn_no }}</p>
        <p><strong>Total Price:</strong> RM {{ number_format($manifest->total_price, 2) }}</p>
        <p><strong>Date:</strong> {{ $manifest->date }}</p>
    </div>

    <h3>Items</h3>
    @if (!empty($manifest->items) && is_iterable($manifest->items))
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Price (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($manifest->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No items available.</p>
    @endif

</body>
</html>
