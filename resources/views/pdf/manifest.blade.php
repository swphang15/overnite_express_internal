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
            margin: -35px;
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
            font-size: 184%;
            font-weight: bold;
        }

        .reg-no {
            font-size: 14px;
            font-weight: normal;
        }

        .manifest-no {
            color: red;
            font-weight: bold;
        }

        .header-text {
            font-size: 14px;
            font-weight: bold;
        }

        .data-text {
            font-size: 14px;
        }

        .summary-text {
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    @php
        use Carbon\Carbon;
        $date = Carbon::parse($manifestInfo->date);
    @endphp

    <!-- 顶部公司与航班信息 -->
    <table>
        <tr>
            <td style="width: 32%; text-align: left; vertical-align: middle;">
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

            <td style="width: 12%;">
                <span style="font-size: 15px; font-weight: bold;">DATE</span><br>
                <span style="font-size: 13px;">{{ $date->format('Y-m-d') }}</span><br>
                <span style="font-size: 13px;">({{ $date->format('l') }})</span>
            </td>

            <td style="width: 14%;">
                <span style="font-size: 15px; font-weight: bold;">AWB No.</span><br>
                <span style="font-size: 13px;">{{ $manifestInfo->awb_no }}</span>
            </td>

            <td style="width: 8%;">
                <span style="font-size: 15px; font-weight: bold;">TO</span><br>
                <span style="font-size: 13px;">{{ $manifestInfo->to }}</span>
            </td>

            <td style="width: 8%;">
                <span style="font-size: 15px; font-weight: bold;">FROM</span><br>
                <span style="font-size: 13px;">{{ $manifestInfo->from }}</span>
            </td>

            <td style="width: 8%;">
                <span style="font-size: 15px; font-weight: bold;">FLT</span><br>
                <span style="font-size: 13px;">{{ $manifestInfo->flt }}</span>
            </td>

            <td style="width: 18%;" class="manifest-no">
                <span style="font-size: 15px; font-weight: bold;">Manifest No.</span><br>
                <span style="font-size: 13px;">{{ $manifestInfo->manifest_no }}</span>
            </td>
        </tr>
    </table>

    <!-- 数据表格 -->
    <table>
        <thead>
            <tr class="header-text">
                <th style="width: 5%;">No</th>
                <th style="width: 8%;">Origin</th>
                <th style="width: 8%;">Destination</th>
                <th style="width: 11%;">Consignor</th>
                <th style="width: 12%;">Consignee</th>
                <th style="width: 14%;">CN No</th>
                <th style="width: 8%;">PCS</th>
                <th style="width: 8%;">KG</th>
                <th style="width: 8%;">GM</th>
                <th style="width: 10%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($manifestLists as $index => $list)
                <tr class="data-text">
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $list->origin }}</td>
                    <td>{{ $list->destination }}</td>
                    <td>{{ $list->consignor->name }}</td>
                    <td>{{ $list->consignee_name }}</td>
                    <td>{{ $list->cn_no }}</td>
                    <td>{{ $list->pcs }}</td>
                    <td>{{ $list->kg }}</td>
                    <td>{{ $list->gram }}</td>
                    <td>{{ $list->remarks }}</td>
                </tr>
            @endforeach

            <!-- 总结行 -->
            <tr class="summary-text">
                <td></td> {{-- No --}}
                <td colspan="3" style="text-align: center;">Manifest Weight:</td> {{-- Origin + Destination + Consignor --}}
                <td style="text-align: center;">
                    {{ number_format($manifestLists->sum('kg') + $manifestLists->sum('gram') / 1000, 2) }} KG
                </td> {{-- Consignee --}}
                <td style="text-align: center;">Total PCS:</td> {{-- CN No --}}
                <td style="text-align: center;">{{ $manifestLists->sum('pcs') }}</td> {{-- PCS --}}
                <td colspan="2" style="text-align: left;">AWB WEIGHT:</td> {{-- KG + GM 合并 --}}
                <td></td> {{-- Remarks --}}
            </tr>


        </tbody>
    </table>

</body>

</html>
