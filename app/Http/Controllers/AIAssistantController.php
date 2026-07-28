<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIService;
use App\Models\Appointment;
use App\Models\Medicine;

class AIAssistantController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $user = $request->user();
        
        // 1. Format Appointments Context
        $appointments = Appointment::where('user_id', $user->id)
            ->orderBy('date_time', 'asc')
            ->get()
            ->map(function ($appt) {
                $dt = $appt->date_time ? $appt->date_time->format('Y-m-d h:i A') : 'N/A';
                return "- Doctor: {$appt->doctor_name}, Clinic/Hospital: {$appt->clinic}, Date & Time: {$dt}, Reason: " . ($appt->reason ?? 'None');
            })
            ->implode("\n");

        // 2. Format Medicines Context
        $medicines = Medicine::where('user_id', $user->id)
            ->where('is_reminder', true)
            ->get()
            ->map(function ($m) {
                return "- {$m->name} ({$m->dosage}) at {$m->time} (Slot: " . ($m->raw_notes ?? 'N/A') . ")";
            })
            ->implode("\n");

        $context = [
            'userName'     => $user->name,
            'appointments' => $appointments,
            'medicines'    => $medicines,
        ];

        $result = $this->aiService->askAssistant($request->prompt, $context);

        return response()->json([
            'success'  => true,
            'reply'    => $result['reply'],
            'provider' => $result['provider'],
            'time'     => date('h:i A'),
        ]);
    }
}
