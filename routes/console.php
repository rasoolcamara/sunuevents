<?php

use App\Models\User;
use App\Notifications\TestMailNotification;
use Classiebit\Eventmie\Notifications\ForgotPasswordNotification;
use Classiebit\Eventmie\Notifications\MailNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notif', function () {
    $user = User::find(203);
    logger('jkdsnfs');
    $token = 'zU7M0cDB2w67bOrHizv2SdCfFPkA1PoLDpLPJCZI';
    // return new TestMailNotification()->toMail($user);
    $user->notify(new TestMailNotification());
    logger('jkdsnfs');
})->purpose('Testing mailing notification');
