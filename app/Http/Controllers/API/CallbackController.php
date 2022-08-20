<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Classiebit\Eventmie\Http\Controllers\BookingsController as BaseBookingsController;
use Classiebit\Eventmie\Models\Booking;
use Classiebit\Eventmie\Models\Transaction;
use Classiebit\Eventmie\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Eventmie\BookingsController;
use App\Models\TicketMessage;
use App\Utilities\CRMAPI;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class CallbackController extends BaseBookingsController
{


    public function wave_callback(Request $request)
    {
        logger("Le callback de wave");
        logger($request->all());
        $this->waveCallbackResponse($request);
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

        if($user->hasRole('organiser'))
            $url = route('eventmie.obookings_index');

        if($user->hasRole('admin'))
            $url = route('voyager.bookings.index');


        // redirect no matter what so that it never turns back
        $msg = __('eventmie-pro::em.booking_success');
        session()->flash('status', $msg);

        return success_redirect($msg, $url);
    }

    /**
     *  payment response
     */
    public function waveResponse(Request $request)
    {
        // IMPORTANT!!! clear session data setted during checkout process
        session()->forget(['pre_payment', 'booking', 'payment_method']);
        
        /* CUSTOM */  
        /* CUSTOM */  
		logger("Le CUSTOMERR");
        logger(Auth::user());
		logger("request");
        logger($request);
        
        // if customer then redirect to mybookings
        // $url = route('eventmie.mybookings_index');
        $url = route('eventmie.events_index');

        try
        {
           // redirect no matter what so that it never turns back
           $msg = __('eventmie-pro::em.booking_success');
           session()->flash('status', $msg);

           return success_redirect($msg, $url);
        } catch (\Throwable $th) {
            // fail case
            $flag = [
                'status'    => false,
                'error'     => $th->getMessage(),
            ];

            // if fail
            // redirect no matter what so that it never turns back
            $msg = __('eventmie-pro::em.payment').' '.__('eventmie-pro::em.failed');
            session()->flash('error', $msg);
            
                /* CUSTOM */
            return redirect($url)->withErrors([__('eventmie-pro::em.booking').' '.__('eventmie-pro::em.failed')]);
        }

        // return $this->finish_checkout($flag);
    }
	
	public function waveCallbackResponse(Request $request)
    {
        // IMPORTANT!!! clear session data setted during checkout process
        session()->forget(['pre_payment', 'booking', 'payment_method']);
        
        /* CUSTOM */  
        /* CUSTOM */  
		logger("Le CUSTOMERR");
        logger(Auth::user());
		logger("request");
        logger($request);
        
        // if customer then redirect to mybookings
        // $url = route('eventmie.mybookings_index');
        $url = route('eventmie.events_index');

        // if(Auth::user() && Auth::user()->hasRole('organiser'))
        //     $url = route('eventmie.obookings_index');
        
        // if(Auth::user() && Auth::user()->hasRole('admin'))
        //     $url = route('voyager.bookings.index');

        
        try
        {
            if($request['data']['payment_status'] == 'succeeded') 
            {
                $transaction = Transaction::where('txn_id', $request['data']['id'])->first();

                $transaction->update([
                    "payment_status"    => "payment_status",
                    "status"            => 1,
                ]);

                $booking = Booking::where("transaction_id", $transaction->id)->first();

                // redirect no matter what so that it never turns back
                $msg = __('eventmie-pro::em.booking_success');
                session()->flash('status', $msg);

                // $url = route('eventmie.downloads_index', [$booking->id, $booking->order_number]);

                // $message = "Bonjour ".$booking->full_name." \nVeuillez retrouver le ticket de votre réservation. NB: Ce ticket reste confidentiel jusqu'à votre accés à l'évenement.\n".$url;
                // (new CRMAPI())->authCRM($booking->phone, $message);

                /* MESSAGE */

                $ticketMsg = TicketMessage::where("booking_id", $booking->id)->where("phone", $booking->phone)->first();

                if (!$ticketMsg) {
                    $ticket_url = route('eventmie.downloads_index', [$booking->id, $booking->order_number]);
    
                    $message = "Bonjour ".$booking->full_name.", \nVeuillez retrouver le ticket de votre réservation.\n".$ticket_url;
                    (new CRMAPI())->authCRM($booking->phone, $message);

                    TicketMessage::create([
                        "booking_id"    => $booking->id,
                        "name"          => $booking->full_name,
                        "phone"         => $booking->phone,
                        "message"       => $message,
                    ]);
                }

                return success_redirect($msg, $url);
            }
            
        } catch (\Throwable $th) {
            // fail case
            $flag = [
                'status'    => false,
                'error'     => $th->getMessage(),
            ];

            // if fail
            // redirect no matter what so that it never turns back
            $msg = __('eventmie-pro::em.payment').' '.__('eventmie-pro::em.failed');
            session()->flash('error', $msg);
            
                /* CUSTOM */
            return redirect($url)->withErrors([__('eventmie-pro::em.booking').' '.__('eventmie-pro::em.failed')]);
        }

        // return $this->finish_checkout($flag);
    }
}
