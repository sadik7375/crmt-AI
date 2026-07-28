<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $appointments = Appointment::where('user_id', $request->user()->id)
            ->orderBy('date_time', 'asc')
            ->get();

        return response()->json([
            'success'      => true,
            'appointments' => $appointments,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'doctor_name' => 'required|string',
            'specialty'   => 'nullable|string',
            'clinic'      => 'required|string',
            'date_time'   => 'required|date',
        ]);

        $appointment = Appointment::create([
            'user_id'        => $request->user()->id,
            'doctor_name'    => $request->doctor_name,
            'specialty'      => $request->specialty ?? 'General',
            'clinic'         => $request->clinic,
            'date_time'      => $request->date_time,
            'reason'         => $request->reason,
            'reminder_alert' => $request->reminder_alert ?? '1Day',
            'is_past'        => false,
        ]);

        return response()->json([
            'success'     => true,
            'appointment' => $appointment,
        ], 201);
    }

    public function parseVoice(Request $request)
    {
        $request->validate([
            'audio'     => 'required|string',
            'mime_type' => 'required|string',
        ]);

        $aiService = app(\App\Services\AIService::class);
        $prompt = 'You are an AI Health Assistant. Parse the following voice message into structured JSON with these fields: "doctorName" (string), "clinic" (string, hospital/clinic name), "dateTime" (string in YYYY-MM-DD HH:MM format, or empty if not mentioned), "reason" (string, reason for visit). Return ONLY the raw JSON string without any Markdown formatting or backticks.';

        $result = $aiService->parseMultimodalImage($request->audio, $request->mime_type, $prompt);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Voice parsing failed'
            ], 422);
        }

        // Clean markdown backticks wrapper
        $cleanJson = trim($result['reply']);
        if (str_starts_with($cleanJson, '```')) {
            $cleanJson = preg_replace('/^```(?:json)?|```$/m', '', $cleanJson);
            $cleanJson = trim($cleanJson);
        }

        $data = json_decode($cleanJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse JSON reply from AI model: ' . json_last_error_msg()
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }
}
