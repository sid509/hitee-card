<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ThemeController extends Controller
{
    public function toggle()
    {
        $currentTheme = Session::get('theme', 'system');
        
        if ($currentTheme === 'system') {
            $newTheme = 'light';
        } elseif ($currentTheme === 'light') {
            $newTheme = 'dark';
        } else {
            $newTheme = 'system';
        }

        Session::put('theme', $newTheme);
        return redirect()->back();
    }
}
