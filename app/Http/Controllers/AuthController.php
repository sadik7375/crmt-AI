<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|string|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $verificationToken = substr(bin2hex(random_bytes(3)), 0, 6);
            Otp::updateOrCreate(
                ['email' => $request->email],
                [
                    'otp_code'   => $verificationToken,
                    'expires_at' => Carbon::now()->addDays(3),
                ]
            );

            $link = request()->getSchemeAndHttpHost() . "/api/auth/verify-email-link?email=" . urlencode($user->email) . "&token=" . $verificationToken;

            try {
                $htmlContent = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
                        <h2 style='color: #1e293b; margin-bottom: 16px;'>Welcome to CareMate AI, {$user->name}!</h2>
                        <p style='color: #475569; font-size: 16px; line-height: 24px; margin-bottom: 24px;'>Please click the button below to verify your email address and activate your account:</p>
                        <a href='{$link}' style='display: inline-block; padding: 12px 24px; background-color: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px;'>Verify Email Address</a>
                        <p style='color: #64748b; font-size: 13px; margin-top: 28px; line-height: 20px;'>If the button doesn't work, copy and paste this link into your browser:<br>
                        <a href='{$link}' style='color: #4f46e5; word-break: break-all;'>{$link}</a></p>
                        <hr style='border: 0; border-top: 1px solid #f1f5f9; margin: 24px 0;'>
                        <p style='color: #94a3b8; font-size: 12px;'>This link is valid for 3 days. If you did not sign up for an account, please ignore this email.</p>
                    </div>
                ";
                Mail::html($htmlContent, function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Verify Your Email - CareMate AI');
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Registration mail error: " . $e->getMessage());
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully and verification email sent',
                'token'   => $token,
                'user'    => $user,
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Registration DB error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed due to a database or server error. Please try again.',
            ], 500);
        }
    }

    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password credentials',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    // Forgot Password — Send OTP
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User with this email not found'], 44);
        }

        $otpCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        Otp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp_code'   => $otpCode,
                'expires_at' => Carbon::now()->addMinutes(10),
            ]
        );

        try {
            Mail::raw("You requested a password reset on CareMate AI.\n\nYour reset OTP code is: {$otpCode}. It is valid for 10 minutes.", function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Password Reset OTP - CareMate AI');
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("ForgotPassword mail error: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification OTP sent to email',
        ]);
    }

    // Verify OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        $record = Otp::where('email', $request->email)
            ->where('otp_code', $request->otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP code'], 400);
        }

        return response()->json(['success' => true, 'message' => 'OTP verified successfully']);
    }

    // Verify Email via Link (GET request)
    public function verifyEmailLink(Request $request)
    {
        $email = $request->query('email');
        $token = $request->query('token');

        $record = Otp::where('email', $email)
            ->where('otp_code', $token)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$record) {
            return response('<!DOCTYPE html>
<html>
<head>
  <title>Invalid Link - CareMate AI</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #FFF5F5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
    .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.1), 0 8px 10px -6px rgba(239, 68, 68, 0.1); text-align: center; max-width: 400px; border-top: 5px solid #EF4444; }
    h1 { color: #EF4444; margin-bottom: 12px; font-size: 26px; }
    p { color: #64748B; font-size: 15px; line-height: 24px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Invalid or Expired Link</h1>
    <p>This verification link is invalid or has expired. Please request a new verification email from the app.</p>
  </div>
</body>
</html>');
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->email_verified_at = Carbon::now();
            $user->save();
        }

        Otp::where('email', $email)->delete();

        return response('<!DOCTYPE html>
<html>
<head>
  <title>Email Verified - CareMate AI</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #F8F9FF; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
    .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.1), 0 8px 10px -6px rgba(79, 70, 229, 0.1); text-align: center; max-width: 400px; border-top: 5px solid #4F46E5; }
    h1 { color: #4F46E5; margin-bottom: 12px; font-size: 26px; }
    p { color: #64748B; font-size: 15px; line-height: 24px; }
    .badge { width: 64px; height: 64px; border-radius: 32px; background: #EEF2FF; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; }
    .badge svg { width: 32px; height: 32px; color: #4F46E5; }
  </style>
</head>
<body>
  <div class="card">
    <div class="badge">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
      </svg>
    </div>
    <h1>Email Verified!</h1>
    <p>Your email address has been successfully verified. You can now return to the CareMate AI app and press "I Have Verified" or refresh to continue.</p>
  </div>
</body>
</html>');
    }

    // Resend Email Verification Link
    public function resendVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['success' => false, 'message' => 'Email is already verified'], 400);
        }

        $token = substr(bin2hex(random_bytes(3)), 0, 6);
        Otp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp_code'   => $token,
                'expires_at' => Carbon::now()->addDays(3),
            ]
        );

        $link = request()->getSchemeAndHttpHost() . "/api/auth/verify-email-link?email=" . urlencode($user->email) . "&token=" . $token;

        try {
            $htmlContent = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
                    <h2 style='color: #1e293b; margin-bottom: 16px;'>Welcome to CareMate AI, {$user->name}!</h2>
                    <p style='color: #475569; font-size: 16px; line-height: 24px; margin-bottom: 24px;'>Please click the button below to verify your email address and activate your account:</p>
                    <a href='{$link}' style='display: inline-block; padding: 12px 24px; background-color: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px;'>Verify Email Address</a>
                    <p style='color: #64748b; font-size: 13px; margin-top: 28px; line-height: 20px;'>If the button doesn't work, copy and paste this link into your browser:<br>
                    <a href='{$link}' style='color: #4f46e5; word-break: break-all;'>{$link}</a></p>
                    <hr style='border: 0; border-top: 1px solid #f1f5f9; margin: 24px 0;'>
                    <p style='color: #94a3b8; font-size: 12px;'>This link is valid for 3 days. If you did not sign up for an account, please ignore this email.</p>
                </div>
            ";
            Mail::html($htmlContent, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Verify Your Email - CareMate AI');
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Resend verification link mail error: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification email link sent successfully',
        ]);
    }

    // Reset Password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'       => 'required|email',
            'newPassword' => 'required|string|min:8',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->newPassword);
        $user->save();

        Otp::where('email', $request->email)->delete();

        return response()->json(['success' => true, 'message' => 'Password reset successfully']);
    }

    // Google Sign-In Login/Register
    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $client = new \Google\Client(['client_id' => '676610858328-g23aabte530pq49t9a7uqnmrbkqgo3fv.apps.googleusercontent.com']);
            $payload = $client->verifyIdToken($request->id_token);

            if (!$payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google Token signature'
                ], 400);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? 'Google User';

            // Find user by google_id or email
            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if (!$user) {
                // Register a new user
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => null, // Password can be null for OAuth users
                ]);
            } else {
                // If user exists but google_id is not set, set it
                if (empty($user->google_id)) {
                    $user->google_id = $googleId;
                    $user->save();
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Google login successful',
                'token' => $token,
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google verification failed: ' . $e->getMessage()
            ], 400);
        }
    }
}
