<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\ApplicationDocumentController;
use App\Livewire\ApplicationForm;
use App\Livewire\ViewApplication;
use App\Livewire\Admin\AgencyManagement;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\ServiceSchemeManagement;
use App\Livewire\Admin\GradeManagement;
use App\Livewire\Admin\PositionManagement;
use App\Http\Controllers\MeetingMinutesController;
use App\Livewire\Meetings\MeetingForm;
use App\Livewire\Meetings\MeetingList;
use App\Livewire\Meetings\MeetingNameManagement;
use App\Livewire\Meetings\MeetingNumberManagement;
use App\Livewire\Meetings\MeetingYearManagement;
use App\Livewire\MasterData\ApplicationCategoryManagement;
use App\Livewire\MasterData\ProjectCategoryManagement;
use App\Livewire\MasterData\ProcurementTypeManagement;
use App\Livewire\MasterData\ProcurementMethodManagement;

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

    // Dokumen sokongan (storan private; kebenaran disemak melalui ApplicationPolicy@view).
    Route::get('/application/{applicationUuid}/documents/{documentUuid}', ApplicationDocumentController::class)->name('application.document');

    Route::get('/admin/agencies', AgencyManagement::class)->middleware('role:pentadbir_urus_setia')->name('admin.agencies');

    // Pengurusan Mesyuarat: Urus Setia sahaja (superadmin tidak dibenarkan walaupun memegang semua permission).
    Route::middleware('role:pentadbir_urus_setia')->prefix('urus-setia/mesyuarat')->name('meetings.')->group(function () {
        Route::get('/', MeetingList::class)->name('index');
        Route::get('/baru', MeetingForm::class)->name('create');
        Route::get('/{uuid}/kemaskini', MeetingForm::class)->name('edit');

        Route::get('/selenggara/nama', MeetingNameManagement::class)->name('names');
        Route::get('/selenggara/bilangan', MeetingNumberManagement::class)->name('numbers');
        Route::get('/selenggara/tahun', MeetingYearManagement::class)->name('years');
    });

    // Master Data Permohonan Projek: Urus Setia sahaja.
    Route::middleware('role:pentadbir_urus_setia')->prefix('urus-setia/master-data')->name('master-data.')->group(function () {
        Route::get('/kategori-permohonan', ApplicationCategoryManagement::class)->name('application-categories');
        Route::get('/kategori-projek', ProjectCategoryManagement::class)->name('project-categories');
        Route::get('/jenis-perolehan', ProcurementTypeManagement::class)->name('procurement-types');
        Route::get('/kaedah-perolehan', ProcurementMethodManagement::class)->name('procurement-methods');
    });

    // Minit Mesyuarat Terdahulu: dibuka kepada ahli mesyuarat (pautan dalam Makluman Emel); superadmin tidak dibenarkan.
    Route::get('/mesyuarat/{uuid}/minit', MeetingMinutesController::class)
        ->middleware('role:' . implode('|', \App\Models\Meeting::MEMBER_ROLES))
        ->name('meetings.minutes');

    Route::get('/admin/service-schemes', ServiceSchemeManagement::class)->middleware('role:superadmin')->name('admin.service-schemes');

    Route::get('/admin/grades', GradeManagement::class)->middleware('role:superadmin')->name('admin.grades');

    Route::get('/admin/positions', PositionManagement::class)->middleware('role:superadmin')->name('admin.positions');

    Route::get('/admin/users', UserManagement::class)->middleware('role:superadmin')->name('admin.users');

    Route::redirect('/admin/positions-grades', '/admin/positions')->middleware('role:superadmin')->name('admin.positions-grades');

    Route::get('/admin/audit-logs', AuditLog::class)->middleware('role:superadmin')->name('admin.audit-logs');
});

require __DIR__.'/auth.php';
