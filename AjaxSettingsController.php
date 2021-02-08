<?php

namespace App\Http\Controllers\Ajax;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\MustVerifyEmail;
use App\Notifications\VerifyEmail;

class AjaxSettingsController extends Controller
{
    use MustVerifyEmail;

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
     * Смена пароля/почты.
     * //todo упростить метод
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function access(Request $request)
    {
        $settings = array_filter($this->validateAccess($request->all())->validate());

        $newMail = mb_strtolower($settings['email']);

        $response = ['status' => 'success'];

        if ($newMail !== Auth::user()->email) {
            Auth::user()->notify(new VerifyEmail($newMail));

            $response['email'] = 'true';
        }

        if (key_exists('password', $settings)) {
            $this->verifyPass($settings['password_old']);
            Auth::user()->password = Hash::make($settings['password']);

            Auth::user()->save();

            $response['password'] = 'true';
        }

        return response()->json($response);
    }

    /**
     * Create a new controller instance.
     *
     * @param string $pass
     * @throws ValidationException
     */
    public function verifyPass($pass)
    {
        if(! Hash::check($pass, Auth::user()->password)){
            $this->sendFailedPassResponse();
        }
    }

    /**
     * Get the failed login response instance.
     *
     * @throws ValidationException
     */
    protected function sendFailedPassResponse()
    {
        throw ValidationException::withMessages([
            'password_old' => [trans('auth.failed')],
        ]);
    }

    /**
     * Сохранить настройки.
     *
	 * @param Request $request
     * @return JsonResponse
     */
    public function settings(Request $request)
    {
		$settings = $this->validateSettings($request->all())->validate();

        $settings['firstname']  = title_case($settings['firstname']);
        $settings['lastname']   = title_case($settings['lastname']);
        $settings['about'] = $settings['about'] ?? '' ;

        Auth::user()->update($settings);
		
		return response()->json([
			'status' => 'success',
		]);
    }
	
	/**
     * Получаем экземпляр валидатора настроек
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validateSettings(array $data)
    {
        return Validator::make(array_filter($data), [
            'firstname' => 'required|regex:/^[а-яА-ЯёЁa-zA-Z]+$/u|string|min:2|max:35',
            'lastname'  => 'required|regex:/^[а-яА-ЯёЁa-zA-Z]+$/u|string|min:2|max:35',
            'gender'    => 'required|string|max:30',
            'birthday'  => 'date|max:55',
            'about'     => 'string|max:500',
        ]);
    }

    /**
     * Получаем экземпляр валидатора учетных данных
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validateAccess(array $data)
    {
        return Validator::make($data, [
            'email' => [
                'required',
                Rule::unique('users')->ignore(Auth::user()->id),
                'string',
                'email',
                'max:255',
            ],

            'password_old'          => 'nullable|string|min:6|max:60|required_with_all:password',
            'password'              => 'nullable|string|min:6|max:60|confirmed|different:password_old',
            'password_confirmation' => 'nullable|same:password'
        ]);
    }
}
