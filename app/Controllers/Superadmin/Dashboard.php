<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        return view('obras/index', $this->datosDashboardObras());
    }
}
