<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LabReport;
use App\Services\OCRService;

class LabReportController extends Controller
{
    protected OCRService $ocrService;

    public function __construct(OCRService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    public function index(Request $request)
    {
        $reports = LabReport::where('user_id', $request->user()->id)
            ->orderBy('report_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    public function show(Request $request, $id)
    {
        $report = LabReport::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'report'  => $report,
        ]);
    }

    // Scan & Process Report via AI / OCR (Returns extraction data without creating database record yet)
    public function scan(Request $request)
    {
        $request->validate([
            'report_type' => 'required|string',
            'image'       => 'required|image|mimes:jpeg,png,jpg,webp,pdf|max:10240',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = '/storage/' . $request->file('image')->store('lab_reports', 'public');
        }

        // Process AI Extraction
        $ocrResult = $this->ocrService->processReportImage(str_replace('/storage/', '', $imagePath ?? ''), $request->report_type);

        return response()->json([
            'success'     => true,
            'message'     => 'Lab report scanned successfully',
            'image_path'  => $imagePath,
            'extraction'  => $ocrResult,
        ]);
    }

    // Save a reviewed lab report to database
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'lab_name'    => 'required|string',
            'report_type' => 'required|string',
            'report_date' => 'required|date',
            'status'      => 'required|string',
            'parameters'  => 'required|array',
            'notes'       => 'nullable|string',
            'image_path'  => 'nullable|string',
        ]);

        $report = LabReport::create([
            'user_id'     => $request->user()->id,
            'name'        => $request->name,
            'lab_name'    => $request->lab_name,
            'report_type' => $request->report_type,
            'report_date' => $request->report_date,
            'status'      => $request->status,
            'image_path'  => $request->image_path,
            'ocr_text'    => $request->ocr_text ?? 'AI OCR Scanned',
            'parameters'  => $request->parameters,
            'notes'       => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lab report saved successfully',
            'report'  => $report,
        ], 201);
    }
}
