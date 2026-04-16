<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ThemeController extends Controller
{
    public function toggle()
    {
        $currentTheme = Session::get('theme', 'light');
        $newTheme = $currentTheme === 'light' ? 'dark' : 'light';
        Session::put('theme', $newTheme);
        return redirect()->back();
    }
}
