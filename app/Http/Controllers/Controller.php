<?php

namespace App\Http\Controllers;

use App\Support\ItqanData;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Render an authenticated page with the frontend data payload
     * (window.ITQAN_DATA) injected through partials.itqan-data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function page(string $view, array $data = [], bool $withFullUsers = false)
    {
        $user = auth()->user();

        return view($view, $data + [
            'itqan' => $user ? ItqanData::for($user, $withFullUsers) : null,
        ]);
    }

    /**
     * The consistent JSON contract: {success, message, data, errors}.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function json(bool $success, string $message, mixed $data = null, array $errors = [], int $status = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Redirect fallback for non-JSON (native form) submissions.
     */
    protected function redirect(string $to, string $message)
    {
        return redirect($to)->with('status', $message);
    }
}
