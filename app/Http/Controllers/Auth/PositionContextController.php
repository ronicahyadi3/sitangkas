<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SelectUserPositionContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StorePositionContextRequest;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class PositionContextController extends Controller
{
    public function __construct(private CurrentUserContext $currentUserContext) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->currentUserContext->user($request);

        $userPositions = $user instanceof User
            ? $this->currentUserContext->selectablePositions($user)
            : collect();

        if ($user instanceof User && $userPositions->isEmpty() && Route::has('login.no_active_position')) {
            return redirect()->route('login.no_active_position');
        }

        $activeUserPositionId = $this->currentUserContext->activeUserPositionId($request);
        $activePosition = $userPositions->first(
            fn (UserPosition $userPosition): bool => (string) $userPosition->getKey() === (string) $activeUserPositionId
        );

        return view('users.positions-switch', [
            'user' => $user,
            'positions' => $userPositions,
            'activePosition' => $activePosition,
            'activeUserPositionId' => $activeUserPositionId,
            'activeYear' => $this->currentUserContext->activeYear($request),
            'contextSnapshot' => $this->currentUserContext->snapshot($request),
        ]);
    }

    public function legacy(): RedirectResponse
    {
        return redirect()->route('positions.index');
    }

    public function store(
        StorePositionContextRequest $request,
        SelectUserPositionContext $selectUserPositionContext
    ): RedirectResponse {
        $user = $this->currentUserContext->user($request);

        abort_unless($user instanceof User, 403);

        $result = $selectUserPositionContext->handle(
            $request,
            $user,
            $request->selectedUserPosition()
        );

        $message = $result['position_changed']
            ? 'Posisi kerja berhasil diganti. Lanjutkan verifikasi keamanan.'
            : 'Posisi kerja berhasil dipilih. Lanjutkan verifikasi keamanan.';

        return redirect()
            ->route('login.mfa')
            ->with('status', $message);
    }
}
