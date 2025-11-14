<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Contacts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ContactAuthController extends Controller
{
    /**
     * Show the contact login form
     */
    public function showLogin()
    {
        return Inertia::render('Contact/Login');
    }

    /**
     * Handle contact login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $contact = Contacts::where('email', $request->email)
            ->where('can_login', true)
            ->first();

        if (!$contact || !Hash::check($request->password, $contact->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Auth::guard('contact')->login($contact, $request->boolean('remember'));

        $request->session()->regenerate();

        // Redirect to password change if required
        if ($contact->password_change_required) {
            return redirect()->route('portal.password.change');
        }

        return redirect()->intended(route('portal.dashboard'));
    }

    /**
     * Handle contact logout
     */
    public function logout(Request $request)
    {
        Auth::guard('contact')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    /**
     * Show password reset request form
     */
    public function showForgotPassword()
    {
        return Inertia::render('Contact/ForgotPassword');
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $contact = Contacts::where('email', $request->email)
            ->where('can_login', true)
            ->first();

        if ($contact) {
            // Use the contacts password broker
            $broker = \Password::broker('contacts');
            $status = $broker->sendResetLink(['email' => $request->email]);
        }

        // Always return success message to prevent email enumeration
        return redirect()->route('portal.login')->with('status', 'If that email address exists in our system, we have sent a password reset link. Please check your email.');
    }

    /**
     * Show password reset form
     */
    public function showResetPassword(Request $request, $token)
    {
        return Inertia::render('Contact/ResetPassword', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $broker = \Password::broker('contacts');
        
        $status = $broker->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($contact, $password) {
                $contact->forceFill([
                    'password' => Hash::make($password),
                    'password_change_required' => false,
                ])->save();
            }
        );

        return $status === \Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', 'Your password has been reset successfully!')
            : back()->withErrors(['email' => [__($status)]]);
    }

    /**
     * Show password change form (for temporary password)
     */
    public function showChangePassword()
    {
        $contact = Auth::guard('contact')->user();

        if (!$contact || !$contact->password_change_required) {
            return redirect()->route('portal.dashboard');
        }

        return Inertia::render('Contact/ChangePassword');
    }

    /**
     * Change password (for temporary password)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        $contact = Auth::guard('contact')->user();

        if (!$contact) {
            return redirect()->route('portal.login');
        }

        // Verify current password
        if (!Hash::check($request->current_password, $contact->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password and clear the flag
        $contact->update([
            'password' => Hash::make($request->password),
            'password_change_required' => false,
        ]);

        return redirect()->route('portal.dashboard')->with('status', 'Your password has been changed successfully!');
    }
}
