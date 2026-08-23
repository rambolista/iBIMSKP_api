<?php

use App\Http\Controllers\Api\AccessManagement\CustomerController;
use App\Http\Controllers\Api\AccessManagement\CustomerMenuController;
use App\Http\Controllers\Api\AccessManagement\MenuController;
use App\Http\Controllers\Api\AccessManagement\MenuIconController;
use App\Http\Controllers\Api\AccessManagement\RoleController;
use App\Http\Controllers\Api\AccessManagement\UserController;
use App\Http\Controllers\Api\Administration\BarangayOfficialController;
use App\Http\Controllers\Api\Administration\BarangaySettingController;
use App\Http\Controllers\Api\Administration\DocumentLogoController;
use App\Http\Controllers\Api\Administration\DropdownSettingController;
use App\Http\Controllers\Api\Administration\IdTemplateController;
use App\Http\Controllers\Api\Administration\NatureOfCaseController;
use App\Http\Controllers\Api\AuditHistoryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserActivityController;
use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\DeleteAccountController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PinController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\Auth\TwoFactorSettingsController;
use App\Http\Controllers\Api\Auth\UnlockController;
use App\Http\Controllers\Api\Auth\UserController as AuthUserController;
use App\Http\Controllers\Api\BarangayId\BarangayIdController;
use App\Http\Controllers\Api\Blotter\BlotterController;
use App\Http\Controllers\Api\BarangayServices\DocumentTemplateController;
use App\Http\Controllers\Api\BarangayServices\ServiceRequestController;
use App\Http\Controllers\Api\BarangayServices\ServiceTypeController;
use App\Http\Controllers\Api\CustomerAuth\ForgotPasswordController as CustomerForgotPasswordController;
use App\Http\Controllers\Api\CustomerAuth\LoginController as CustomerLoginController;
use App\Http\Controllers\Api\CustomerAuth\RegisterController as CustomerRegisterController;
use App\Http\Controllers\Api\CustomerAuth\ResetPasswordController as CustomerResetPasswordController;
use App\Http\Controllers\Api\CustomerAuth\TwoFactorChallengeController as CustomerTwoFactorChallengeController;
use App\Http\Controllers\Api\CustomerAuth\TwoFactorSettingsController as CustomerTwoFactorSettingsController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\Dashboard\BarangayDashboardController;
use App\Http\Controllers\Api\Dashboard\DashboardAnalyticsController;
use App\Http\Controllers\Api\Dashboard\DashboardInformationController;
use App\Http\Controllers\Api\KatarungangPambarangay\CaseController as KatarungangPambarangayCaseController;
use App\Http\Controllers\Api\KatarungangPambarangay\HearingController as KatarungangPambarangayHearingController;
use App\Http\Controllers\Api\KatarungangPambarangay\LuponDashboardController;
use App\Http\Controllers\Api\KatarungangPambarangay\LuponMemberController as KatarungangPambarangayLuponMemberController;
use App\Http\Controllers\Api\KatarungangPambarangay\PangkatController as KatarungangPambarangayPangkatController;
use App\Http\Controllers\Api\Payments\PaymentTransactionController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Api\LandingPageSectionController;
use App\Http\Controllers\Api\LandingPageSectionItemController;
use App\Http\Controllers\Api\LayoutSettingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectSettingController;
use App\Http\Controllers\Api\PublicLandingPageController;
use App\Http\Controllers\Api\ResidentManagement\HouseholdController;
use App\Http\Controllers\Api\ResidentManagement\PurokController;
use App\Http\Controllers\Api\ResidentManagement\ResidentController;
use App\Http\Controllers\Api\ThemePreferenceController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Support\Facades\Route;

// ── Public auth routes (no token required) ─────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class);
    Route::post('/forgot-password', ForgotPasswordController::class);
    Route::post('/reset-password', ResetPasswordController::class);
    Route::post('/2fa/challenge/verify', TwoFactorChallengeController::class)->middleware('throttle:10,1');
});

Route::prefix('customer')->group(function () {
    Route::post('/login', CustomerLoginController::class);
    Route::post('/register', CustomerRegisterController::class);
    Route::post('/forgot-password', CustomerForgotPasswordController::class);
    Route::post('/reset-password', CustomerResetPasswordController::class);
    Route::post('/two-factor/challenge/verify', CustomerTwoFactorChallengeController::class);
});

Route::get('/project-settings', [ProjectSettingController::class, 'show']);
Route::get('/barangay-settings', [BarangaySettingController::class, 'show']);
Route::get('/layout-settings', [LayoutSettingController::class, 'show']);
Route::get('/landing-page', [PublicLandingPageController::class, 'show']);
Route::get('/service-request-verification/{verificationCode}', [ServiceRequestController::class, 'verifyDocument']);
Route::get('/barangay-id-verification/{verificationCode}', [BarangayIdController::class, 'verifyDocument']);
Route::get('/id-templates/current', [IdTemplateController::class, 'current']);

// ── Protected routes (valid Sanctum token required) ────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', LogoutController::class);
        Route::get('/user', AuthUserController::class);
        Route::put('/change-password', ChangePasswordController::class);
        Route::post('/unlock', UnlockController::class)->middleware('throttle:5,1');
        Route::get('/2fa', [TwoFactorSettingsController::class, 'status']);
        Route::post('/2fa/setup', [TwoFactorSettingsController::class, 'setup'])->middleware('throttle:5,1');
        Route::post('/2fa/confirm', [TwoFactorSettingsController::class, 'confirm'])->middleware('throttle:10,1');
        Route::post('/2fa/disable', [TwoFactorSettingsController::class, 'disable']);
        Route::put('/pin', [PinController::class, 'update']);
        Route::post('/pin/verify', [PinController::class, 'verify'])->middleware('throttle:5,1');
        Route::delete('/account', DeleteAccountController::class);
    });

    // User profile
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::get('/profile/history', [UserProfileController::class, 'history']);
        Route::put('/profile', [UserProfileController::class, 'update']);
        Route::post('/avatar', [UserProfileController::class, 'updateAvatar']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    Route::put('/layout-settings', [LayoutSettingController::class, 'update']);
    Route::put('/layout-settings/theme', [LayoutSettingController::class, 'updateTheme']);
    Route::put('/user/theme-preference', [ThemePreferenceController::class, 'update']);
    Route::put('/project-settings', [ProjectSettingController::class, 'update']);
    Route::put('/barangay-settings', [BarangaySettingController::class, 'update']);
    Route::get('/customer-menus', [CustomerMenuController::class, 'index']);

    Route::prefix('customer')->group(function () {
        Route::get('/2fa', [CustomerTwoFactorSettingsController::class, 'status']);
        Route::post('/2fa/setup', [CustomerTwoFactorSettingsController::class, 'setup']);
        Route::post('/2fa/confirm', [CustomerTwoFactorSettingsController::class, 'confirm']);
        Route::post('/2fa/disable', [CustomerTwoFactorSettingsController::class, 'disable']);
    });

    Route::prefix('customer')->group(function () {
        Route::get('/profile', [CustomerProfileController::class, 'show']);
        Route::put('/profile', [CustomerProfileController::class, 'update']);
    });

    Route::prefix('landing-pages')->group(function () {
        Route::get('/', [LandingPageController::class, 'index']);
        Route::post('/', [LandingPageController::class, 'store']);
        Route::post('/{landingPage}/duplicate', [LandingPageController::class, 'duplicate']);
        Route::get('/{landingPage}', [LandingPageController::class, 'show']);
        Route::match(['put', 'patch'], '/{landingPage}', [LandingPageController::class, 'update']);
        Route::delete('/{landingPage}', [LandingPageController::class, 'destroy']);

        Route::get('/{landingPage}/sections', [LandingPageSectionController::class, 'index']);
        Route::post('/{landingPage}/sections', [LandingPageSectionController::class, 'store']);
        Route::patch('/{landingPage}/sections/reorder', [LandingPageSectionController::class, 'reorder']);
        Route::get('/{landingPage}/sections/{section}', [LandingPageSectionController::class, 'show']);
        Route::match(['put', 'patch'], '/{landingPage}/sections/{section}', [LandingPageSectionController::class, 'update']);
        Route::delete('/{landingPage}/sections/{section}', [LandingPageSectionController::class, 'destroy']);

        Route::get('/{landingPage}/sections/{section}/items', [LandingPageSectionItemController::class, 'index']);
        Route::post('/{landingPage}/sections/{section}/items', [LandingPageSectionItemController::class, 'store']);
        Route::patch('/{landingPage}/sections/{section}/items/reorder', [LandingPageSectionItemController::class, 'reorder']);
        Route::get('/{landingPage}/sections/{section}/items/{item}', [LandingPageSectionItemController::class, 'show']);
        Route::match(['put', 'patch'], '/{landingPage}/sections/{section}/items/{item}', [LandingPageSectionItemController::class, 'update']);
        Route::delete('/{landingPage}/sections/{section}/items/{item}', [LandingPageSectionItemController::class, 'destroy']);
    });

    // Access Management — Menus
    Route::prefix('access-management')->group(function () {
        Route::get('/menu-icons', [MenuIconController::class, 'index']);
        Route::apiResource('/menus', MenuController::class);
        Route::apiResource('/customer-menus', CustomerMenuController::class);
        Route::apiResource('/customers', CustomerController::class);

        // Roles
        Route::get('/roles/{role}/menu-permissions', [RoleController::class, 'menuPermissions']);
        Route::post('/roles/{role}/menu-permissions', [RoleController::class, 'saveMenuPermissions']);
        Route::apiResource('/roles', RoleController::class);

        // Users
        Route::post('/users/{user}/roles', [UserController::class, 'assignRoles']);
        Route::apiResource('/users', UserController::class);
    });

    Route::apiResource('/residents/households', HouseholdController::class)->parameters(['households' => 'household']);
    Route::apiResource('/residents/puroks', PurokController::class)->parameters(['puroks' => 'purok']);
    Route::get('/residents/duplicates', [ResidentController::class, 'duplicates']);
    Route::apiResource('/residents', ResidentController::class);

    Route::apiResource('/barangay-services/requests', ServiceRequestController::class)->parameters(['requests' => 'serviceRequest']);
    Route::get('/barangay-services/requests/{serviceRequest}/document-preview', [ServiceRequestController::class, 'documentPreview']);
    Route::post('/barangay-services/requests/{serviceRequest}/verify', [ServiceRequestController::class, 'verify']);
    Route::post('/barangay-services/requests/{serviceRequest}/validate', [ServiceRequestController::class, 'validateRequirements']);
    Route::post('/barangay-services/requests/{serviceRequest}/approve', [ServiceRequestController::class, 'approve']);
    Route::post('/barangay-services/requests/{serviceRequest}/proceed-to-payment', [ServiceRequestController::class, 'proceedToPayment']);
    Route::post('/barangay-services/requests/{serviceRequest}/proceed-to-printing', [ServiceRequestController::class, 'proceedToPrinting']);
    Route::post('/barangay-services/requests/{serviceRequest}/record-payment', [ServiceRequestController::class, 'recordPayment']);
    Route::post('/barangay-services/requests/{serviceRequest}/release', [ServiceRequestController::class, 'release']);
    Route::post('/barangay-services/requests/{serviceRequest}/reject', [ServiceRequestController::class, 'reject']);
    Route::post('/barangay-services/requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel']);
    Route::apiResource('/barangay-services/types', ServiceTypeController::class)->parameters(['types' => 'serviceType']);
    Route::apiResource('/barangay-services/document-templates', DocumentTemplateController::class)->parameters(['document-templates' => 'documentTemplate']);
    Route::apiResource('/apps/administration/document-logos', DocumentLogoController::class)->parameters(['document-logos' => 'documentLogo']);
    Route::apiResource('/apps/administration/barangay-officials', BarangayOfficialController::class)->parameters(['barangay-officials' => 'barangayOfficial']);
    Route::apiResource('/apps/administration/dropdown-settings/nature-of-case', NatureOfCaseController::class)->parameters(['nature-of-case' => 'natureOfCase']);
    Route::post('/barangay-id/{barangayId}/verify', [BarangayIdController::class, 'verify']);
    Route::post('/barangay-id/{barangayId}/approve', [BarangayIdController::class, 'approve']);
    Route::post('/barangay-id/{barangayId}/proceed-to-payment', [BarangayIdController::class, 'proceedToPayment']);
    Route::post('/barangay-id/{barangayId}/proceed-to-printing', [BarangayIdController::class, 'proceedToPrinting']);
    Route::post('/barangay-id/{barangayId}/record-payment', [BarangayIdController::class, 'recordPayment']);
    Route::post('/barangay-id/{barangayId}/release', [BarangayIdController::class, 'release']);
    Route::post('/barangay-id/{barangayId}/report-lost', [BarangayIdController::class, 'reportLost']);
    Route::post('/barangay-id/{barangayId}/process-replacement', [BarangayIdController::class, 'processReplacement']);
    Route::post('/barangay-id/{barangayId}/cancel', [BarangayIdController::class, 'cancel']);
    Route::apiResource('/barangay-id', BarangayIdController::class)->parameters(['barangay-id' => 'barangayId']);
    Route::apiResource('/apps/administration/id-templates', IdTemplateController::class)->parameters(['id-templates' => 'idTemplate']);
    Route::post('/blotter/{blotter}/investigate', [BlotterController::class, 'investigate']);
    Route::post('/blotter/{blotter}/refer', [BlotterController::class, 'refer']);
    Route::post('/blotter/{blotter}/resolve', [BlotterController::class, 'resolve']);
    Route::post('/blotter/{blotter}/close', [BlotterController::class, 'close']);
    Route::post('/blotter/{blotter}/reopen', [BlotterController::class, 'reopen']);
    Route::post('/blotter/{blotter}/attachments', [BlotterController::class, 'addAttachments']);
    Route::post('/blotter/{blotter}/notes', [BlotterController::class, 'addNote']);
    Route::apiResource('/blotter', BlotterController::class)->parameters(['blotter' => 'blotter']);
    Route::post('/payments/{paymentTransaction}/collect-payment', [PaymentTransactionController::class, 'collectPayment']);
    Route::post('/payments/{paymentTransaction}/void', [PaymentTransactionController::class, 'voidTransaction']);
    Route::post('/payments/{paymentTransaction}/refund', [PaymentTransactionController::class, 'refundTransaction']);
    Route::get('/payments', [PaymentTransactionController::class, 'index']);
    Route::post('/payments', [PaymentTransactionController::class, 'store']);
    Route::get('/payments/{paymentTransaction}', [PaymentTransactionController::class, 'show']);
    Route::post('/katarungang-pambarangay/cases/{case}/close', [KatarungangPambarangayCaseController::class, 'close']);
    Route::post('/katarungang-pambarangay/cases/{case}/settlement', [KatarungangPambarangayCaseController::class, 'saveSettlement']);
    Route::post('/katarungang-pambarangay/cases/{case}/settlement/approve', [KatarungangPambarangayCaseController::class, 'approveSettlement']);
    Route::post('/katarungang-pambarangay/cases/{case}/documents/{documentTemplate}/print', [KatarungangPambarangayCaseController::class, 'markDocumentPrinted']);
    Route::post('/katarungang-pambarangay/cases/{case}/attachments', [KatarungangPambarangayCaseController::class, 'addAttachments']);
    Route::apiResource('/katarungang-pambarangay/cases', KatarungangPambarangayCaseController::class)->parameters(['cases' => 'case']);
    Route::delete('/katarungang-pambarangay/hearings/{hearing}/force', [KatarungangPambarangayHearingController::class, 'forceDestroy']);
    Route::apiResource('/katarungang-pambarangay/hearings', KatarungangPambarangayHearingController::class)->parameters(['hearings' => 'hearing']);
    Route::apiResource('/katarungang-pambarangay/lupon-members', KatarungangPambarangayLuponMemberController::class)->parameters(['lupon-members' => 'luponMember']);
    Route::apiResource('/katarungang-pambarangay/pangkat', KatarungangPambarangayPangkatController::class)->parameters(['pangkat' => 'pangkat']);
    Route::get('/dashboard/barangay/stats', [BarangayDashboardController::class, 'index']);
    Route::get('/dashboard/information/stats', [DashboardInformationController::class, 'index']);
    Route::get('/dashboard/analytics/stats', [DashboardAnalyticsController::class, 'index']);
    Route::get('/katarungang-pambarangay/dashboard/stats', [LuponDashboardController::class, 'index']);
    Route::get('/audit-history/{resource}/{recordId}', [AuditHistoryController::class, 'index']);
    Route::get('/user-activity', [UserActivityController::class, 'index']);
    Route::get('/reports/catalog', [ReportController::class, 'catalog']);
    Route::get('/reports/generate', [ReportController::class, 'generate']);
    Route::apiResource('/events', EventController::class);
});
