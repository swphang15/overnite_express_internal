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
            padding: 5px;
            text-align: center;
            white-space: nowrap;
            height: 20px;
        }
        .header-logo {
            width: 45px;
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

    @php
        use Carbon\Carbon;
        $date = Carbon::parse($manifestInfo->date);
    @endphp

    {{-- **📌 顶部表格（公司信息 + 单号）** --}}
    <table>
        <tr>
            <td style="width: 22%; text-align: left; vertical-align: middle;">
                <table style="border: none; width: 100%;">
                    <tr>
                        <td style="width: 50px; vertical-align: middle; border: none;">
                            <img src="{{ public_path('images/overnite express logo.png') }}" class="header-logo">
                        </td>
                        <td style="border: none; vertical-align: middle; text-align: left;">
                            <span class="company-info">
                                OVERNITE EXPRESS<br>(S) SDN BHD
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: left; font-size: 10px; border: none;">
                            <span class="reg-no">Reg. No: 199001000275 / 191833U</span>
                        </td>
                    </tr>
                </table>
            </td>

            {{-- ✅ 修改 DATE 部分，显示日期 + 星期几 --}}
            <td style="width: 10%;">
                <strong>DATE</strong><br>
                {{ $date->format('Y-m-d') }}<br> 
                ({{ $date->format('l') }}) {{-- 显示完整的星期几，比如 Monday --}}
            </td>

            <td style="width: 15%;"><strong>AWB No.</strong><br>{{ $manifestInfo->awb_no }}</td>
            <td style="width: 5%;"><strong>TO</strong><br>{{ $manifestInfo->to }}</td>
            <td style="width: 5%;"><strong>FROM</strong><br>{{ $manifestInfo->from }}</td>
            <td style="width: 5%;"><strong>FLT</strong><br>{{ $manifestInfo->flt }}</td>
            <td style="width: 12%;" class="manifest-no"><strong>Manifest No.</strong><br>{{ $manifestInfo->manifest_no }}</td>
        </tr>
    </table>

    {{-- **📌 主表格（数据部分）** --}}
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 8%;">Origin</th>
                <th style="width: 10%;">Consignor</th>
                <th style="width: 10%;">Consignee</th>
                <th style="width: 15%;">CN No</th>
                <th style="width: 5%;">PCS</th>
                <th style="width: 5%;">KG</th>
                <th style="width: 5%;">GM</th>
                <th colspan="4">Remarks</th>  {{-- 让 Remarks 标题占据 4 个格子 --}}
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
                <td style="width: 3%;"></td>
                <td style="width: 3%;"></td>
                <td style="width: 3%;"></td>
                <td style="width: 3%;"></td>
            </tr>
            @endforeach

            {{-- ✅ 在最后一行添加 Manifest Weight 和 Total PCS --}}
            <tr>
                <td></td>  {{-- No 列空白 --}}
                <td colspan="2" style="text-align: center; font-weight: bold;">Manifest Weight:</td>
                <td colspan="1" style="text-align: center;">
                    {{-- 计算总重量：KG + (GM/1000) --}}
                    {{ $manifestLists->sum('kg') + ($manifestLists->sum('gram') / 1000) }} KG
                </td>
                <td colspan="1" style="text-align: center; font-weight: bold;">Total PCS:</td>
                <td colspan="1" style="text-align: center;">{{ $manifestLists->sum('pcs') }}</td>
                <td colspan="2" style="text-align: center; font-weight: bold;">AWB WEIGHT:</td>
                <td colspan="1"></td> {{-- 让出位置，避免错位 --}}
                <td colspan="1"></td>
                <td colspan="1"></td>
                <td colspan="1"></td>
            </tr>

        </tbody>
    </table>

</body>
</html>
