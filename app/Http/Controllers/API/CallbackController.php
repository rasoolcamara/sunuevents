<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Classiebit\Eventmie\Models\Booking;
use Classiebit\Eventmie\Models\Transaction;
use Illuminate\Http\Request;
use Classiebit\Eventmie\Http\Controllers\BookingsController as BaseBookingsController;
use Auth;


class CallbackController extends BaseBookingsController
{
    public function wave_callback(Request $request)
    {
        logger("Le callback de wave");
        logger($request->all());
    }

    public function om_senegal_callback(Request $request)
    {
        logger("Le callback de om_senegal");
        logger($request->all());
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
        $url = route('eventmie.mybookings_index');
        if(Auth::user()->hasRole('organiser'))
            $url = route('eventmie.obookings_index');
        
        if(Auth::user()->hasRole('admin'))
            $url = route('voyager.bookings.index');

        try
        {
            if($request['data']['payment_status'] == 'succeeded') 
            {
                $transaction = Transaction::where('txn_idtxn_id', $request['data']['id'])->first();

                $transaction->update([
                    "payment_status"    => "payment_status",
                    "status"            => 1,
                ]);

                $booking = Booking::where("transaction_id", $transaction->id)->first();

                // redirect no matter what so that it never turns back
                $msg = __('eventmie-pro::em.booking_success');
                session()->flash('status', $msg);
                
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
