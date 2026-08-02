<?php

namespace App\Http\Controllers;

use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private CurrentUserContext $currentUserContext) {}

    public function __invoke(Request $request): View
    {
        return view('dashboard.index', $this->currentUserContext->viewData($request));
    }
}
