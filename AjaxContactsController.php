<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AjaxContactsController extends Controller
{
	
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Кидаем сообщение в очередь на обработку.
     *
	 * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
		$data = $this->validator($request->all())->validate();

        $this->dispatch(new ProcessReviews($data));

		return response()->json([
			'status' => 'success',
		]);
    }
	
	/**
     * Валидация данных из формы обратной связи
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name'      => 'required|string|max:50',
            'email'     => 'required|string|max:60',
            'text'      => 'required|string|max:1000',
        ]);
    }
}
