<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AjaxSmartSettingsController extends Controller
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
     * Сохранить настройки.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function save(Request $request)
    {
		$settings = $this->validator($request->all())->validate();

		Auth::user()->saveSmartSettings($settings);
		
		Auth::user()->save();
		
		return response()->json([
			'status' => 'success',
            'message'=> 'Операция успешна!',
		]);
    }
	
	/**
     * Получаем экземпляр валидатора полученных настроек
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'setting1' => 'sometimes|string|max:2',
            'setting2' => 'sometimes|string|max:2',
        ]);
    }
}
