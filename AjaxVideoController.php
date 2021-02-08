<?php

namespace App\Http\Controllers\Ajax;

use App\Video;
use App\Http\Controllers\Videos\DeleteVideo;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class AjaxVideoController extends Controller
{

    use DeleteVideo;
    /**
     * Allowable symbols for name of video
     *
     * @var string
     */
    private const CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Проверка и загрузка видеофайла
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     * @throws GuzzleException
     */
    public function upload(Request $request)
    {
        $extended = (Auth::check() && Auth::user()->access < 3);

        $minDuration = 4200;

        $data = $this->validator($request->all(), $extended)->validate();

        $data['name'] = $data['name'] ?? $this->generateName();
        $data['user_id'] = Auth::check() ? Auth::id() : 2 ;

        $path = public_path('/videos/'.$data['name']);
        $newFile = public_path('/videos/'.$data['name'].'/main.mp4');

        //todo: адекватный ответ если такая папка есть.
        //todo: удаление всего что в папке если что-то пошло не так.
        mkdir($path);
        if (@move_uploaded_file($data['videofile'], $newFile)) {
            $data['duration'] = FFMpeg::fromDisk('videos')->open($data['name'].'/main.mp4')->getDurationInMiliseconds();

            $data['duration'] = empty($data['duration']) ? 0 : $data['duration'];

            if ($data['duration'] < $minDuration) {
                unlink("{$newFile}");
                rmdir("{$path}");

                return response()->json([
                    'status' => 'fail',
                    'reason' => "Must be at least {$minDuration} msec",
                    'duration' => $data['duration']
                ]);
            }

            $this->create($data);
            //todo: откатить изменения в базе при ошибке загрузки
            $this->videoConverting($data);
        }

        return response()->json([
            'status' => 'success',
            'name'   => $data['name'],
        ]);
    }

    /**
     * Converting mp4 to MPEG-DASH format
     *
     * @param array $data
     * @return bool
     * @throws GuzzleException
     */
    protected function videoConverting(array $data)
    {
        $client = new Client();
        $client->post('http://192.168.0.101:1336/', [
            'json' => [
                'action' => 'separate',
                'params' => json_encode([
                    'name' => $data['name'],
                    'from' => (int) $data['user_id'],
                    'input' => public_path('videos/'.$data['name']),
                    'output' => public_path('videos/'.$data['name']),
                ]),
            ]
        ]);

        return true;
    }

    /**
     * Delete the video
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function delVideo(Request $request)
    {
        $uuid = $request->has('name')
            ? $request->name
            : '' ;

        //todo: проверка на принадлежность видоса удаляющему пользователю

        $this->deleteVideo($uuid);

        return response()->json([
            'status' => 'success',
        ]);
    }

    /**
     * Get video parameters: duration/height/width etc
     *
     * @param  string  $path
     * @return array
     */
    protected function getVideoParams(string $path)
    {
        /*
         *
         */
        return array($path);
    }

    /**
     * Create a new video instance after validation.
     *
     * @param  array  $data
     * @return Video
     */
    protected function create(array $data)
    {
        return Video::create([
            'name'         => $data['name'],
            'user_id'      => $data['user_id'],
            'title'        => $data['title'],
            'description'  => $data['description'],
            'duration'     => $data['duration'],
        ]);
    }

    /**
     * Валидация данных
     *
     * @param  array  $data
     * @param bool $extended
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data, bool $extended = false)
    {
        $rules = [
            'title' => 'required|regex:/^[а-яА-ЯёЁa-zA-Z0-9 .,%]+$/u|string|min:2|max:35',
            'description' => 'regex:/^[а-яА-ЯёЁa-zA-Z0-9 .,%]+$/u|string|nullable|max:500',
            'videofile' => 'required|mimes:mp4,mp4v,mpg4,h264|file',
        ];

        if ($extended) {
            $rules['name'] = 'required|regex:/^[а-яА-ЯёЁa-zA-Z]+$/u|string|min:2|max:35';
        }

        return Validator::make($data, $rules);
    }

    /**
     * Generate name for new video
     *
     * @param int $length
     * @return string
     */
    protected function generateName(int $length = 11): string
    {
        $uid = '';
        while ($length-- > 0) {
            $uid .= self::CHARS[mt_rand(0, 63)];
        }

        return $uid;
    }
}
