<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $settings = Setting::all()->pluck('value', 'key');
        return view('modules.settings.index', compact('settings'));
    }

    /**
     * Update settings.
     */
    public function update(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $data = $request->except(['_token', '_method']);

        foreach ($data as $key => $value) {
            // Only update if value is provided, allows partial updates per tab if needed
            // although usually we submit all visible fields
            Setting::set($key, $value);
        }

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }
}
