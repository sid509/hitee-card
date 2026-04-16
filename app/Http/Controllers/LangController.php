<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LangController extends Controller
{
    public function switch($lang)
    {
        if (in_array($lang, ['en', 'ne'])) {
            Session::put('locale', $lang);
        }
        return redirect()->back();
    }
}
