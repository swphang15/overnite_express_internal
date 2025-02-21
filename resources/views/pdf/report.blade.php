<!DOCTYPE html>
<html>
<head>
    <title>Logistic Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Logistic Report</h2>
    <table>
        <tr>
            <th>Origin</th>
            <th>Consignor</th>
            <th>Consignee</th>
            <th>C/N No.</th>
            <th>PCS</th>
            <th>KG</th>
            <th>GRAM</th>
            <th>Remarks</th>
            <th>Date</th>
            <th>AWB No.</th>
            <th>To</th>
            <th>From</th>
            <th>FLT</th>
            <th>Manifest No.</th>
        </tr>
        @foreach($records as $record)
        <tr>
            <td>{{ $record->origin }}</td>
            <td>{{ $record->consignor }}</td>
            <td>{{ $record->consignee }}</td>
            <td>{{ $record->cn_no }}</td>
            <td>{{ $record->pcs }}</td>
            <td>{{ $record->kg }}</td>
            <td>{{ $record->gram }}</td>
            <td>{{ $record->remarks }}</td>
            <td>{{ $record->date }}</td>
            <td>{{ $record->awb_no }}</td>
            <td>{{ $record->to }}</td>
            <td>{{ $record->from }}</td>
            <td>{{ $record->flt }}</td>
            <td>{{ $record->manifest_no }}</td>
        </tr>
        @endforeach
    </table>
</body>
</html>
