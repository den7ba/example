<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
//use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;


class AjaxForgotPassController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

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
     * Send a reset link to the given user.
     *
     * @param  Request  $request
     * @return RedirectResponse|JsonResponse
     * @throws ValidationException
     */
    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);
	
        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = $this->broker()->sendResetLink( //passwords.user if no user
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT //passwords.sent
                    ? $this->sendResetLinkResponse()
                    : $this->sendResetLinkFailedResponse();
    }
	
    /**
     * Validate the email for the given request.
     *
     * @param Request $request
     * @throws ValidationException
     * @return void
     */
    protected function validateEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|string|email|max:255|exists:users,email']);
    }
	
    /**
     * Get the response for a successful password reset link.
     *
     * @return RedirectResponse|JsonResponse
     */
    protected function sendResetLinkResponse()
    {
		return response()->json([
			'status' => 'success',
			'message' => 'Email has been sent!'
		]);
    }

    /**
     * Get the response for a failed password reset link.
     *
     * @return RedirectResponse|JsonResponse
     */
    protected function sendResetLinkFailedResponse()
    {
		return response()->json([
			'status' => 'error',
			'message' => 'The given data was invalid.'
		], 422);
    }
}
