<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Classiebit\Eventmie\Http\Controllers\BookingsController as BaseBookingsController;
use Classiebit\Eventmie\Models\Booking;
use Classiebit\Eventmie\Models\Transaction;
use Classiebit\Eventmie\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Eventmie\BookingsController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CallbackController extends Controller
{


    public function wave_callback(Request $request)
    {
        logger("Le callback de wave");
        logger($request->all());
    }

    public function om_senegal_callback(Request $request)
    {
        $data = $request->all();
        $flag   = [];

        $secret_key ='9F22E204D4E38BCDA44AC6F9701E2601AC7C7B5216D0E9373A02AA03ABC70E73';
        $bin_key = pack("H*", $secret_key);
        ksort($data);
        $message=$data['MESSAGE'];
        $hmac = strtoupper(hash_hmac(strtolower($data['ALGO']), $message, $bin_key));

        if ($hmac === $data['HMAC']){
            logger("Bingo! Valid Data");
            logger($data['HMAC']);

        }
        else{
            logger("Suspicious data");
            logger($hmac);
            logger($data['HMAC']);

            return [
                // only for reference
                'error'     => $data['statut'],
                'status'    => false
            ];

        }
    }


    public function om_senegal_return_url(Request $request)
    {
        logger("Le return url de om");
        $data = $request->all();

        $transaction = Transaction::where('order_number', $data['ref_commande'])->first();
        $book = Booking::where('order_number', $data['ref_commande'])->first();
        $user = User::where('id', $book->customer_id)->first();

        $transaction->update([
            'payment_status' => $data['statut'],
            'status' => 1,
            'txn_id' => $data['ref_transaction']
        ]);

// if customer then redirect to mybookings
        $url = route('eventmie.mybookings_index');
        if(Auth::user()->hasRole('organiser'))
            $url = route('eventmie.obookings_index');

        if(Auth::user()->hasRole('admin'))
            $url = route('voyager.bookings.index');


        // redirect no matter what so that it never turns back
        $msg = __('eventmie-pro::em.booking_success');
        session()->flash('status', $msg);

        return success_redirect($msg, $url);
    }
}
