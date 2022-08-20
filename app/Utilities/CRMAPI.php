<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Http;

class CRMAPI {
    public function authCRM(String $phone, String $msg)
    {
        $url = 'https://crm.paydunya.com/api/v1/auth/login';

        $response = Http::withHeaders([
            'Content-Type'  =>'application/json',
            "Accept"        => "application/vnd.apisms.v1+json",
        ])->post($url, [
            "user_key"      => env('PAYDUNYA_USER_KEY'),
            "secret_key"    => env('PAYDUNYA_SECRET_KEY'),
        ]);

        $body = $response->json();


        if ($body['access_token'] != null) {
            $token = $body['access_token'];
            $result = $this->sendSMS($phone, $msg, $token);
            logger($result);

            if ($result == "00") {
                return true;
            }
        } else {
            return false;
        }
    }

    public function sendSMS(String $phone, String $msg, String $token) {

        $url = 'https://crm.paydunya.com/api/v1/sms/send';

        $response = Http::withHeaders([
            'Content-Type'      =>'application/json',
            "Accept"            => "application/vnd.apisms.v1+json",
            "Authorization"     => "Bearer ".$token

        ])->post($url, [
            "send_sms_request"  => [
                "type"      => "application",
                "app_key"   => env('PAYDUNYA_APP_KEY'),
                "sms"   => [
                    [
                        "from"    => env('PAYDUNYA_SMS_FROM'),
                        "to"      => $phone,
                        "text"    => $msg,
                    ]
                ]
            ]
        ]);

        $body = $response->json();

        logger("La reponse");
        logger($body);

        if ($body['code'] != null) {
            return $body['code'];
        } else {
            return $body;
        }
    }
}