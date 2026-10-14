<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Workspaces\AcceptInvitationController;
use App\Http\Controllers\Workspaces\InvitationController;
use App\Http\Controllers\Workspaces\MemberController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Internal reference for reviewing colours, type and components on preview links.
Route::inertia('design-system', 'design-system')->name('design-system');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    // Everything a company owns lives under its workspace's address. The
    // workspace middleware 404s anyone who is not a current member.
    Route::prefix('w/{workspace}')
        ->middleware('workspace')
        ->name('workspaces.')
        ->group(function () {
            Route::get('/', [WorkspaceController::class, 'show'])->name('show');

            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::patch('members/{membership}', [MemberController::class, 'update'])->whereUuid('membership')->name('members.update');
            Route::delete('members/{membership}', [MemberController::class, 'destroy'])->whereUuid('membership')->name('members.destroy');

            Route::post('invitations', [InvitationController::class, 'store'])->middleware('throttle:20,1')->name('invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->whereUuid('invitation')->name('invitations.destroy');
        });
});

Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('invitations.show');
Route::post('invitations/{token}', [AcceptInvitationController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('invitations.accept');

require __DIR__.'/settings.php';
