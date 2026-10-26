<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\ChangePasswordRequest;

class SettingsController extends Controller
{
    /**
     * Change the authenticated user's password (verifies the current one).
     */
    public function password(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->save();

        // Password changed — rotate the session.
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return $this->json(true, 'تم حفظ كلمة المرور الجديدة');
        }

        return $this->redirect('/settings', 'تم حفظ كلمة المرور الجديدة');
    }
}
