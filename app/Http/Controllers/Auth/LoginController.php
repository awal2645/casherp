<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Services\BusinessContextService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Rules\ReCaptcha;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $moduleUtil;

    protected $businessContext;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil, BusinessContextService $businessContext)
    {
        $this->middleware('guest')->except('logout');
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
        $this->businessContext = $businessContext;
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Change authentication from email to username
     *
     * @return void
     */
    public function username()
    {
        return 'username';
    }

    public function logout()
    {
        $this->businessUtil->activityLog(auth()->user(), 'logout');

        request()->session()->flush();
        \Auth::logout();

        return redirect('/login');
    }

    /**
     * The user has been authenticated.
     * Check if the business is active or not.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        $business = $this->businessContext->recoverActiveBusiness($user);

        if (empty($business)) {
            \Auth::logout();

            return redirect('/login')
              ->with(
                  'status',
                  ['success' => 0, 'msg' => __('lang_v1.business_inactive')]
              );
        }

        if ((int) $user->business_id !== (int) $business->id) {
            $user->business_id = $business->id;
            $user->save();
        }

        $this->businessUtil->activityLog($user, 'login', null, [], false, $business->id);

        $status = $user->getAttribute('status');
        if (\Illuminate\Support\Facades\Schema::hasColumn($user->getTable(), 'status')
            && $status !== null
            && $status !== ''
            && $status != 'active') {
            \Auth::logout();

            return redirect('/login')
              ->with(
                  'status',
                  ['success' => 0, 'msg' => __('lang_v1.user_inactive')]
              );
        } elseif (isset($user->allow_login) && ! $user->allow_login) {
            \Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.login_not_allowed')]
                );
        } elseif (($user->user_type == 'user_customer') && ! $this->moduleUtil->hasThePermissionInSubscription($user->business_id, 'crm_module')) {
            \Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.business_dont_have_crm_subscription')]
                );
        }
    }

    protected function redirectTo()
    {
        $user = \Auth::user();
        if (! $user->can('dashboard.data') && $user->can('sell.create')) {
            return '/pos/create';
        }

        if ($user->user_type == 'user_customer') {
            return 'contact/contact-dashboard';
        }

        return '/home';
    }

    public function validateLogin(Request $request)
    {
        if(config('constants.enable_recaptcha')){
            $this->validate($request, [
                $this->username() => 'required|string',
                'password' => 'required|string',
                'g-recaptcha-response' => ['required', new ReCaptcha]
            ]);
        }else{
            $this->validate($request, [
                $this->username() => 'required|string',
                'password' => 'required|string',
            ]);
        }
       
    }

}
