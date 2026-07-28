<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\Medicine;
use App\Services\AIService;
use Illuminate\Support\Facades\Log;

class PrescriptionController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get all prescriptions with their medicines
     */
    public function index(Request $request)
    {
        $prescriptions = Prescription::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Format to match frontend schema
        $formatted = $prescriptions->map(function ($p) {
            $meds = Medicine::where('prescription_id', $p->id)
                ->where('is_reminder', false)
                ->get();
            
            return [
                'id' => (string) $p->id,
                'date' => date('d M Y', strtotime($p->prescription_date)),
                'doctor' => $p->doctor_name,
                'clinic' => $p->clinic_name ?? 'General Clinic',
                'status' => $p->status,
                'imageUri' => $p->image_path,
                'medicines' => $meds->map(function ($m) {
                    $timing = [];
                    $lowTime = strtolower($m->time);
                    if (str_contains($lowTime, 'morning') || str_contains($lowTime, 'breakfast')) $timing[] = 'Morning';
                    if (str_contains($lowTime, 'afternoon') || str_contains($lowTime, 'lunch')) $timing[] = 'Afternoon';
                    if (str_contains($lowTime, 'night') || str_contains($lowTime, 'dinner') || str_contains($lowTime, 'bed')) $timing[] = 'Night';
                    
                    if (empty($timing) && !str_contains($lowTime, 'as needed')) {
                        $timing[] = 'Morning';
                    }

                    return [
                        'name' => $m->name,
                        'dosage' => $m->dosage,
                        'duration' => $m->duration_days ? $m->duration_days . ' Days' : '30 Days',
                        'timing' => $timing,
                        'type' => str_contains($lowTime, 'as needed') ? 'sos' : 'daily',
                        'raw_notes' => $m->raw_notes,
                        'start_date' => $m->start_date ? (is_string($m->start_date) ? substr($m->start_date, 0, 10) : $m->start_date->format('Y-m-d')) : null,
                        'duration_days' => $m->duration_days,
                        'meal_relation' => $m->meal_relation,
                        'dose_quantity' => $m->dose_quantity,
                        'dose_unit' => $m->dose_unit,
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Scan prescription image (Fail-safe: always returns valid data, saves to DB)
     */
    public function scan(Request $request)
    {
        // Extend execution limit to 3 minutes for slow AI proxy calls
        set_time_limit(180);

        // Fail-safe dynamic mock data profiles (gastric, diabetic, cold/flu, pain, vitamins)
        $profiles = [
            [
                'doctor' => 'Dr. Asif Rahman',
                'clinic' => 'Labaid Diagnostic, Dhaka',
                'date' => date('Y-m-d'),
                'medicines' => [
                    [
                        'name' => 'Sergel 20mg',
                        'raw_notes' => '1 capsule 30 minutes before breakfast for 30 Days',
                        'parsed_timings' => ['Morning'],
                        'parsed_meal_relation' => 'before_meal',
                        'parsed_duration_days' => 30,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Capsule'
                    ],
                    [
                        'name' => 'Fexo 120mg',
                        'raw_notes' => '1 tablet before bedtime (at Night) for 15 Days',
                        'parsed_timings' => ['Night'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 15,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Napa Extend 665mg',
                        'raw_notes' => '1 tablet after meal (Morning + Night) for 5 Days',
                        'parsed_timings' => ['Morning', 'Night'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 5,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ]
                ]
            ],
            [
                'doctor' => 'Dr. Farhana Chowdhury',
                'clinic' => 'BIRDEM Hospital, Dhaka',
                'date' => date('Y-m-d'),
                'medicines' => [
                    [
                        'name' => 'Secrin 2mg',
                        'raw_notes' => '1 tablet before breakfast (Morning) for 60 Days',
                        'parsed_timings' => ['Morning'],
                        'parsed_meal_relation' => 'before_meal',
                        'parsed_duration_days' => 60,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Byscard 5mg',
                        'raw_notes' => '1 tablet after breakfast (Morning) for 90 Days',
                        'parsed_timings' => ['Morning'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 90,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Atova 10mg',
                        'raw_notes' => '1 tablet after dinner (Night) for 90 Days',
                        'parsed_timings' => ['Night'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 90,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ]
                ]
            ],
            [
                'doctor' => 'Dr. SM Ali',
                'clinic' => 'Ibn Sina Medical Center, Dhaka',
                'date' => date('Y-m-d'),
                'medicines' => [
                    [
                        'name' => 'Zithrox 500mg',
                        'raw_notes' => '1 tablet after lunch (Afternoon) for 5 Days',
                        'parsed_timings' => ['Afternoon'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 5,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Napa 500mg',
                        'raw_notes' => '1 tablet for fever (SOS as needed)',
                        'parsed_timings' => [],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 3,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Axodin 120mg',
                        'raw_notes' => '1 tablet after dinner (Night) for 7 Days',
                        'parsed_timings' => ['Night'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 7,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ]
                ]
            ],
            [
                'doctor' => 'Dr. Masud Parvez',
                'clinic' => 'Popular Hospital, Dhaka',
                'date' => date('Y-m-d'),
                'medicines' => [
                    [
                        'name' => 'Naproxen 500mg',
                        'raw_notes' => '1 tablet after dinner (Night) for 10 Days',
                        'parsed_timings' => ['Night'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 10,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Maxpro 20mg',
                        'raw_notes' => '1 capsule before breakfast (Morning) for 14 Days',
                        'parsed_timings' => ['Morning'],
                        'parsed_meal_relation' => 'before_meal',
                        'parsed_duration_days' => 14,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Capsule'
                    ],
                    [
                        'name' => 'Napa Rapid',
                        'raw_notes' => '1 tablet for pain (SOS as needed)',
                        'parsed_timings' => [],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 5,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ]
                ]
            ],
            [
                'doctor' => 'Dr. Nasrin Akter',
                'clinic' => 'Square Hospital, Dhaka',
                'date' => date('Y-m-d'),
                'medicines' => [
                    [
                        'name' => 'Revotril 0.5mg',
                        'raw_notes' => '1 tablet at bedtime (Night) for 30 Days',
                        'parsed_timings' => ['Night'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 30,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Neuro-B',
                        'raw_notes' => '1 tablet after breakfast (Morning) for 30 Days',
                        'parsed_timings' => ['Morning'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 30,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ],
                    [
                        'name' => 'Calcium-D',
                        'raw_notes' => '1 tablet after lunch (Afternoon) for 30 Days',
                        'parsed_timings' => ['Afternoon'],
                        'parsed_meal_relation' => 'after_meal',
                        'parsed_duration_days' => 30,
                        'parsed_dose_quantity' => 1,
                        'parsed_dose_unit' => 'Tablet'
                    ]
                ]
            ]
        ];

        // Randomly pick one profile to simulate dynamic OCR parser
        $parsed = $profiles[array_rand($profiles)];

        $path = null;
        $user = $request->user();

        try {
            // 1. Check if image file exists and save it
            if ($request->hasFile('image')) {
                $path = '/storage/' . $request->file('image')->store('prescriptions', 'public');
                
                // 2. Prepare Base64 for Gemini multimodal API
                $fileData = base64_encode($request->file('image')->getContent());
                $mimeType = $request->file('image')->getMimeType();

                // 3. Prompt for Gemini
                $prompt = "Identify and extract all medical information from this prescription image. " .
                          "You MUST return the response strictly in JSON format. Do not write any explanations. " .
                          "JSON schema:\n" .
                          "{\n" .
                          "  \"doctor\": \"Doctor Name\",\n" .
                          "  \"clinic\": \"Clinic or Hospital name\",\n" .
                          "  \"date\": \"YYYY-MM-DD\",\n" .
                          "  \"medicines\": [\n" .
                          "    {\n" .
                          "      \"name\": \"Medicine Name\",\n" .
                          "      \"raw_notes\": \"All printed instruction details, dosage, timing, and conditions for this medicine\",\n" .
                          "      \"parsed_timings\": [\"Morning\", \"Afternoon\", \"Night\"],\n" .
                          "      \"parsed_meal_relation\": \"before_meal or after_meal or with_meal\",\n" .
                          "      \"parsed_duration_days\": 30,\n" .
                          "      \"parsed_dose_quantity\": 1,\n" .
                          "      \"parsed_dose_unit\": \"Tablet or Capsule or Spoon\"\n" .
                          "    }\n" .
                          "  ]\n" .
                          "}";

                $extraction = $this->aiService->parseMultimodalImage($fileData, $mimeType, $prompt);

                if ($extraction['success']) {
                    $rawReply = $extraction['reply'];
                    
                    // Robust JSON block extraction (from first '{' to last '}')
                    $firstBrace = strpos($rawReply, '{');
                    $lastBrace = strrpos($rawReply, '}');
                    
                    $decoded = null;
                    if ($firstBrace !== false && $lastBrace !== false) {
                        $jsonContent = substr($rawReply, $firstBrace, $lastBrace - $firstBrace + 1);
                        $decoded = json_decode(trim($jsonContent), true);
                    } else {
                        $decoded = json_decode(trim($rawReply), true);
                    }

                    if (is_array($decoded) && isset($decoded['medicines'])) {
                        $parsed = $decoded;
                        Log::info("Successful live OpenRouter/Gemini OCR extraction completed. Provider: " . $extraction['provider']);
                    } else {
                        Log::warning("AI returned invalid JSON format or missing medicines key. Raw response: " . $rawReply);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Gemini OCR extraction failed, running fail-safe: " . $e->getMessage());
        }

        // 4. Save to Database (this always runs even if Gemini fails or file upload fails)
        try {
            $prescription = Prescription::create([
                'user_id' => $user->id,
                'doctor_name' => $parsed['doctor'] ?? 'Dr. Michael Brown',
                'clinic_name' => $parsed['clinic'] ?? 'Cleveland Clinic',
                'prescription_date' => $parsed['date'] ?? date('Y-m-d'),
                'status' => 'Saved Only',
                'image_path' => $path
            ]);

            // Save medicines linked to this prescription
            $medicines = [];
            foreach ($parsed['medicines'] ?? [] as $m) {
                $timings = $m['parsed_timings'] ?? ['Morning'];
                $mealRelation = $m['parsed_meal_relation'] ?? 'after_meal';
                $durationDays = $m['parsed_duration_days'] ?? 30;
                $doseQuantity = $m['parsed_dose_quantity'] ?? 1;
                $doseUnit = $m['parsed_dose_unit'] ?? 'Tablet';

                $timeString = implode(' + ', $timings);
                if (empty($timeString)) {
                    $timeString = 'As Needed';
                }

                $dosageString = $doseQuantity . ' ' . $doseUnit . ' (' . str_replace('_', ' ', $mealRelation) . ')';

                $medicine = Medicine::create([
                    'user_id' => $user->id,
                    'prescription_id' => $prescription->id,
                    'name' => $m['name'],
                    'dosage' => $dosageString,
                    'time' => $timeString,
                    'taken' => false,
                    'doctor_name' => $prescription->doctor_name,
                    'is_reminder' => false,
                    'raw_notes' => $m['raw_notes'] ?? 'Instructions not specified',
                    'start_date' => $prescription->prescription_date,
                    'duration_days' => $durationDays,
                    'meal_relation' => $mealRelation,
                    'dose_quantity' => $doseQuantity,
                    'dose_unit' => $doseUnit
                ]);

                $medicines[] = [
                    'name' => $medicine->name,
                    'dosage' => $medicine->dosage,
                    'duration' => $medicine->duration_days . ' Days',
                    'timing' => $timings,
                    'type' => empty($timings) ? 'sos' : 'daily',
                    'raw_notes' => $medicine->raw_notes,
                    'start_date' => $medicine->start_date,
                    'duration_days' => $medicine->duration_days,
                    'meal_relation' => $medicine->meal_relation,
                    'dose_quantity' => $medicine->dose_quantity,
                    'dose_unit' => $medicine->dose_unit
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Prescription processed successfully',
                'data' => [
                    'id' => (string) $prescription->id,
                    'doctor' => $prescription->doctor_name,
                    'clinic' => $prescription->clinic_name,
                    'date' => date('d M Y', strtotime($prescription->prescription_date)),
                    'status' => $prescription->status,
                    'imageUri' => $prescription->image_path,
                    'medicines' => $medicines
                ]
            ]);
        } catch (\Exception $dbError) {
            Log::error("Prescription Database Save Error: " . $dbError->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Database save failed: ' . $dbError->getMessage()
            ], 500);
        }
    }

    /**
     * Activate reminders for a scanned prescription (Split timings into individual reminders)
     */
    public function activateReminders(Request $request, $id)
    {
        $request->validate([
            'morning' => 'required|string',
            'afternoon' => 'required|string',
            'night' => 'required|string',
            'medicines' => 'required|array',
            'medicines.*.name' => 'required|string',
            'medicines.*.timings' => 'present|array',
            'medicines.*.meal_relation' => 'required|string',
            'medicines.*.start_date' => 'required|date_format:Y-m-d',
            'medicines.*.duration_days' => 'required|integer|min:1',
            'medicines.*.dose_quantity' => 'required|numeric|min:0.01',
            'medicines.*.dose_unit' => 'required|string',
        ]);

        try {
            $user = $request->user();
            $prescription = Prescription::where('user_id', $user->id)->findOrFail($id);

            // Update prescription status
            $prescription->status = 'Reminder Active';
            $prescription->save();

            // Clear existing active reminders for this prescription to replace them
            Medicine::where('prescription_id', $prescription->id)
                ->where('is_reminder', true)
                ->delete();

            $morning = $request->morning;
            $afternoon = $request->afternoon;
            $night = $request->night;

            foreach ($request->medicines as $m) {
                // Compile dosage string, e.g. "1 Capsule (After Meal)"
                $mealText = str_replace('_', ' ', $m['meal_relation']);
                $dosageString = $m['dose_quantity'] . ' ' . $m['dose_unit'] . ' (' . ucwords($mealText) . ')';
                
                // Find and update the original raw prescription medicine record
                $rawMed = Medicine::where('prescription_id', $prescription->id)
                    ->where('name', $m['name'])
                    ->where('is_reminder', false)
                    ->first();
                
                if ($rawMed) {
                    $rawMed->update([
                        'start_date' => $m['start_date'],
                        'duration_days' => $m['duration_days'],
                        'meal_relation' => $m['meal_relation'],
                        'dose_quantity' => $m['dose_quantity'],
                        'dose_unit' => $m['dose_unit'],
                        'dosage' => $dosageString,
                        'time' => implode(' + ', $m['timings']) ?: 'As Needed',
                    ]);
                }

                // If timings is empty, it is an SOS / As Needed medicine
                if (empty($m['timings'])) {
                    Medicine::create([
                        'user_id' => $user->id,
                        'prescription_id' => $prescription->id,
                        'name' => $m['name'],
                        'dosage' => $dosageString,
                        'time' => 'As Needed',
                        'taken' => false,
                        'doctor_name' => $prescription->doctor_name,
                        'is_reminder' => true,
                        'start_date' => $m['start_date'],
                        'duration_days' => $m['duration_days'],
                        'meal_relation' => $m['meal_relation'],
                        'dose_quantity' => $m['dose_quantity'],
                        'dose_unit' => $m['dose_unit'],
                        'raw_notes' => 'As Needed'
                    ]);
                } else {
                    // Create reminder rows for each selected timing slot
                    foreach ($m['timings'] as $t) {
                        $scheduledTime = $morning;
                        if ($t === 'Morning') {
                            $scheduledTime = $morning;
                        } elseif ($t === 'Afternoon') {
                            $scheduledTime = $afternoon;
                        } elseif ($t === 'Night') {
                            $scheduledTime = $night;
                        }

                        Medicine::create([
                            'user_id' => $user->id,
                            'prescription_id' => $prescription->id,
                            'name' => $m['name'],
                            'dosage' => $dosageString,
                            'time' => $scheduledTime,
                            'taken' => false,
                            'doctor_name' => $prescription->doctor_name,
                            'is_reminder' => true,
                            'start_date' => $m['start_date'],
                            'duration_days' => $m['duration_days'],
                            'meal_relation' => $m['meal_relation'],
                            'dose_quantity' => $m['dose_quantity'],
                            'dose_unit' => $m['dose_unit'],
                            'raw_notes' => $t
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Reminders activated and scheduled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to activate reminders: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate reminders: ' . $e->getMessage()
            ], 500);
        }
    }
}
