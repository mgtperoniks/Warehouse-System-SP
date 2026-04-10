<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OpnameController extends Controller
{
    public function index()
    {
        return view('opname.index');
    }
}
