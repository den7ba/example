<?php

namespace App\Http\Controllers\Ajax;

use App\Social;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;
use App\Repositories\SocialRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

class AjaxSocialAuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Авторизация пользователей через социальные сети
    |--------------------------------------------------------------------------
    |
    | Этот контроллер обрабатывает авторизацию новых пользователей, а также их
    | проверку и создание. По умолчанию этот контроллер использует  трейт для
    | обеспечения этой функции, не требуя дополнительного кода.
    |
    */

    use RegistersUsers;

    /**
     * Экземпляр SocialRepository
     *
     * @var SocialRepository
     */
	protected $social;
	
    /**
     * Create a new controller instance.
     *
     * @param  SocialRepository  $social
     * @return void
     */
    public function __construct(SocialRepository $social)
    {
        $this->middleware('guest');
		$this->social = $social;
    }
	
    /**
     * JS callbak обработчик
     *
     * @param  string|null  $provider
     * @return Factory|View
     */
    public function callback($provider = null)
    {
		$social = Socialite::driver($provider)->stateless()->user(); //oauth-код -> токен+данные
		
		return ($uid = Social::where($provider, $social->id)->first())
					? $this->login($uid->user_id)
					: $this->reg($social, $provider);
    }
	
    /**
     * Оповестить front-end об успешной аутентификации
     *
     * @param  int  $id
     * @return View
     */
    protected function login(int $id)
    {
		Auth::loginUsingId($id, true);
		
		return view('social.redirect');
	}
	
    /**
     * Оповестить front-end о необходимости зарегистрироваться
     *
	 * @param  string  $provider
	 * @param  Socialite  $social
     * @return View
     */
    protected function reg($social, string $provider)
    {
		session([
			'soc_provider' 	=> $provider,
			
			'soc_id' 		=> $social->id,
			'soc_nickname' 	=> $social->nickname,
			'soc_firstname' => $social->firstname,
			'soc_lastname' 	=> $social->lastname,
			'soc_avatar' 	=> $social->avatar,
			'soc_email' 	=> $social->email,
		]);
		
		return view('social.redirect')->with([
			'redirect' 	=> route('social.form')
		]);
	}
	
    /**
     * Отображение формы регистрации
     *
     * @param Request $request
     * @return View
     */
    public function showRegForm(Request $request)
    {
		$data = $request->session()->all();
		
		(key_exists('soc_provider' ,$data)) 
						?: abort(404);
		
		$names = [
			'vk' 	=> 'Vkontakte',
			'ok' 	=> 'Odnoklassniki',
			'insta' => 'Instagram',
			'fb'	=> 'Facebook',
		];
		
        return view('social.form')->with([
			'data' 		=> $data,
			'soc_name'	=> $names[$data['soc_provider']]
		]);
    }
	
	/**
     * Регистрация нового пользователя
     *
     * @param Request $request
	 * @return JsonResponse
     */
    public function register(Request $request)
    {	
		$data = array_merge($request->session()->all(), 
							$request->all());
		
		$this->validator($data)->validate();
		
        event(new Registered($user = $this->social->createUser($data, $request)));
		
		$this->social->createAvatar($data['soc_avatar'], $user->id);
		
        $this->guard()->login($user, true);

		$request->session()->flush();
		
		return response()->json([
			'status'  => 'success',
			'message' => 'Registered!'
		]);
    }
	
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'email' 		=> 'required|string|email|max:255|unique:users',
            'password' 		=> 'required|string|min:6|max:60',
			'firstname' 	=> 'required|regex:/^[а-яА-ЯёЁa-zA-Z]+$/u|string|min:2|max:35',
			'lastname' 		=> 'required|regex:/^[а-яА-ЯёЁa-zA-Z]+$/u|string|min:2|max:35',
			'soc_provider' 	=> 'required|string',
        ]);
    }
}
