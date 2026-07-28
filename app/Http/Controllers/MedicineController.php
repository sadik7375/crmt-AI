<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medicine;
use App\Models\MedicineLog;
use Carbon\Carbon;

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $medicines = Medicine::where('user_id', $request->user()->id)
            ->where(function ($q) {
                $q->whereNull('prescription_id')->orWhere('is_reminder', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $todayStr = now()->format('Y-m-d');
        $yesterdayStr = now()->subDay()->format('Y-m-d');

        foreach ($medicines as $medicine) {
            if ($medicine->taken && $medicine->updated_at && $medicine->updated_at->format('Y-m-d') !== $todayStr) {
                $takenDate = $medicine->updated_at->format('Y-m-d');
                MedicineLog::updateOrCreate([
                    'user_id' => $medicine->user_id,
                    'medicine_id' => $medicine->id,
                    'log_date' => $takenDate,
                ], [
                    'name' => $medicine->name,
                    'time' => $medicine->time,
                    'status' => 'taken',
                    'taken_at' => $medicine->updated_at,
                ]);

                $medicine->taken = false;
                $medicine->save();
            }
        }

        // Auto backfill 'missed' logs for yesterday
        $activeReminders = Medicine::where('user_id', $request->user()->id)
            ->where('is_reminder', true)
            ->where(function($query) use ($yesterdayStr) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', $yesterdayStr);
            })
            ->get();

        foreach ($activeReminders as $reminder) {
            if (strtolower($reminder->time) === 'as needed') {
                continue;
            }

            MedicineLog::firstOrCreate([
                'user_id' => $reminder->user_id,
                'medicine_id' => $reminder->id,
                'log_date' => $yesterdayStr,
            ], [
                'name' => $reminder->name,
                'time' => $reminder->time,
                'status' => 'missed',
                'taken_at' => null,
            ]);
        }

        return response()->json([
            'success'   => true,
            'medicines' => $medicines,
        ]);
    }

    public function toggleTaken(Request $request, $id)
    {
        $medicine = Medicine::where('user_id', $request->user()->id)->findOrFail($id);
        $medicine->taken = !$medicine->taken;
        $medicine->save();

        $todayStr = now()->format('Y-m-d');

        if ($medicine->taken) {
            MedicineLog::updateOrCreate([
                'user_id' => $medicine->user_id,
                'medicine_id' => $medicine->id,
                'log_date' => $todayStr,
            ], [
                'name' => $medicine->name,
                'time' => $medicine->time,
                'status' => 'taken',
                'taken_at' => now(),
            ]);
        } else {
            MedicineLog::where([
                'user_id' => $medicine->user_id,
                'medicine_id' => $medicine->id,
                'log_date' => $todayStr,
            ])->delete();
        }

        return response()->json([
            'success'  => true,
            'medicine' => $medicine,
        ]);
    }

    public function history(Request $request)
    {
        $logs = MedicineLog::where('user_id', $request->user()->id)
            ->orderBy('log_date', 'desc')
            ->orderBy('time', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'history' => $logs,
        ]);
    }

    public function storeManual(Request $request)
    {
        $request->validate([
            'name'   => 'required|string',
            'dosage' => 'required|string',
            'time'   => 'required|string',
        ]);

        $medicine = new Medicine([
            'user_id'     => $request->user()->id,
            'name'        => $request->name,
            'dosage'      => $request->dosage,
            'time'        => $request->time,
            'doctor_name' => $request->doctor_name ?? 'Manual Entry',
            'taken'       => false,
        ]);

        if ($request->filled('date')) {
            try {
                $medicine->created_at = \Carbon\Carbon::parse($request->date);
            } catch (\Exception $e) {
                // Ignore invalid date format
            }
        }
        $medicine->save();

        return response()->json([
            'success'  => true,
            'medicine' => $medicine,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'   => 'required|string',
            'dosage' => 'required|string',
            'time'   => 'required|string',
        ]);

        $medicine = Medicine::where('user_id', $request->user()->id)->findOrFail($id);
        $medicine->name = $request->name;
        $medicine->dosage = $request->dosage;
        $medicine->time = $request->time;

        if ($request->filled('date')) {
            try {
                $medicine->created_at = \Carbon\Carbon::parse($request->date);
            } catch (\Exception $e) {
                // Ignore parse errors
            }
        }
        $medicine->save();

        return response()->json([
            'success'  => true,
            'medicine' => $medicine,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $medicine = Medicine::where('user_id', $request->user()->id)->findOrFail($id);
        $medicine->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function updateSlotTimes(Request $request)
    {
        $request->validate([
            'morning'   => 'required|string',
            'afternoon' => 'required|string',
            'night'     => 'required|string',
        ]);

        $userId = $request->user()->id;

        Medicine::where('user_id', $userId)
            ->where('is_reminder', true)
            ->where('raw_notes', 'Morning')
            ->update(['time' => $request->morning]);

        Medicine::where('user_id', $userId)
            ->where('is_reminder', true)
            ->where('raw_notes', 'Afternoon')
            ->update(['time' => $request->afternoon]);

        Medicine::where('user_id', $userId)
            ->where('is_reminder', true)
            ->where('raw_notes', 'Night')
            ->update(['time' => $request->night]);

        return response()->json([
            'success' => true,
            'message' => 'Slot times updated successfully',
        ]);
    }
}
