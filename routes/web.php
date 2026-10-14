<?php

use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Assets\AssetStatusController;
use App\Http\Controllers\Assets\LibraryController;
use App\Http\Controllers\Brands\BrandAccessController;
use App\Http\Controllers\Brands\BrandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guidelines\GuidelinePageController;
use App\Http\Controllers\Workspaces\AcceptInvitationController;
use App\Http\Controllers\Workspaces\InvitationController;
use App\Http\Controllers\Workspaces\MemberController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceSettingsController;
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

            Route::patch('settings/approval', [WorkspaceSettingsController::class, 'updateApproval'])->name('settings.approval');

            Route::get('library', [LibraryController::class, 'index'])->name('library');

            Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
            Route::whereUuid(['brand', 'brandRole'])->group(function () {
                Route::get('brands/{brand}', [LibraryController::class, 'brand'])->name('brands.show');
                Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
                Route::get('brands/{brand}/access', [BrandAccessController::class, 'index'])->name('brands.access.index');
                Route::post('brands/{brand}/access', [BrandAccessController::class, 'store'])->name('brands.access.store');
                Route::delete('brands/{brand}/access/{brandRole}', [BrandAccessController::class, 'destroy'])->name('brands.access.destroy');
                Route::post('brands/{brand}/assets', [AssetController::class, 'store'])->middleware('throttle:120,1')->name('brands.assets.store');
                Route::get('brands/{brand}/guidelines', [GuidelinePageController::class, 'brand'])->name('brands.guidelines.index');
                Route::post('brands/{brand}/guidelines', [GuidelinePageController::class, 'store'])->name('brands.guidelines.store');
            });

            Route::whereUuid('asset')->prefix('assets/{asset}')->name('assets.')->group(function () {
                Route::get('/', [AssetController::class, 'show'])->name('show');
                Route::patch('/', [AssetController::class, 'update'])->name('update');
                Route::delete('/', [AssetController::class, 'destroy'])->name('destroy');
                Route::get('download', [AssetController::class, 'download'])->middleware('throttle:120,1')->name('download');
                Route::get('preview', [AssetController::class, 'preview'])->name('preview');
                Route::get('font', [AssetController::class, 'font'])->name('font');
                Route::post('approve', [AssetStatusController::class, 'approve'])->name('approve');
                Route::post('archive', [AssetStatusController::class, 'archive'])->name('archive');
                Route::post('restore', [AssetStatusController::class, 'restore'])->name('restore');
            });

            Route::get('guidelines', [GuidelinePageController::class, 'index'])->name('guidelines.index');
            Route::whereUuid('page')->prefix('guidelines/{page}')->name('guidelines.')->group(function () {
                Route::get('/', [GuidelinePageController::class, 'show'])->name('show');
                Route::get('edit', [GuidelinePageController::class, 'edit'])->name('edit');
                Route::put('/', [GuidelinePageController::class, 'update'])->name('update');
                Route::delete('/', [GuidelinePageController::class, 'destroy'])->name('destroy');
                Route::post('publish', [GuidelinePageController::class, 'publish'])->name('publish');
                Route::post('move', [GuidelinePageController::class, 'move'])->name('move');
            });
        });
});

Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('invitations.show');
Route::post('invitations/{token}', [AcceptInvitationController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('invitations.accept');

require __DIR__.'/settings.php';
