<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\UserController;
use App\Services\HrStaffRecord;
use Illuminate\Http\Request;
class PeopleController extends Controller
{
    public function index(Request $request, string $section = 'directory')
    {
        $user=$request->user();
        abort_unless(\App\Models\User::internalStaff()->whereKey($user->id)->exists(),403);
        if($section==='records'){
            abort_unless($user->hasFullHrAccess(),403);
            return app(HrStaffController::class)->index($request,app(HrStaffRecord::class));
        }
        if($section==='accounts'){
            abort_unless($user->hasFullHrAccess() || $user->hasRole(['admin','super_admin']) || $user->isCvoOrSuperAdmin(),403);
            return response(app(UserController::class)->index($request))->header('Cache-Control','private, no-store');
        }
        return response(app(DirectoryController::class)->index($request))->header('Cache-Control','private, no-store');
    }
}
