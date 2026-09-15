<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MissingActivePositionController extends Controller
{
    public function __construct(private CurrentUserContext $currentUserContext) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $this->currentUserContext->user($request);

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($this->currentUserContext->hasSelectablePositions($user)) {
            return redirect()
                ->route('positions.index')
                ->with('status', 'Posisi aktif sudah tersedia. Silakan pilih konteks kerja.');
        }

        $this->currentUserContext->forgetActivePosition($request);

        return view('auth.no-active-position', [
            'user' => $user,
            'activeYear' => $this->currentUserContext->activeYear($request)
                ?? $user->tahun_aktif
                ?? (int) now()->year,
        ]);
    }
}
