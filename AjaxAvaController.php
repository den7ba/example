<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AjaxAvaController extends Controller
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
     * Поставить новую аву
     *
	 * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $request = $this->validator($request->all())->validate();

        $ava = $request['croppedImage'];

        Auth::user()->setAvatar($ava);

		return response()->json([
			'status' => 'success',
		]);
    }
	
	/**
     * Получаем экземпляр валидатора авы
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'croppedImage' => 'required|mimes:jpeg,jpg,png|dimensions:min_width=100,min_height=100,max_width=500,max_height=500,ratio=1/1',
        ]);
    }
}
