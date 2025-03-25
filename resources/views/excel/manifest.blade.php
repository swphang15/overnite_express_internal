<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .header-left {
            width: 60%;
        }

        .header-right {
            text-align: right;
            font-weight: bold;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: red;
            /* 📌 公司名变红 */
        }

        .client-info {
            font-weight: bold;
            font-size: 14px;
            margin-top: 5px;
        }

        .contact-info {
            font-size: 12px;
            margin-bottom: 10px;
        }

        h2 {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid black;
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 14px;
        }

        td {
            font-size: 13px;
        }

        .total {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>

<body>

    <!-- ✅ 头部信息 -->
    <div class="header">
        <div class="header-left">
            <p class="company-name">COMPANY A</p>
            <p class="client-info">CLIENT / DESTINATION</p>
            <p class="contact-info">TEL : _____________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; FAX : _____________</p>
        </div>
        <div class="header-right">
            <p>No. : <strong>I-2411-13</strong></p>
            <p>Your Ref. : ___________</p>
            <p>Our D/O No. : ___________</p>
            <p>Terms : <strong>C.O.D.</strong></p>
            <p>Date : <strong>{{ now()->format('d/m/Y') }}</strong></p>
            <p>Page : <strong>1 of 1</strong></p>
        </div>
    </div>

    <h2>MANIFEST LIST REPORT</h2>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Consignment Note</th>
                <th>Delivery Date</th>
                <th>Qty</th>
                <th>UOM</th>
                <th>U/Price RM</th>
                <th>Disc.</th>
                <th>Total RM</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($manifestLists as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>DCN {{ $item->origin }} - {{ $item->destination }}</td>
                    <td>{{ $item->cn_no }}</td>
                    <td>{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $item->pcs }}</td>
                    <td>KG</td>
                    <td>4.00</td>
                    <td>0.00</td>
                    <td>{{ number_format($item->total_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total">Total Price: RM {{ number_format($manifestLists->sum('total_price'), 2) }}</p>

</body>

</html>
