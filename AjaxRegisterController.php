<?php

namespace App\Http\Controllers\Ajax;

use App\User;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

class AjaxRegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');

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
            'email'                 => 'required|string|email|max:255|unique:users',
            'password'              => 'required|string|min:6|max:60',
			'g-recaptcha-response'  => 'required|string|max:800',
        ]);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator_conf(array $data)
    {
        return Validator::make($data, [
            'email'     => 'required|string|email|max:255|unique:users',
            'password'  => 'required|string|min:6|max:60',
			'firstname' => 'required|regex:/^[а-яА-ЯёЁa-zA-Z]+$/u|string|min:2|max:35',
			'lastname'  => 'required|regex:/^[а-яА-ЯёЁa-zA-Z]+$/u|string|min:2|max:35',
            'rules'     => 'required|accepted'
        ]);
    }

    /**
     * Проверка google-recaptcha
     *
     * @param  string  $key
     * @return boolean
     */
    protected function validateCaptcha(string $key)
    {
        $client = new Client();
        $response = $client->post(env('RECAPTCHA_URL', 'https://www.google.com/recaptcha/api/siteverify'), [
            'form_params' => [
                'secret' => env('RECAPTCHA_KEY', 'none'),
                'response' => $key,
            ]
        ]);

        $response = json_decode($response->getBody()->getContents(), true);

        return $response !== NULL && $response['success'] === true;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
			'firstname' => $data['firstname'],
			'lastname'  => $data['lastname'],
        ]);
    }

    /**
     * Обработчик запроса регистрации
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        if ($this->validateCaptcha($request->input('g-recaptcha-response'))) {

            $request->session()->put('email', $request->email);
            $request->session()->put('password', $request->password);

            $response = [
                'status' => 'success'
            ];
        } else {
            $response = [
                'status'    => 'error',
                'message'   => 'Failed captcha',
		    ];
        }

        return response()->json($response);
    }

	/**
     * Обработчик запроса подтверждения регистрации
     *
     * @param Request $request
	 * @return JsonResponse
     */
    public function confirm(Request $request)
    {
        $data = $request->all();

        $data['email'] = $request->session()->get('email', false);
        $data['password'] = $request->session()->get('password', false);

        $this->validator_conf($data)->validate();

        event(new Registered($user = $this->create($data)));

        $this->guard()->login($user, true);

        $request->session()->flush();

        return response()->json([
            'status' => 'success',
            'message' => 'Registered!'
        ]);
    }

}
