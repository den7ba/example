<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\ResetPassword;
use Carbon\Carbon;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * Алиасы на уровни доступа(роли).
     *
     * @var array
     */
    public static $roles = [
        '1' => 'admin',
        '2' => 'system',
        '3' => 'moder',
        '4' => 'user',
        '5' => 'banned',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname', 'lastname', 'email', 'password', 'social', 'birthday', 'about', 'gender',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
	
    /**
     * Get the avatar for the user.
     *
     * @return string
     */
    public function getAvatar()
    {
        $urlav = public_path('/images/avatars/'.$this->id.'.gif');
        return (file_exists($urlav)) ? $this->id.'.gif?t='.filemtime($urlav) : 'noavatar.gif';
    }

    /**
     * Set the avatar for the user.
     *
     * @param string $file
     * @return void
     */
    public function setAvatar($file)
    {
        $urlav = public_path('/images/avatars/'.$this->id.'.gif');
        move_uploaded_file($file, $urlav);
    }
	
	/**
     * Возвращает статус пользователя
     *
     * @return string
     */
    public function getStatus()
    {
        return self::$roles[$this->access];
    }

    /**
     * Возвращает последнюю активность пользователя
     *
     * @return string
     */
    public function getLastActivity()
    {
        $lastAct = $this->updated_at;

        $now = Carbon::now();

        return str_replace('после', 'назад', $now->diffForHumans($lastAct));
    }
	
	/**
     * Возвращает пол пользователя
     *
     * @return string пол пользователя
     */
    public function getGender()
    {
		$gender = [
			'1' => 'Не определился',
			'2' => 'М',
			'3' => 'Ж',
		];
        return $this->gender ? $gender[$this->gender] : $gender['1'];
    }

    /**
     * Получить все видосы пользователя.
     */
    public function videos()
    {
        return $this->hasMany(Video::class);
    }
	
	/**
	* Получить информацию о привязанных соцсетях.
	*/
	public function socials()
	{
		return $this->hasOne(Social::class);
	}

    /**
     * Получить smart настройку пользователя.
     *
     * @param string $setting
     * @return string|false
     */
    public function getSmartSetting($setting)
    {
        $ss = $this->getSmartSettings();

        return $ss[$setting] ?? false;
    }

    /**
     * Получить smart настройки пользователя.
     *
     * @return array
     */
    public function getSmartSettings()
    {
        return $this->smart_settings
            ? json_decode($this->smart_settings, true)
            : [];
    }

    /**
     * Сохранить smart настройку/ки пользователя.
     *
     * @param array $settings
     * @return User
     */
    public function saveSmartSettings($settings)
    {
        $this->smart_settings = json_encode(array_merge($this->getSmartSettings(), $settings));
        $this->save();
        return $this;
    }

    /**
     * Онлайн ли пользователь
     *
     * @param int $time
     * @return string|false
     */
    public function isOnline($time = 120)
    {
        return (Carbon::now()->diffInSeconds($this->updated_at) > $time) ? false : true;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }
}
