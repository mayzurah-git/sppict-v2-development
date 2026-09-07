<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Livewire\ApplicationForm;
use App\Livewire\ViewApplication;
use App\Livewire\Admin\AgencyManagement;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Admin\PositionGradeManagement;
use App\Livewire\Admin\AuditLog;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    // Permohonan Baharu
    Route::get('/application/new', ApplicationForm::class)->middleware('not-role:superadmin')->name('application.create');
    Route::view('/permohonan/baru', 'pages.permohonan-baru')->middleware('not-role:superadmin');

    // Kemaskini draf menggunakan UUID, bukan ID pangkalan data.
    Route::get('/application/edit/{uuid}', ApplicationForm::class)->middleware('not-role:superadmin')->name('application.edit');

    // Paparan rekod permohonan yang telah dihantar.
    Route::get('/application/view/{uuid}', ViewApplication::class)->middleware('not-role:superadmin')->name('application.view');

    Route::get('/admin/agencies', AgencyManagement::class)->middleware('role:superadmin')->name('admin.agencies');

    Route::get('/admin/users', UserManagement::class)->middleware('role:superadmin')->name('admin.users');

    Route::get('/admin/positions-grades', PositionGradeManagement::class)->middleware('role:superadmin')->name('admin.positions-grades');

    Route::get('/admin/audit-logs', AuditLog::class)->middleware('role:superadmin')->name('admin.audit-logs');
});

require __DIR__.'/auth.php';
