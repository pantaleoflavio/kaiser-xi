<?php

use App\Http\Controllers\Api\V1\AcceptLeagueInvitationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EligiblePlayerController;
use App\Http\Controllers\Api\V1\FantasyTeamController;
use App\Http\Controllers\Api\V1\FantasyTeamPlayerController;
use App\Http\Controllers\Api\V1\LeagueController;
use App\Http\Controllers\Api\V1\LeagueInvitationController;
use App\Http\Controllers\Api\V1\LeagueMemberController;
use App\Http\Controllers\Api\V1\LeagueSettingController;
use App\Models\FantasyTeam;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn () => response()->json([
        'status' => 'ok',
        'competition' => config('competition.code'),
    ]));

    // Authentication routes
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/league-invitations/{code}', [AcceptLeagueInvitationController::class, 'show'])->name('api.v1.league-invitations.show');
        Route::post('/league-invitations/{code}/accept', [AcceptLeagueInvitationController::class, 'accept'])->name('api.v1.league-invitations.accept');
    });

    Route::prefix('leagues')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
        // League routes
        Route::get('/', [LeagueController::class, 'index']);
        Route::post('/', [LeagueController::class, 'store']);
        Route::get('/{league}', [LeagueController::class, 'show'])->middleware('can:view,league');
        Route::patch('/{league}', [LeagueController::class, 'update'])->middleware('can:update,league');
        Route::delete('/{league}', [LeagueController::class, 'destroy'])->middleware('can:delete,league');
        Route::get('/{league}/members', [LeagueMemberController::class, 'index'])->middleware('can:view,league');
        Route::delete('/{league}/members/{user}', [LeagueMemberController::class, 'destroy'])
            ->name('api.v1.leagues.members.destroy')
            ->middleware('can:removeMember,league,user');
        Route::patch('/{league}/members/{user}/role', [LeagueMemberController::class, 'updateRole'])
            ->name('api.v1.leagues.members.role.update')
            ->middleware('can:manageMemberRole,league,user');
        Route::get('/{league}/eligible-players', [EligiblePlayerController::class, 'index'])
            ->name('api.v1.leagues.eligible-players.index')
            ->middleware('can:view,league');

        // League settings routes
        Route::get('/{league}/settings', [LeagueSettingController::class, 'show'])
            ->name('api.v1.leagues.settings.show')
            ->middleware('can:view,league');

        Route::patch('/{league}/settings', [LeagueSettingController::class, 'update'])
            ->name('api.v1.leagues.settings.update')
            ->middleware('can:manageSettings,league');

        // League invitation routes
        Route::get('/{league}/invitations', [LeagueInvitationController::class, 'index'])
            ->name('api.v1.leagues.invitations.index')
            ->middleware('can:manageInvitations,league');

        Route::post('/{league}/invitations', [LeagueInvitationController::class, 'store'])
            ->name('api.v1.leagues.invitations.store')
            ->middleware('can:manageInvitations,league');

        Route::delete('/{league}/invitations/{invitation}', [LeagueInvitationController::class, 'destroy'])
            ->name('api.v1.leagues.invitations.destroy')
            ->middleware('can:manageInvitations,league');

        // Fantasy team routes
        Route::get('/{league}/fantasy-teams', [FantasyTeamController::class, 'index'])
            ->name('api.v1.leagues.fantasy-teams.index')
            ->middleware('can:viewAny,' . FantasyTeam::class . ',league');

        Route::post('/{league}/fantasy-teams', [FantasyTeamController::class, 'store'])
            ->name('api.v1.leagues.fantasy-teams.store')
            ->middleware('can:create,' . FantasyTeam::class . ',league');

        Route::get('/{league}/fantasy-teams/{fantasyTeam}', [FantasyTeamController::class, 'show'])
            ->name('api.v1.leagues.fantasy-teams.show')
            ->middleware('can:view,fantasyTeam');

        Route::patch('/{league}/fantasy-teams/{fantasyTeam}', [FantasyTeamController::class, 'update'])
            ->name('api.v1.leagues.fantasy-teams.update')
            ->middleware('can:update,fantasyTeam');

        Route::get('/{league}/fantasy-teams/{fantasyTeam}/players', [FantasyTeamPlayerController::class, 'index'])
            ->name('api.v1.leagues.fantasy-teams.players.index')
            ->middleware('can:viewRoster,fantasyTeam');

        Route::post('/{league}/fantasy-teams/{fantasyTeam}/players', [FantasyTeamPlayerController::class, 'store'])
            ->name('api.v1.leagues.fantasy-teams.players.store')
            ->middleware('can:manageRoster,fantasyTeam,league');

        Route::delete('/{league}/fantasy-teams/{fantasyTeam}/players/{player}', [FantasyTeamPlayerController::class, 'destroy'])
            ->name('api.v1.leagues.fantasy-teams.players.destroy')
            ->middleware('can:manageRoster,fantasyTeam,league');
    });
});
