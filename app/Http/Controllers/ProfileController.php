<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Support\ItqanData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        return $this->page('pages.profile');
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        // Full Name is no longer part of the profile form: only overwrite the
        // stored name when an explicit (valid) value is submitted.
        if (array_key_exists('name', $data) && $data['name'] !== null && $data['name'] !== '') {
            $user->name = $data['name'];
        }
        $user->email = $data['email'];
        $user->phone = $data['phone'];
        $user->save();

        if ($request->expectsJson()) {
            return $this->json(true, __('تم حفظ بياناتك'), [
                'user' => ItqanData::currentUser($user),
            ]);
        }

        return $this->redirect('/profile', __('تم حفظ بياناتك'));
    }

    /**
     * Upload or replace the authenticated user's own photo. The endpoint has
     * no user id — nobody can touch another user's photo.
     *
     * The image's real bytes are stored in MySQL (users.avatar_data) along
     * with its MIME type. A copy is still written to the private disk so the
     * pre-existing avatar_path contract (and any legacy readers) keeps working;
     * no previously stored file is deleted here.
     */
    public function avatar(UpdateAvatarRequest $request)
    {
        $user = $request->user();
        $file = $request->file('avatar');

        // Real bytes + the MIME type validated by the request rules.
        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = match (strtolower($file->getClientOriginalExtension())) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        // Keep a private-disk copy under a server-chosen name (client names
        // are never trusted). Overwrites the caller's own file only.
        $path = $file->storeAs('avatars', $user->id.'.'.$ext, 'local');

        $user->avatar_data = $bytes;
        $user->avatar_mime = $mime;
        $user->avatar_path = $path;
        $user->save();

        return $this->json(true, __('تم تحديث الصورة'), [
            'url' => $user->avatar_url,
        ]);
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        // Drop the DB bytes; the default avatar is served from now on. Any
        // legacy file on disk is intentionally left in place (never deleted
        // before the DB-backed flow is confirmed end-to-end).
        $user->avatar_data = null;
        $user->avatar_mime = null;
        $user->avatar_path = null;
        $user->save();

        return $this->json(true, __('تمت إزالة الصورة'), [
            'url' => asset('images/avatar.svg'),
        ]);
    }

    /**
     * Serve the authenticated user's own photo. Source priority: the MySQL
     * bytes → a legacy private-disk file → the shared default avatar. No user
     * id in the URL → no cross-user access is possible.
     */
    public function serveAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_data !== null && $user->avatar_data !== '') {
            return response($user->avatar_data, 200, [
                'Content-Type' => $user->avatar_mime ?: 'image/png',
                'Content-Length' => (string) strlen((string) $user->avatar_data),
                'Content-Disposition' => 'inline; filename="avatar"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        // Legacy fallback: an avatar stored only as a private-disk file.
        if ($user->avatar_path && Storage::disk('local')->exists($user->avatar_path)) {
            return response()->file(Storage::disk('local')->path($user->avatar_path), [
                'Content-Type' => 'image/'.$this->avatarMime($user->avatar_path),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        return response()->file(public_path('images/avatar.svg'), [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Persist the authenticated user's interface language preference.
     */
    public function locale(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', SetLocale::SUPPORTED)],
        ]);

        $user = $request->user();
        $user->locale = $data['locale'];
        $user->save();

        $request->session()->put('locale', $data['locale']);

        if ($request->expectsJson()) {
            return $this->json(true, __('تم حفظ اللغة'), [
                'locale' => $user->locale,
                'user' => ItqanData::currentUser($user),
            ]);
        }

        return $this->redirect('/profile', __('تم حفظ اللغة'));
    }

    private function avatarMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'jpeg',
            'webp' => 'webp',
            default => 'png',
        };
    }
}
