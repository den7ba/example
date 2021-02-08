<?php

namespace App\Http\Controllers\Ajax;

use App\Social;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AjaxSocialManagerController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * JS callbak обработчик.
     *
     * @param  string|null  $provider
     * @param  string|null  $action
     * @return View
     */
    public function callback($provider = null, $action = null)
    {
		session(['soc_action' => $action]); // for build redirectUrl in drivers

		$user = Socialite::driver($provider)->stateless()->user(); //code -> token + data

		$social = json_decode(Auth::user()->social, true);

		$social = ($action === 'bind') //bind or unbind social to user
				? $this->bind($social, $provider, $user)
				: $this->unbind($social, $provider);

		//if error -> this social already exists
        if ($social === false) return view('social.redirect', ['action' => 'M.toast({html: \'This social already exists!\', classes: \'gradient-45deg-red-pink gradient-shadow\'})']);
		
		Auth::user()->social = json_encode($social);
		Auth::user()->save();
		
		return view('social.redirect');
    }
	
   /**
     * Отображение формы менеджера соцсетей
     *
     * @return View
     */
    public function socialManager()
    {
		$socials = json_decode(Auth::user()->social, true);

        foreach (Socialite::getList() as $provider => $fullname) {
            if (! isset($socials[$provider])){
                $socials[$provider] = [
                    'id' => false,
                    'friends' => null,
                ];
            }
        }

		return view('social.manager')->with([
			'socials' => $socials,
		]);
	}
	
	/**
     * Привязка соцсети к пользователю
	 *
	 * @param  mixed  $social
     * @param  string  $provider
	 * @param  Object  $user
	 * @return array|bool $social
     */
    protected function bind($social, string $provider, $user)
    {
        if (Social::where($provider, $user->id)->first())
            return false;

		empty($social)
			    ? $social = array($provider => 	array('id' => $user->id, 'friends' => $user->friends))
			    : $social[$provider] = 			array('id' => $user->id, 'friends' => $user->friends);
		
		Auth::user()->socials
					? Auth::user()->socials		->update([$provider => $user->id])
					: Auth::user()->socials()	->create([$provider => $user->id]);
		
		return $social;
    }

	/**
     * Отвязка соцсети от пользователя
	 *
     * @param  array  $social
	 * @param  string  $provider
	 * @return array $social
     */
    protected function unbind(array $social, string $provider)
    {
		Auth::user()->socials->update([$provider => null]);
		
		unset($social[$provider]);
		
		return $social;
    }
}
