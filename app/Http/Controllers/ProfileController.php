<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        return response()->json([
            'success' => true,
            'user'    => $request->user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'               => 'sometimes|string|max:100',
            'phone'              => 'sometimes|nullable|string',
            'emerg_name'         => 'sometimes|nullable|string',
            'emerg_phone'        => 'sometimes|nullable|string',
            'dob'                => 'sometimes|nullable|string',
            'gender'             => 'sometimes|string|in:Male,Female,Other',
            'blood_type'         => 'sometimes|nullable|string|max:5',
            'height'             => 'sometimes|nullable|string',
            'weight'             => 'sometimes|nullable|string',
            'allergies'          => 'sometimes|nullable|string',
            'medical_conditions' => 'sometimes|nullable|string',
            'conditions'         => 'sometimes|nullable|string',
        ]);

        $data = $request->only([
            'name', 'phone', 'emerg_name', 'emerg_phone', 'dob', 'gender', 'blood_type', 'height', 'weight', 'allergies'
        ]);

        if ($request->has('medical_conditions')) {
            $data['medical_conditions'] = $request->input('medical_conditions');
        } elseif ($request->has('conditions')) {
            $data['medical_conditions'] = $request->input('conditions');
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['success' => true, 'message' => 'Password updated successfully']);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['success' => true, 'message' => 'Account deleted successfully']);
    }
}
