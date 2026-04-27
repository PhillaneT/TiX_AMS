<?php

use App\Models\LmsConnection;
use App\Http\Controllers\ActiveContextController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LmsConnectionController;
use App\Http\Controllers\LmsSyncController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\QualificationModuleController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::bind('integration', fn ($id) => LmsConnection::findOrFail($id));

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

        // Questions (nested under assignments)
        Route::get('assignments/{assignment}/questions/create', [QuestionController::class, 'create'])->name('assignments.questions.create');
        Route::post('assignments/{assignment}/questions', [QuestionController::class, 'store'])->name('assignments.questions.store');
        Route::get('assignments/{assignment}/questions/{question}/edit', [QuestionController::class, 'edit'])->name('assignments.questions.edit');
        Route::put('assignments/{assignment}/questions/{question}', [QuestionController::class, 'update'])->name('assignments.questions.update');
        Route::delete('assignments/{assignment}/questions/{question}', [QuestionController::class, 'destroy'])->name('assignments.questions.destroy');
        Route::post('assignments/{assignment}/questions/reorder', [QuestionController::class, 'reorder'])->name('assignments.questions.reorder');

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

            // Submissions (per learner)
            Route::prefix('learners/{learner}')->name('learners.')->group(function () {
                Route::post('submissions', [SubmissionController::class, 'store'])
                    ->name('submissions.store');
                Route::get('submissions/{submission}', [SubmissionController::class, 'show'])
                    ->name('submissions.show');
                Route::post('submissions/{submission}/mark', [SubmissionController::class, 'mark'])
                    ->name('submissions.mark');
                Route::post('submissions/{submission}/signoff', [SubmissionController::class, 'signOff'])
                    ->name('submissions.signoff');
                Route::post('submissions/{submission}/reopen', [SubmissionController::class, 'reopen'])
                    ->name('submissions.reopen');
                Route::delete('submissions/{submission}', [SubmissionController::class, 'destroy'])
                    ->name('submissions.destroy');
            });
        });
    });

    Route::get('learners/template', [LearnerController::class, 'downloadTemplate'])->name('learners.template');

    // LMS Integrations
    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/',             [LmsConnectionController::class, 'index'])->name('index');
        Route::get('/create',       [LmsConnectionController::class, 'create'])->name('create');
        Route::post('/',            [LmsConnectionController::class, 'store'])->name('store');
        Route::get('/{integration}/edit',   [LmsConnectionController::class, 'edit'])->name('edit');
        Route::put('/{integration}',        [LmsConnectionController::class, 'update'])->name('update');
        Route::delete('/{integration}',     [LmsConnectionController::class, 'destroy'])->name('destroy');
        Route::post('/{integration}/test',  [LmsConnectionController::class, 'test'])->name('test');

        Route::post('/{integration}/sync',              [LmsSyncController::class, 'sync'])->name('sync');
        Route::post('/{integration}/sync-submissions',  [LmsSyncController::class, 'syncSubmissions'])->name('sync-submissions');
        Route::post('/{integration}/push/{submission}', [LmsSyncController::class, 'push'])->name('push');
        Route::post('/{integration}/fetch-courses',     [LmsSyncController::class, 'fetchCourses'])->name('fetch-courses');
    });
});
