<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return response()->json(Report::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'origin' => 'required|string',
            'consignor' => 'required|string',
            'consignee' => 'required|string',
            'cn_no' => 'required|string',
            'pcs' => 'required|integer',
            'kg' => 'required|integer',
            'gram' => 'required|integer',
            'remarks' => 'nullable|string',
            'date' => 'required|date',
            'awb_no' => 'required|string',
            'to' => 'required|string',
            'from' => 'required|string',
            'flt' => 'required|string',
            'manifest_no' => 'required|string',
        ]);

        $report = Report::create($request->all());
        return response()->json($report, 201);
    }

    public function show($id)
    {
        return response()->json(Report::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $report->update($request->all());
        return response()->json($report);
    }

    public function destroy($id)
    {
        Report::destroy($id);
        return response()->json(null, 204);
    }
}
