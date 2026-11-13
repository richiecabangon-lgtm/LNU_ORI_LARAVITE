<?php

use App\Http\Controllers\Admin\AchievementTierController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\CertificationSettingController;
use App\Http\Controllers\Admin\FpesArchiveController;
use App\Http\Controllers\Admin\NewsPostController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\ResearchTypeController;
use App\Http\Controllers\Admin\ResearchTypePointController;
use App\Http\Controllers\Admin\SdgAgendaController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Coordinator\EvaluationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Faculty\ResearchSubmissionController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NewsEngagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResearchCertificateController;
use App\Http\Controllers\ResearchProfileController;
use App\Http\Controllers\SubmissionAttachmentController;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/workspace', function (Request $request) {
        $validated = $request->validate(['workspace' => ['required', 'string', 'in:faculty,staff']]);
        $request->session()->put('workspace', [
            'user_id' => $request->user()->id,
            'role' => $request->user()->role->value,
            'selection' => $validated['workspace'],
        ]);

        return redirect()->route($validated['workspace'] === 'faculty' ? 'faculty.submissions.index' : 'dashboard');
    })->middleware('role:coordinator|admin')->name('workspace.switch');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/submission-attachments/{attachment}/view', [SubmissionAttachmentController::class, 'view'])->name('submission-attachments.view');
    Route::get('/submission-attachments/{attachment}/download', [SubmissionAttachmentController::class, 'download'])->name('submission-attachments.download');
    Route::delete('/submission-attachments/{attachment}', [SubmissionAttachmentController::class, 'destroy'])->name('submission-attachments.destroy');
    Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');
    Route::get('/news/{news}/cover', [NewsController::class, 'cover'])->name('news.cover');
    Route::get('/news/{news}/attachments/{attachment}', [NewsController::class, 'download'])->name('news.attachments.download');
    Route::get('/news/{news}/attachments/{attachment}/view', [NewsController::class, 'view'])->name('news.attachments.view');
    Route::post('/news/{news}/reactions', [NewsEngagementController::class, 'storeReaction'])->middleware('throttle:30,1')->name('news.reactions.store');
    Route::delete('/news/{news}/reactions', [NewsEngagementController::class, 'destroyReaction'])->middleware('throttle:30,1')->name('news.reactions.destroy');
    Route::post('/news/{news}/comments', [NewsEngagementController::class, 'storeComment'])->middleware('throttle:5,1')->name('news.comments.store');
    Route::put('/news/{news}/comments/{comment}', [NewsEngagementController::class, 'updateComment'])->middleware('throttle:10,1')->name('news.comments.update');
    Route::delete('/news/{news}/comments/{comment}', [NewsEngagementController::class, 'destroyComment'])->middleware('throttle:10,1')->name('news.comments.destroy');
    Route::get('/reports/fpes', [ReportController::class, 'fpes'])->name('reports.fpes');
    Route::get('/reports/my-fpes', [ReportController::class, 'fpes'])->middleware('role:faculty|coordinator|admin')->name('reports.personal');
    Route::get('/research-profiles', [ResearchProfileController::class, 'index'])->name('research-profiles.index');
    Route::get('/research-profiles/me', [ResearchProfileController::class, 'personal'])->middleware('role:faculty|coordinator|admin')->name('research-profiles.personal');
    Route::get('/research-profiles/{user}', [ResearchProfileController::class, 'show'])->name('research-profiles.show');
    Route::get('/research-authorizations/{author}', [ResearchSubmissionController::class, 'downloadAuthorization'])
        ->name('research-authorizations.download');
    Route::get('/research-authorizations/{author}/view', [ResearchSubmissionController::class, 'viewAuthorization'])
        ->name('research-authorizations.view');
    Route::get('/notifications', [UserNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [UserNotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::middleware('role:faculty|coordinator|admin')->prefix('faculty')->name('faculty.')->group(function () {
        Route::get('submissions/{submission}/certificate', [ResearchCertificateController::class, 'show'])->name('certificates.show');
        Route::get('submissions/{submission}/certificate/download', [ResearchCertificateController::class, 'show'])->name('certificates.download');
        Route::post('submissions/extract-metadata', [ResearchSubmissionController::class, 'extractMetadata'])
            ->middleware('throttle:10,1')
            ->name('submissions.extract-metadata');
        Route::resource('submissions', ResearchSubmissionController::class);
        Route::post('submissions/{submission}/submit', [ResearchSubmissionController::class, 'submit'])
            ->name('submissions.submit');
    });

    Route::middleware('role:coordinator')->prefix('coordinator')->name('coordinator.')->group(function () {
        Route::get('evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
        Route::get('evaluations/{submission}', [EvaluationController::class, 'show'])->name('evaluations.show');
        Route::post('evaluations/{submission}', [EvaluationController::class, 'store'])->name('evaluations.store');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('certification/settings', [CertificationSettingController::class, 'edit'])->name('certification.settings');
        Route::put('certification/settings', [CertificationSettingController::class, 'update'])->name('certification.settings.update');
        Route::get('achievement-tiers', [AchievementTierController::class, 'index'])->name('achievement-tiers.index');
        Route::put('achievement-tiers', [AchievementTierController::class, 'update'])->name('achievement-tiers.update');
        Route::resource('news', NewsPostController::class)->except('show');
        Route::delete('news/{news}/attachments/{attachment}', [NewsPostController::class, 'destroyAttachment'])->name('news.attachments.destroy');
        Route::get('research-types', [ResearchTypeController::class, 'index'])->name('research-types.index');
        Route::post('research-types', [ResearchTypeController::class, 'store'])->name('research-types.store');
        Route::put('research-types/{researchType}', [ResearchTypeController::class, 'update'])->name('research-types.update');
        Route::delete('research-types/{researchType}', [ResearchTypeController::class, 'destroy'])->name('research-types.destroy');
        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('colleges', [OrganizationController::class, 'storeCollege'])->name('colleges.store');
        Route::put('colleges/{college}', [OrganizationController::class, 'updateCollege'])->name('colleges.update');
        Route::delete('colleges/{college}', [OrganizationController::class, 'destroyCollege'])->name('colleges.destroy');
        Route::post('departments', [OrganizationController::class, 'storeDepartment'])->name('departments.store');
        Route::put('departments/{department}', [OrganizationController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('departments/{department}', [OrganizationController::class, 'destroyDepartment'])->name('departments.destroy');
        Route::get('research-mapping', [SdgAgendaController::class, 'index'])->name('research-mapping.index');
        Route::post('research-agendas', [SdgAgendaController::class, 'storeAgenda'])->name('research-agendas.store');
        Route::put('research-agendas/{agenda}', [SdgAgendaController::class, 'updateAgenda'])->name('research-agendas.update');
        Route::delete('research-agendas/{agenda}', [SdgAgendaController::class, 'destroyAgenda'])->name('research-agendas.destroy');
        Route::put('sdgs/{sdg}', [SdgAgendaController::class, 'updateSdg'])->name('sdgs.update');
        Route::get('research-type-points', [ResearchTypePointController::class, 'index'])->name('research-type-points.index');
        Route::put('research-type-points', [ResearchTypePointController::class, 'update'])->name('research-type-points.update');
        Route::get('reports/settings', [ReportController::class, 'settings'])->name('reports.settings');
        Route::put('reports/settings', [ReportController::class, 'updateSettings'])->name('reports.settings.update');
        Route::get('reports/fpes/export', [ReportController::class, 'exportFpes'])->name('reports.fpes.export');
        Route::post('reports/fpes/archives/preview', [FpesArchiveController::class, 'preview'])->name('reports.fpes.archives.preview');
        Route::post('reports/fpes/archives', [FpesArchiveController::class, 'store'])->name('reports.fpes.archives.store');
        Route::post('reports/fpes/archives/{archive}/restore', [FpesArchiveController::class, 'restore'])->name('reports.fpes.archives.restore');
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('approvals/{submission}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('approvals/{submission}', [ApprovalController::class, 'store'])->name('approvals.store');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
