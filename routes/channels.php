<?php

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\MfaSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.online-users', function (User $user): array|false {
    $request = request();
    $currentUserContext = app(CurrentUserContext::class);
    $realActiveUserPosition = $currentUserContext->realActivePosition($request);

    if (
        ! $realActiveUserPosition instanceof UserPosition
        || ! $currentUserContext->isAdminSuperPosition($realActiveUserPosition)
        || ! app(MfaSession::class)->isVerifiedFor($request, $user, $realActiveUserPosition)
    ) {
        return false;
    }

    return [
        'id' => $user->getKey(),
        'nama' => $user->nama,
        'status' => 'monitoring',
    ];
});
