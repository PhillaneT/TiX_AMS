<?php

use App\Http\Controllers\ActiveContextController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\QualificationModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->get('/api/saqa-lookup', [QualificationModuleController::class, 'lookupJson'])
    ->name('api.saqa-lookup');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/context', [ActiveContextController::class, 'update'])->name('context.update');

    Route::resource('qualifications', QualificationController::class);

    Route::prefix('qualifications/{qualification}')->name('qualifications.')->group(function () {

        // Qualification modules — SAQA fetch + assignment mapping
        Route::get('modules', [QualificationModuleController::class, 'index'])
            ->name('modules.index');
        Route::post('modules/fetch-saqa', [QualificationModuleController::class, 'fetchSaqa'])
            ->name('modules.fetch-saqa');
        Route::post('modules/save-mapping', [QualificationModuleController::class, 'saveMapping'])
            ->name('modules.save-mapping');
        Route::post('modules/add', [QualificationModuleController::class, 'addModule'])
            ->name('modules.add');
        Route::delete('modules/{module}', [QualificationModuleController::class, 'destroyModule'])
            ->name('modules.destroy');

        // Assignments
        Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
        Route::get('assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
        Route::put('assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::get('assignments/{assignment}/memo', [AssignmentController::class, 'downloadMemo'])->name('assignments.memo');

        // Cohorts
        Route::resource('cohorts', CohortController::class)
            ->names([
                'index'   => 'cohorts.index',
                'create'  => 'cohorts.create',
                'store'   => 'cohorts.store',
                'show'    => 'cohorts.show',
                'edit'    => 'cohorts.edit',
                'update'  => 'cohorts.update',
                'destroy' => 'cohorts.destroy',
            ]);

        Route::prefix('cohorts/{cohort}')->name('cohorts.')->group(function () {
            Route::get('learners', [LearnerController::class, 'index'])->name('learners.index');
            Route::get('learners/import', [LearnerController::class, 'importForm'])->name('learners.import');
            Route::post('learners/import', [LearnerController::class, 'import'])->name('learners.import.store');
            Route::delete('learners/{learner}', [LearnerController::class, 'destroy'])->name('learners.destroy');
            Route::get('learners/{learner}/poe', [LearnerController::class, 'poe'])->name('learners.poe');
        });
    });

    Route::get('learners/template', [LearnerController::class, 'downloadTemplate'])->name('learners.template');
});
