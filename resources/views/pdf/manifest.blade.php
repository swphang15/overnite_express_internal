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
            margin: -30px;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
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
            font-size: 15px;
            font-weight: bold;
        }

        .reg-no {
            font-size: 13px;
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

    <!-- ✅ 顶部表格（公司信息 + 单号） -->
    <table>
        <tr>
            <td style="width: 32.2%; text-align: left; vertical-align: middle;">
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

            <td style="width: 10.7%;">
                <span style="font-size: 14px; font-weight: bold;">DATE</span><br>
                <span style="font-size: 12px;">{{ $date->format('Y-m-d') }}</span><br>
                <span style="font-size: 11px;">({{ $date->format('l') }})</span>
            </td>

            <td style="width: 15%;">
                <span style="font-size: 14px; font-weight: bold;">AWB No.</span><br>
                <span style="font-size: 12px;">{{ $manifestInfo->awb_no }}</span>
            </td>

            <td style="width: 8%;">
                <span style="font-size: 14px; font-weight: bold;">TO</span><br>
                <span style="font-size: 12px;">{{ $manifestInfo->to }}</span>
            </td>

            <td style="width: 8%;">
                <span style="font-size: 14px; font-weight: bold;">FROM</span><br>
                <span style="font-size: 12px;">{{ $manifestInfo->from }}</span>
            </td>

            <td style="width: 8%;">
                <span style="font-size: 15px; font-weight: bold;">FLT</span><br>
                <span style="font-size: 13px;">{{ $manifestInfo->flt }}</span>
            </td>

            <td style="width: 18%;" class="manifest-no">
                <span style="font-size: 14px; font-weight: bold;">Manifest No.</span><br>
                <span style="font-size: 12px;">{{ $manifestInfo->manifest_no }}</span>
            </td>

        </tr>
    </table>

    <!-- ✅ 主表格（数据部分） -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 8%;">Origin</th>
                <th style="width: 8%;">Destination</th> <!-- ✅ 新增 Destination -->
                <th style="width: 11%;">Consignor</th>
                <th style="width: 11%;">Consignee</th>
                <th style="width: 15%;">CN No</th>
                <th style="width: 8%;">PCS</th>
                <th style="width: 8%;">KG</th>
                <th style="width: 8%;">GM</th>
                <th colspan="4" style="width: 18%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($manifestLists as $index => $list)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $list->origin }}</td>
                    <td>{{ $list->destination }}</td> <!-- ✅ 新增 Destination -->
                    <td>{{ $list->consignor->name }}</td>
                    <td>{{ $list->consignee_name }}</td>
                    <td>{{ $list->cn_no }}</td>
                    <td>{{ $list->pcs }}</td>
                    <td>{{ $list->kg }}</td>
                    <td>{{ $list->gram }}</td>
                    <td style="width: 5%;"></td>
                    <td style="width: 5%;"></td>
                    <td style="width: 5%;"></td>
                    <td style="width: 5%;"></td>
                </tr>
            @endforeach

            <!-- ✅ 最后一行：计算 Manifest Weight & Total PCS -->
            <tr>
                <td></td> <!-- No 列空白 -->
                <td colspan="3" style="text-align: center; font-weight: bold;">Manifest Weight:</td>
                <td colspan="1" style="text-align: center;">
                    {{ number_format($manifestLists->sum('kg') + $manifestLists->sum('gram') / 1000, 2) }} KG
                </td>

                <td colspan="1" style="text-align: center; font-weight: bold;">Total PCS:</td>
                <td colspan="1" style="text-align: center;">{{ $manifestLists->sum('pcs') }}</td>
                <td colspan="2" style="text-align: center; font-weight: bold;">AWB WEIGHT:</td>
                <td colspan="1"></td> <!-- 让出位置，避免错位 -->
                <td colspan="1"></td>
                <td colspan="1"></td>
                <td colspan="1"></td>
            </tr>

        </tbody>
    </table>

</body>

</html>
