<?php

namespace App\Http\Controllers\Eventmie;

use Classiebit\Eventmie\Http\Controllers\BookingsController as BaseBookingsController;
use Classiebit\Eventmie\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Auth;
use Classiebit\Eventmie\Models\Booking;
use Classiebit\Eventmie\Models\Event;
use Illuminate\Support\Facades\Http;
use Paydunya\Setup;
use Paydunya\Checkout\Store;
use Throwable;

class BookingsController extends BaseBookingsController
{
    public function __construct()
    {
        parent::__construct();
        $this->paydunyaConfigration();
    }

    // book tickets
    public function book_tickets(Request $request)
    {
        // check login user role
        $status = $this->is_admin_organiser($request);

        // organiser can't book other organiser event's tikcets but  admin can book any organiser events'tikcets for customer
        if(!$status) {
            return response([
                'status'    => false,
                'url'       => route('eventmie.events_index'),
                'message'   => __('eventmie-pro::em.organiser_note_5'),
            ], Response::HTTP_OK);
        }

        // 1. General validation and get selected ticket and event id
        $data = $this->general_validation($request);
        if(!$data['status'])
            return error($data['error'], Response::HTTP_BAD_REQUEST);

        // 2. Check availability
        $check_availability = $this->availability_validation($data);
        if(!$check_availability['status'])
            return error($check_availability['error'], Response::HTTP_BAD_REQUEST);

        // 3. TIMING & DATE CHECK 
        $pre_time_booking   =  $this->time_validation($data);
        if(!$pre_time_booking['status'])
            return error($pre_time_booking['error'], Response::HTTP_BAD_REQUEST);

        $selected_tickets   = $data['selected_tickets'];
        $tickets            = $data['tickets'];


        $booking_date = $request->booking_date;

        $params  = [
            'customer_id' => $this->customer_id,
        ];
        // get customer information by customer id    
        $customer   = $this->user->get_customer($params);

        if(empty($customer))
            return error($pre_time_booking['error'], Response::HTTP_BAD_REQUEST);

        $booking        = [];
        $price          = 0;
        $total_price    = 0;

        // organiser_price excluding admin_tax
        $booking_organiser_price    = [];
        $admin_tax                  = [];
        foreach($selected_tickets as $key => $value) {
            $booking[$key]['customer_id']       = $this->customer_id;
            $booking[$key]['customer_name']     = $customer['name'];
            $booking[$key]['customer_email']    = $customer['email'];
            $booking[$key]['organiser_id']      = $this->organiser_id;
            $booking[$key]['event_id']          = $request->event_id;
            $booking[$key]['ticket_id']         = $value['ticket_id'];
            $booking[$key]['quantity']          = $value['quantity'];
            $booking[$key]['status']            = 1;
            $booking[$key]['created_at']        = Carbon::now();
            $booking[$key]['updated_at']        = Carbon::now();
            $booking[$key]['event_title']       = $data['event']['title'];
            $booking[$key]['event_category']    = $data['event']['category_name'];
            $booking[$key]['ticket_title']      = $value['ticket_title'];
            $booking[$key]['item_sku']          = $data['event']['item_sku'];
            $booking[$key]['currency']          = setting('regional.currency_default');

            $booking[$key]['event_repetitive']  = $data['event']->repetitive > 0 ? 1 : 0;

            // non-repetitive
            $booking[$key]['event_start_date']  = $data['event']->start_date;
            $booking[$key]['event_end_date']    = $data['event']->end_date;
            $booking[$key]['event_start_time']  = $data['event']->start_time;
            $booking[$key]['event_end_time']    = $data['event']->end_time;

            // repetitive event
            if($data['event']->repetitive)
            {
                $booking[$key]['event_start_date']  = $booking_date;
                $booking[$key]['event_end_date']    = $request->merge_schedule ? $request->booking_end_date : $booking_date;
                $booking[$key]['event_start_time']  = $request->start_time;
                $booking[$key]['event_end_time']    = $request->end_time;
            }

            foreach($tickets as $k => $v)
            {
                if($v['id'] == $value['ticket_id'])
                {
                    $price       = $v['price'];
                    break;
                }
            }
            $booking[$key]['price']         = $price * $value['quantity'];
            $booking[$key]['ticket_price']  = $price;

            // call calculate price
            $params   = [
                'ticket_id'         => $value['ticket_id'],
                'quantity'          => $value['quantity'],
            ];

            // calculating net price
            $net_price    = $this->calculate_price($params);


            $booking[$key]['tax']        = number_format((float)($net_price['tax']), 2, '.', '');
            $booking[$key]['net_price']  = number_format((float)($net_price['net_price']), 2, '.', '');

            // organiser price excluding admin_tax
            $booking_organiser_price[$key]['organiser_price']  = number_format((float)($net_price['organiser_price']), 2, '.', '');

            //  admin_tax
            $admin_tax[$key]['admin_tax']  = number_format((float)($net_price['admin_tax']), 2, '.', '');


            // if payment method is offline then is_paid will be 0
            if($request->payment_method == 'offline')
            {
                // except free ticket
                if(((int) $booking[$key]['net_price']))
                    $booking[$key]['is_paid'] = 0;
            }
            else
            {
                $booking[$key]['is_paid'] = 1;
            }

        }

        // calculate commission 
        $this->calculate_commission($booking, $booking_organiser_price, $admin_tax);

        // if net price total == 0 then no paypal process only insert data into booking 
        foreach($booking as $k => $v)
        {
            $total_price  += (float)$v['net_price'];
            $total_price = number_format((float)($total_price), 2, '.', '');
        }

        // check if eligible for direct checkout
        $is_direct_checkout = $this->checkDirectCheckout($request, $total_price);

        // IF FREE EVENT THEN ONLY INSERT DATA INTO BOOKING TABLE 
        // AND DON'T INSERT DATA INTO TRANSACTION TABLE 
        // AND DON'T CALLING PAYPAL API
        if($is_direct_checkout) {
            $data = [
                'order_number' => time().rand(1,988),
                'transaction_id' => 0
            ];
            $flag =  $this->finish_booking($booking, $data);

            // in case of database failure
            if(empty($flag))
            {
                return error('Database failure!', Response::HTTP_REQUEST_TIMEOUT);
            }

            // redirect no matter what so that it never turns backreturn response
            $msg = __('eventmie-pro::em.booking_success');
            session()->flash('status', $msg);

            // if customer then redirect to mybookings
            $url = route('eventmie.mybookings_index');

            if(Auth::user()->hasRole('organiser'))
                $url = route('eventmie.obookings_index');

            if(Auth::user()->hasRole('admin'))
                $url = route('voyager.bookings.index');

            return response([
                'status'    => true,
                'url'       => $url,
                'message'   => $msg,
            ], Response::HTTP_OK);
        }

        // return to paypal
        session(['booking'=>$booking]);

        /* CUSTOM */
        $this->set_payment_method($request, $booking);
        /* CUSTOM */

        return $this->init_checkout($booking);
    }

    /**
     * Initialize checkout process
     * 1. Validate data and start checkout process
     */
    protected function init_checkout($booking)
    {
        // add all info into session
        $order = [
            'item_sku'          => $booking[key($booking)]['item_sku'],
            'order_number'      => time().rand(1,988),
            'product_title'     => $booking[key($booking)]['event_title'],
            'price_title'       => '',
            'price_tagline'     => '',
        ];

        $total_price   = 0;

        foreach($booking as $key => $val)
        {
            $order['price_title']   .= ' | '.$val['ticket_title'].' | ';
            $order['price_tagline'] .= ' | '.$val['quantity'].' | ';
            // $order['quantity']       = $val['quantity'];

            $total_price            += $val['net_price'];
        }

        // calculate total price
        $order['price']             = $total_price;

        // set session data
        session(['pre_payment' => $order]);

        //CUSTOM
        return $this->multiple_payment_method($order);
        //CUSTOM

        // return $this->paypal($order, setting('regional.currency_default'));
    }

    /**
     * 4 Finish checkout process
     * Last: Add data to purchases table and finish checkout
     */
    protected function finish_checkout($flag = [])
    {
        // prepare data to insert into table
        $data  = session('pre_payment');
        // unset extra columns
        unset($data['product_title']);
        unset($data['price_title']);
        unset($data['price_tagline']);


        $booking                = session('booking');

        // IMPORTANT!!! clear session data setted during checkout process
        // session()->forget(['pre_payment', 'booking']);


        /* CUSTOM */
        $payment_method         = (int)session('payment_method')['payment_method'];

        // IMPORTANT!!! clear session data setted during checkout process
        session()->forget(['pre_payment', 'booking', 'payment_method']);


        /* CUSTOM */

        // if customer then redirect to mybookings
        $url = route('eventmie.mybookings_index');
        if(Auth::user()->hasRole('organiser'))
            $url = route('eventmie.obookings_index');

        if(Auth::user()->hasRole('admin'))
            $url = route('voyager.bookings.index');

        ///
        /// if success 

        if($flag['status'])
        {
            $data['txn_id']             = $flag['transaction_id'];
            $data['amount_paid']        = $data['price'];
            unset($data['price']);
            $data['payment_status']     = $flag['message'];
            $data['payer_reference']    = $flag['payer_reference'];
            $data['status']             = 1;
            $data['created_at']         = Carbon::now();
            $data['updated_at']         = Carbon::now();
            $data['currency_code']      = setting('regional.currency_default');
            $data['payment_gateway']    = 'paypal';
            /* CUSTOM */
            $data['payment_gateway']    =  $payment_method == 2 ? 'Paydunia' : 'PayPal';
            /* CUSTOM */            // insert data of paypal transaction_id into transaction table

            // insert data of paypal transaction_id into transaction table
            $flag                       = $this->transaction->add_transaction($data);

            $data['transaction_id']     = $flag; // transaction Id

            $flag = $this->finish_booking($booking, $data);

            // in case of database failure
            if(empty($flag))
            {

                $msg = __('eventmie-pro::em.booking').' '.__('eventmie-pro::em.failed');
                session()->flash('status', $msg);

                // return error_redirect($msg);
                /* CUSTOM */
                return redirect($url)->withErrors([__('eventmie-pro::em.booking').' '.__('eventmie-pro::em.failed')]);
                /* CUSTOM */
            }

            // redirect no matter what so that it never turns back
            $msg = __('eventmie-pro::em.booking_success');
            session()->flash('status', $msg);

            return success_redirect($msg, $url);
        }

        // if fail
        // redirect no matter what so that it never turns back
        $msg = __('eventmie-pro::em.payment').' '.__('eventmie-pro::em.failed');
        session()->flash('error', $msg);

        /* CUSTOM */
        return redirect($url)->withErrors([__('eventmie-pro::em.booking').' '.__('eventmie-pro::em.failed')]);
        /* CUSTOM */
        // return error_redirect($msg);
    }

    /**
     * configration
     */

    public function paydunyaConfigration()
    {
        \Paydunya\Setup::setMasterKey(env('PAYDUNYA_MASTER_KEY'));
        \Paydunya\Setup::setPublicKey(env('PAYDUNYA_PUBLIC_KEY'));
        \Paydunya\Setup::setPrivateKey(env('PAYDUNYA_PRIVATE_KEY'));
        \Paydunya\Setup::setToken(env('PAYDUNYA_TOKEN'));
        \Paydunya\Setup::setMode(env('PAYDUNYA_MODE')); // Optional. Use this option for test payments.

        //Configuring your service/company information
        \Paydunya\Checkout\Store::setName(env('PAYDUNYA_COMPANY_NAME')); // Only the name is required
        \Paydunya\Checkout\Store::setTagline(env('PAYDUNYA_COMPANY_TAGLINE'));
        \Paydunya\Checkout\Store::setWebsiteUrl(env('PAYDUNYA_COMPANY_WETSITE_URL'));
        \Paydunya\Checkout\Store::setLogoUrl(env('PAYDUNYA_COMPANY_LOGO_URL'));
        // \Paydunya\Checkout\Store::setPhoneNumber("336530583");
        // \Paydunya\Checkout\Store::setPostalAddress("Dakar Plateau - Establishment kheweul");

        \Paydunya\Checkout\Store::setCancelUrl(route('paydunyaResponse'));
        \Paydunya\Checkout\Store::setCallbackUrl(route('paydunyaResponse'));
        \Paydunya\Checkout\Store::setReturnUrl(route('paydunyaResponse'));
    }

    /**
     *  payment request
     */
    public function paydunyaRequest($order = [], $currency = 'FCFA')
    {
        $event_title    = session('payment_method')['event_title'];
        try
        {
            $invoice = new \Paydunya\Checkout\CheckoutInvoice();
            $order['price'] =200;
            $invoice->addItem($event_title, 1, $order['price'], $order['price']);
            $invoice->setDescription($event_title);
            $invoice->setTotalAmount($order['price']);
            $invoice->create();

            if(empty($invoice->getInvoiceUrl()))
                return response()->json(['status' => false, 'message' => $invoice->response_text]);

            return response()->json(['status' => true, 'url' => $invoice->getInvoiceUrl()]);
        }
        catch(\Throwable $th)
        {
            return response()->json(['status' => false, 'message' => $th->getMessage()]);
        }
    }

    /**
     *  payment response
     */
    public function paydunyaResponse(Request $request)
    {
        $token = $request->token;

        $flag   = [];

        try
        {
            $invoice = new \Paydunya\Checkout\CheckoutInvoice();

            if($invoice->confirm($token))
            {
                $flag['status']             = true;
                $flag['transaction_id']     = $invoice->transaction_id; // transation_id
                $flag['payer_reference']    = $invoice->getCustomerInfo('email');
                $flag['message']            = $invoice->getStatus(); // outcome message
            }
            else
            {
                $flag['status']             = false;
                $flag['error']              = $invoice->getStatus();
            }
        }

            // All Exception Handling like error card number
        catch (\Throwable $th)
        {
            // fail case
            $flag = [
                'status'    => false,
                'error'     => $th->getMessage(),
            ];
        }

        return $this->finish_checkout($flag);
    }

    /*====================== Payment Method Store In Session =======================*/

    protected function set_payment_method(Request $request, $booking = [])
    {
        $payment_method = [
            'payment_method' => $request->payment_method,
            'customer_email' => $booking[key($booking)]['customer_email'],
            'customer_name'  => $booking[key($booking)]['customer_name'],
            'event_title'    => $booking[key($booking)]['event_title'],
        ];

        session(['payment_method' => $payment_method]);
    }

    /*===========================multiple payment method ===============*/

    protected function multiple_payment_method($order = [])
    {
        $url = route('eventmie.events_index');
        $msg = __('eventmie-pro::em.booking').' '.__('eventmie-pro::em.failed');

        $payment_method = (int)session('payment_method')['payment_method'];

        if($payment_method == 2)
        {
            if(empty(env('PAYDUNYA_MASTER_KEY')) || empty(env('PAYDUNYA_PUBLIC_KEY')) || empty(env('PAYDUNYA_PRIVATE_KEY')))
                return response()->json(['status' => false, 'url'=>$url, 'message'=>$msg]);

            return $this->paydunyaRequest($order, setting('regional.currency_default'));

        }
        else if($payment_method == 3)
        {
            return $this->waveRequest($order, setting('regional.currency_default'));

        }
        else if($payment_method == 4)
        {
            return $this->omsenegalRequest($order, setting('regional.currency_default'));
        }
        else
        {
            if(empty(setting('apps.paypal_secret')) || empty(setting('apps.paypal_client_id')))
                return response()->json(['status' => false, 'url'=>$url, 'message'=>$msg]);

            return $this->paypal($order, setting('regional.currency_default'));
        }
    }

    /**
     *  payment request
     */
    public function waveRequest($order = [], $currency = 'XOF')
    {
        // $event_title    = session('payment_method')['event_title'];
        try
        {
            $url = 'https://api.wave.com/v1/checkout/sessions';

            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer wave_sn_prod_a0NVjFwZbobr_t5aPnfgR76tOv22ApBAiMo5U9D7T27SOgHWBd0munUrFsw-0bENBdAqOzfdTsu2TSRVakhzI0O_Bt0HVbIOjQ',
                // 'Authorization' => 'Bearer wave_sn_prod_CN1R1ZFFStIJR3QuvKa1X1mYEqOKCKV2Gr1oPSXYSe3RUyebTvbrtjJcerYaARuu0XXObFs3jO-sQyWJiAvWN-8rIL369n4enw',
                // 'Authorization' => 'Bearer wave_sn_test_KDR7FXgVJjCFd7LSecKSerLhWiwTKpwDK2Oz03F9NNf-jqk6otb56FZfWccO4KisektIx-7JyyO1E5iCrBKKCMaSZB2H8pYx8w',
                // 'Authorization' => 'Bearer wave_sn_prod_1Wd7rR4b1XD99dItFYf3lyV0VRzEQWjPxOTJAz7CIg8k_BUUqzaqkFxVv_AGWHAFnoow_KnQ6YFxeW3PMAUKx2RthBnuLnRESg',
            ])->post($url, [
                "amount"        => 200,// $order['price'],
                "currency"      => "XOF",
                "error_url"     => "https://sunuevents.sn/wave-return-url",
                "success_url"   => "https://sunuevents.sn/wave-return-url"
            ]);

            $body = $response->json();

            $response = json_decode($response->getBody()->getContents());

            if(isset($body['code'])) {
                return response()->json(['status' => false, 'message' => $body['message']]);
            }

            // prepare data to insert into table
            $data  = session('pre_payment');
            // unset extra columns
            unset($data['product_title']);
            unset($data['price_title']);
            unset($data['price_tagline']);

            $booking = session('booking');
            logger("body & flag");
            
            // IMPORTANT!!! clear session data setted during checkout process
            // session()->forget(['pre_payment', 'booking']);
            
            /* CUSTOM */
            $payment_method         = (int)session('payment_method')['payment_method'];

            // IMPORTANT!!! clear session data setted during checkout process
            session()->forget(['pre_payment', 'booking', 'payment_method']);

            $flag   = [];
        
            $flag['status']             = true;
            $flag['transaction_id']     = $body['id'];
            $flag['payer_reference']    = $body['business_name'];               
            $flag['message']            = $body['checkout_status'];

            ///
            $data['txn_id']             = $flag['transaction_id'];
            $data['amount_paid']        = $data['price'];
            unset($data['price']);
            $data['payment_status']     = $flag['message'];
            $data['payer_reference']    = $flag['payer_reference'];
            $data['status']             = 0;
            $data['created_at']         = Carbon::now();
            $data['updated_at']         = Carbon::now();
            $data['currency_code']      = setting('regional.currency_default');
            $data['payment_gateway']    = 'WAVE';
            // insert data of paypaltra nsaction_id into transaction table
            logger("body & flag");
            logger($data);
            $flag                       = $this->transaction->add_transaction($data);
            $data['transaction_id']     = $flag;
            $flag = $this->finish_booking($booking, $data);

            ///

            return response()->json(['status' => true, 'url' => $body['wave_launch_url']]);
        } 
        catch(\Throwable $th)
        {
            logger($th);
            logger("th");

            return response()->json(['status' => false, 'message' => $th->getMessage()]);
        }
    }

    /**
     *  payment request
     */
    public function omsenegalRequest($order = [], $currency = 'FCFA')
    {
        try {

            // prepare data to insert into table
            $data                   = session('pre_payment');

            // unset extra columns
            unset($data['product_title']);
            unset($data['price_title']);
            unset($data['price_tagline']);
            unset($data['quantity']);

            $booking                = session('booking');

            // IMPORTANT!!! clear session data setted during checkout process
            // session()->forget(['pre_payment', 'booking']);


            /* CUSTOM */
            $payment_method         = (int)session('payment_method')['payment_method'];

            // IMPORTANT!!! clear session data setted during checkout process
            //session()->forget(['pre_payment', 'booking', 'payment_method']);
            $data['txn_id']             = null;
            $data['amount_paid']        = $data['price'];
            unset($data['price']);
            $data['payment_status']     = 'pending';
            $data['payer_reference']    = null;
            $data['status']             = 0;
            $data['created_at']         = Carbon::now();
            $data['updated_at']         = Carbon::now();
            $data['currency_code']      = setting('regional.currency_default');
            $data['payment_gateway']    = 'Orange Money';

            // insert data of paypal transaction_id into transaction table

            $flag                       = $this->transaction->add_transaction($data);

            $data['transaction_id']     = $flag; // transaction Id

            $flag = $this->finish_booking($booking, $data);

            $om_config = [
                'identifiant' => md5(env('ORANGE_IDENTIFIER')),
                'site' => md5(env('ORANGE_SITE')),
                'dateh' => date('c'),
                'algo' => 'SHA512',
                'ref_commande' => $order['order_number'],
                'total' => intval($order['price']),
                'commande' => $order['product_title'],
            ];

            $message = "S2M_COMMANDE=" . $om_config['commande'] . "&S2M_DATEH=" . $om_config['dateh'] . "&S2M_HTYPE=" . $om_config['algo'] . "&S2M_IDENTIFIANT=" . $om_config['identifiant'] . "&S2M_REF_COMMANDE=" . $om_config['ref_commande'] . "&S2M_SITE=" . $om_config['site'] . "&S2M_TOTAL=" . $om_config['total'];

            $cle_secrete = env('ORANGE_SECRET_KEY');
            $cle_bin = pack("H*", $cle_secrete);
            $hmac = strtoupper(hash_hmac(strtolower($om_config['algo']), $message, $cle_bin));
            $om_config['hmac'] = $hmac;

            logger($om_config);

            return response()->json([
                'status' => true,
                'om_config' => $om_config
            ]);

        } catch(\Throwable $th) {
            return response()->json(['status' => false, 'message' => $th->getMessage()]);
        }
    }
}
