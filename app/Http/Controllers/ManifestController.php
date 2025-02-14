<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use Illuminate\Http\Request;

class ManifestController extends Controller
{
    public function index()
    {
        return response()->json(Manifest::all());
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

        $manifest = Manifest::create($request->all());
        return response()->json($manifest, 201);
    }

    public function show($id)
    {
        return response()->json(Manifest::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $manifest = Manifest::findOrFail($id);
        $manifest->update($request->all());
        return response()->json($manifest);
    }

    public function destroy($id)
    {
        Manifest::destroy($id);
        return response()->json(null, 204);
    }
}
