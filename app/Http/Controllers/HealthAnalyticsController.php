<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medicine;
use App\Models\LabReport;

class HealthAnalyticsController extends Controller
{
    public function getAnalytics(Request $request)
    {
        $userId = $request->user()->id;

        // 1. Calculate Medicine Adherence Rate
        $totalMeds = Medicine::where('user_id', $userId)
            ->where('is_reminder', true)
            ->count();
            
        $takenMeds = Medicine::where('user_id', $userId)
            ->where('is_reminder', true)
            ->where('taken', true)
            ->count();
            
        $adherence = $totalMeds > 0 ? round(($takenMeds / $totalMeds) * 100) : 100;

        // 2. Health Score Calculation
        $healthScore = min(100, max(50, 70 + ($adherence * 0.3)));

        // 3. Danger Zone Alerts (abnormal lab parameters)
        $reports = LabReport::where('user_id', $userId)->get();
        $dangerAlerts = [];
        $totalNormalcyParams = 0;
        $totalAbnormalParams = 0;

        foreach ($reports as $r) {
            $params = is_array($r->parameters) ? $r->parameters : json_decode($r->parameters, true);
            if (is_array($params)) {
                foreach ($params as $p) {
                    $flag = $p['flag'] ?? 'Normal';
                    if (in_array(strtolower($flag), ['high', 'low', 'critical'])) {
                        $totalAbnormalParams++;
                        $dangerAlerts[] = [
                            'id' => 'alert_' . count($dangerAlerts),
                            'title' => ($p['label'] ?? 'Parameter') . ' is ' . $flag,
                            'value' => $p['value'] ?? 'N/A',
                            'ref' => 'Normal: ' . ($p['refRange'] ?? 'N/A'),
                            'severity' => strtoupper($flag),
                            'date' => $r->report_date ? $r->report_date->format('d M Y') : 'N/A',
                            'source' => $r->name ?? 'Lab Report',
                            'recommendation' => 'Please consult a doctor about this abnormal value.'
                        ];
                    } else {
                        $totalNormalcyParams++;
                    }
                }
            }
        }

        $totalParams = $totalNormalcyParams + $totalAbnormalParams;
        $labNormalcy = $totalParams > 0 ? round(($totalNormalcyParams / $totalParams) * 100) : 100;

        // 4. Missed Medicines
        $missedMedicines = Medicine::where('user_id', $userId)
            ->where('is_reminder', true)
            ->where('taken', false)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'time' => $m->time,
                    'reason' => 'Dose Skipped'
                ];
            });

        return response()->json([
            'success'        => true,
            'health_score'   => $healthScore,
            'adherence_rate' => $adherence . '%',
            'lab_normalcy'   => $labNormalcy . '%',
            'danger_alerts'  => $dangerAlerts,
            'missed_doses'   => $missedMedicines,
            'taken_count'    => $takenMeds,
            'missed_count'   => $totalMeds - $takenMeds,
        ]);
    }
}
