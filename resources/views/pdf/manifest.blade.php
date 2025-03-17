<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifest PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid black;
            padding: 2px;
            text-align: center;
            white-space: nowrap;
        }
        .header-logo {
            width: 40px;
            height: auto;
            vertical-align: middle;
        }
        .company-info {
            text-align: left;
            font-size: 12px;
            font-weight: bold;
        }
        .reg-no {
            font-size: 10px;
            font-weight: normal;
        }
        .manifest-no {
            color: red;
            font-weight: bold;
        }
        .left-align {
            text-align: left;
            padding-left: 5px;
        }
    </style>
</head>
<body>

    {{-- **📌 顶部表格，包含公司信息 + 单号信息（列宽匹配下方数据）** --}}
    <table>
        {{-- **✅ 第一行：MANIFEST LIST** --}}
        <tr>
            <th colspan="9">MANIFEST LIST</th>
        </tr>
        {{-- **✅ 第二行：Logo + 公司信息 + 单号信息（列宽对齐数据表格）** --}}
        <tr>
        <td colspan="3" style="width: 35%;">
    <table style="border: none;">
        <tr>
            <!-- Logo 和 公司名称（同一行） -->
            <td style="width: 50px; vertical-align: middle; border: none;">
                <img src="{{ public_path('images/overnite express logo.png') }}" class="header-logo" 
                     style="max-width: 45px; height: auto;">
            </td>
            <td style="text-align: left; padding-left: 5px; vertical-align: middle; border: none;">
                <span class="company-info" style="font-size: 12px; font-weight: bold;">
                    OVERNITE EXPRESS  &nbsp;&nbsp;&nbsp;<br>(S) SDN BHD
                </span>
            </td>
        </tr>
        <tr>
            <!-- Reg. No. 独立一行，跨两列 -->
            <td colspan="2" style="text-align: left; padding-top: 5px; font-size: 10px; border: none;">
                <span class="reg-no">Reg. No: 199001000275 / 191833U</span>
            </td>
        </tr>
    </table>
</td>


            <td colspan="6" class="left-align">
                <strong>DATE:</strong> {{ $manifestInfo->date }} &nbsp;
                <strong>AWB No.:</strong> {{ $manifestInfo->awb_no }} &nbsp;
                <strong>TO:</strong> {{ $manifestInfo->to }} &nbsp;
                <strong>FROM:</strong> {{ $manifestInfo->from }} &nbsp;
                <strong>FLT:</strong> {{ $manifestInfo->flt }} &nbsp;
                <strong>Manifest No.:</strong> <span class="manifest-no">{{ $manifestInfo->manifest_no }}</span>
            </td>
        </tr>
    </table>

    {{-- **📌 主表格（数据部分）** --}}
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Origin</th>
                <th>Consignor</th>
                <th>Consignee</th>
                <th>CN No</th>
                <th>PCS</th>
                <th>KG</th>
                <th>GM</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($manifestLists as $index => $list)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $list->origin }}</td>
                <td>{{ $list->consignor->name }}</td>
                <td>{{ $list->consignee_name }}</td>
                <td>{{ $list->cn_no }}</td>
                <td>{{ $list->pcs }}</td>
                <td>{{ $list->kg }}</td>
                <td>{{ $list->gram }}</td>
                <td>{{ $list->remarks }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- **📌 底部合计信息** --}}
    <p><strong>Manifest Weight:</strong> {{ $manifestLists->sum('kg') }} KG</p>
    <p><strong>Total PCS:</strong> {{ $manifestLists->sum('pcs') }}</p>

</body>
</html>
