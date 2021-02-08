<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Repositories\WebsocketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Message;
use App\Dialog;
use App\User;

/**
 * Класс устарел, функции связанные с сообщениями не используются.
 * TODO: Перенести оставшуюся функциональность в демона
 */
class AjaxChatController extends Controller
{
    /**
     * The user class instance
     *
     * @var object
     */
    protected $user;

    /**
     * The Websocket repository class instance
     *
     * @var object
     */
    protected $ws;

    /**
     * Count messages on the 1 page
     *
     * @var object
     */
    protected $messagesOnPage = 30;

    /**
     * Create a new controller instance.
     *
     * @param WebsocketRepository $ws
     * @return void
     */
    public function __construct(WebsocketRepository $ws)
    {
        $this->middleware('auth');
        $this->ws = $ws;
    }

    /**
     * Получить сообщения (последние штук 20-30)
     *
     * @param  int $dialog
     * @return object
     */
    public function getMessages(int $dialog)
    {
        return Message::where('dialog_id', $dialog)->latest()->limit($this->messagesOnPage)->get()->sortBy('id');
    }

    /**
     * Подготовка диалогов (последние штук 20-30)
     *
     * @param  int $id
     * @return array
     */
    public function getDialogsJson(int $id = 0)
    {

        $dialogs = $this->getDialogs();

        $dialogs = $this->addDataToDialogs($dialogs, $id);

        return $dialogs
            ? $dialogs->values()->all()
            : ['status' => 'fail'];
    }

    /**
     * Подготовка сообщений
     *
     * @param  int $id
     * @return array
     */
    public function getMessagesJson(int $id = 0)
    {

        $dialog = $this->getDialogId($id);
        $messages = $this->getMessages($dialog);

        $messages = $this->addAvaLinksToMessages($messages);

        // if last TO  ME then set the dialog to VIEWED
        $observer = Auth::user()->id;
        $dialog   = Dialog::find($dialog);
        $last = $messages->last();
        if ($last && $last->rcpt == $observer) {
            $dialog->viewed = 1;
            $dialog->save();
        }

        return $dialog
            ? $messages->values()->all()
            : ['status' => 'fail'];
    }

    /**
     * Выдать json с сообщениями и диалогами
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function getChat(int $id = 0)
    {
        return response()->json([
            'dialogs' => $this->getDialogsJson($id),
            //'messages' => $this->getMessagesJson($id),
        ]);
    }

    /**
     * Get the avatar for the user.
     *
     * @param int $id
     * @return string
     */
    private function getAvatar($id)
    {
        $urlav = $_SERVER['DOCUMENT_ROOT'].'/images/avatars/'.$id.'.gif';
        return (file_exists($urlav)) ? $id.'.gif?t='.filemtime($urlav) : 'noavatar.gif';
    }

    /**
     * Добвить данные к пачке сообщений
     *
     * @param object $messages
     * @return object
     */
    private function addAvaLinksToMessages($messages)
    {
        $observer = Auth::user()->id;

        $views = 0;

        foreach ($messages as &$message) {
            if(!$message->viewed && $message->rcpt == $observer){
                $message->viewed = 1;
                $message->save();

                Auth::user()->decrement('private');
                Auth::user()->save(); //todo: можно вынести за цикл для уменьшения кол-ва запросов.

                $views++;
            }

            $message->ava       = $this->getAvatar($message->from);
            $message->body      = nl2br(trim($message->body));
            $message->observer  = $observer;
        }

        if ($views) {
            $message = $messages->first();
            $params = [
                'action' => 'allIsViewed',
                'rcpt' => $message->rcpt == $observer ? $message->from : $message->rcpt,
                'from' => $message->rcpt == $observer ? $message->rcpt : $message->from,
            ];

            $this->ws->sendWsMessage($params);
        }

        return $messages;
    }

    /**
     * Добвить данные к пачке диалогов
     * todo: need optimization МОЖНО ОТДАВАТЬ ТОЛЬКО МЕССАГИ И 2 ЮЗЕРА
     * @param object $dialogs
     * @param int $current
     * @return object
     */
    private function addDataToDialogs($dialogs, $current)
    {
        $observer = Auth::user()->id;

        foreach ($dialogs as &$dialog) {
            $companion          = $dialog->getCompanion($observer);
            $user               = User::find($companion);

            $dialog->companion  = $companion;
            $dialog->ava        = $user->getAvatar();
            $dialog->firstname  = $user->firstname;
            $dialog->lastname   = $user->lastname;
            $dialog->online     = $user->isOnline();
            $dialog->current    = ($companion == $current);
            $dialog->observer   = $observer;
        }

        return $dialogs;
    }

    /**
     * Получить диалоги
     *
     * @return object
     */
    public function getDialogs()
    {
        $this->user = Auth::user();
        return Dialog::where('member1', $this->user->id)->orWhere('member2', $this->user->id)->latest('updated_at')->get();
    }

    /**
     * Adds (дозагрузка сообщений)
     *
     * @param  int $first
     * @param  int $id
     * @return array
     */
    public function getAdditionalMessagesJson(int $id = 0, int $first = 0)
    {

        $dialog = $this->getDialogId($id);

        if(!$first || !$dialog){ abort(404); }

        $messages = $this->getAdditionalMessages($first, $dialog);

        $messages = $this->addAvaLinksToMessages($messages);

        return response()->json([
            'messages' => $messages->values()->all(),
        ]);
    }

    /**
     * Получить доп сообщения (при прокрутке) (способ реализации: id < last && limit 30
     *
     * @param  int $firstMessage
     * @param  int $dialog
     * @return object
     */
    public function getAdditionalMessages(int $firstMessage, int $dialog)
    {
        return Message::where('dialog_id', $dialog)->where('id', '<', $firstMessage)->latest()->take($this->messagesOnPage)->get();
    }

    /**
     * Получить новые сообщения для данного диалога
     *
     * @param  int $lastMessage
     * @param  int $dialog
     * @return object
     */
    public function getNewMessagesForThisDialog(int $lastMessage, int $dialog)
    {
        return Message::where('dialog', $dialog)->where('id', '>', $lastMessage)->latest()->take($this->messagesOnPage)->get();
    }

    /**
     * Получить id диалога между данными двумя челиками
     * todo: relised
     * @param  int $id
     * @return int
     */
    public function getDialogId(int $id)
    {
        $this->user = Auth::user();
        $dialog = $this->user->id > $id
            ? Dialog::where('member1', $id)->where('member2', $this->user->id)->first()
            : Dialog::where('member1', $this->user->id)->where('member2', $id)->first();

        return $dialog
            ? $dialog->id
            : false;
    }

    /**
     * Удалить сообщение
     * todo: relised
     * @param  int $id
     * @return bool
     */
    public function deleteMessage(int $id)
    {
        if($this->user->getStatus() !== 'admin')
            return false;

        $message = Message::findOrFail($id);

        User::findOrFail($message['to'])->increment('mail');

        return (bool) Message::destroy($id);
    }

    /**
     * Получить id диалога между данными двумя челиками
     *
     * @param  int $id
     * @return int
     */
    public function addMessage(int $id)
    {
        return $this->user > $id
            ? Dialog::where('member1', $id)->where('member2', $this->user->id)->first()->id
            : Dialog::where('member1', $this->user->id)->where('member2', $id)->first()->id;
    }

    /**
     * Сделать сообщение просмотренным
     *
     * @param  int $message
     * @return int
     */
    public function makeViewed(int $message)
    {
        return Message::where('id', $message)->update(['viewed' => 1]);
    }
	
	/**
     * Получаем экземпляр валидатора сообщения
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
