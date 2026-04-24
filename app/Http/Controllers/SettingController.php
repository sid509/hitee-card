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

        $data = $request->validate([
            'support_email'           => 'required|email',
            'support_phone'           => 'required|string',
            'terms_url'               => 'required|url',
            'policy_url'              => 'required|url',
            'negative_allowed_point'  => 'required|numeric|min:0',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->back()->with('success', 'Global settings updated successfully.');
    }
}
