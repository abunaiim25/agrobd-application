<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Library\SslCommerz\SslCommerzNotification;
use App\Models\Business\MyBusiness;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderitemPayment;
use App\Models\OrderPayment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerBuyer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SslCommerzPaymentController extends Controller
{

    public function exampleEasyCheckout()
    {
        return view('frontend.payment.exampleEasycheckout');
    }

    public function exampleHostedCheckout()
    {
        return view('frontend.payment.exampleHosted');
    }

    public function businessPaymentForm(Request $request, $id)
    {
        $business = MyBusiness::findOrFail($id);
        $quantity = (int) $request->query('quantity', 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        if ($quantity > $business->product_quantity) {
            $quantity = $business->product_quantity;
        }
        return view('frontend.payment.business_payment', compact('business', 'quantity'));
    }

    public function payBusiness(Request $request)
    {
        $request->validate([
            'business_id' => 'required|integer|exists:my_businesses,id',
            'quantity' => 'required|integer|min:1',
            'phone' => 'required|string',
            'address' => 'required|string',
            'state' => 'required|string',
            'post_code' => 'required|integer',
        ]);

        $business = MyBusiness::findOrFail($request->business_id);

        if ($request->quantity > $business->product_quantity) {
            return redirect()->back()->with('error', 'Requested quantity exceeds available stock.');
        }

        $amount = $business->price * $request->quantity;

        // Get payment gateway, default to sslcommerz if not set
        $gateway = $business->payment_gateway ?? 'sslcommerz';

        // Route to correct payment gateway
        if ($gateway === 'sslcommerz') {
            return $this->payBusinessSSLCommerz($request, $business, $amount);
        } elseif ($gateway === 'bkash') {
            if (!$business->bkash_number) {
                return redirect()->back()->with('error', 'Seller has not configured bKash number. Please ask seller to update their business profile.');
            }
            return $this->payBusinessBkash($request, $business, $amount);
        } elseif ($gateway === 'bank') {
            if (!$business->bank_account) {
                return redirect()->back()->with('error', 'Seller has not configured bank details. Please ask seller to update their business profile.');
            }
            return $this->payBusinessBank($request, $business, $amount);
        } else {
            return redirect()->back()->with('error', 'Invalid payment gateway configured.');
        }
    }

    public function payBusinessSSLCommerz(Request $request, MyBusiness $business, $amount)
    {
        if (!$business->store_id || !$business->store_password) {
            return view('frontend.payment.payment_not_configured');
        }

        $post_data = [];
        $post_data['total_amount'] = $amount;
        $post_data['currency'] = "BDT";
        $post_data['tran_id'] = uniqid();

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $request->name ?? Auth::user()->name;
        $post_data['cus_email'] = $request->email ?? Auth::user()->email;
        $post_data['cus_add1'] = $request->address;
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = "";
        $post_data['cus_state'] = $request->state;
        $post_data['cus_postcode'] = $request->post_code;
        $post_data['cus_country'] = "Bangladesh";
        $post_data['cus_phone'] = $request->phone;
        $post_data['cus_fax'] = "";
        $post_data['description'] = $request->description ?? "Payment to seller {$business->name} for {$business->product_name} ({$request->quantity} kg)";

        # SHIPMENT INFORMATION
        $post_data['ship_name'] = $business->name;
        $post_data['ship_add1'] = $business->village;
        $post_data['ship_add2'] = $business->road;
        $post_data['ship_city'] = $business->district;
        $post_data['ship_state'] = $business->police_station;
        $post_data['ship_postcode'] = $business->post_code;
        $post_data['ship_phone'] = $business->phone;
        $post_data['ship_country'] = $business->country;

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = $business->product_name;
        $post_data['product_category'] = $business->category;
        $post_data['product_profile'] = "physical-goods";

        # OPTIONAL PARAMETERS
        $post_data['value_a'] = "seller_id:{$business->user_id}";
        $post_data['value_b'] = "business_id:{$business->id}";
        $post_data['value_c'] = "quantity:{$request->quantity}";
        $post_data['value_d'] = "payment_type:business_seller";

        $payment_record = DB::table('payments')->updateOrInsert(
            ['transaction_id' => $post_data['tran_id']],
            [
                'user_id' => Auth::id(),
                'name' => $post_data['cus_name'],
                'email' => $post_data['cus_email'],
                'phone' => $post_data['cus_phone'],
                'amount' => $post_data['total_amount'],
                'status' => 'Pending',
                'address' => $post_data['cus_add1'],
                'state' => $post_data['cus_state'],
                'post_code' => $post_data['cus_postcode'],
                'transaction_id' => $post_data['tran_id'],
                'currency' => $post_data['currency'],
                'description' => $post_data['description'],
                'business_id' => $business->id,
                'seller_id' => $business->user_id,
                'quantity' => $request->quantity,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );

        $sslc = new SslCommerzNotification($business->store_id, $business->store_password);
        $payment_options = $sslc->makePayment($post_data, 'hosted');

        if (!is_array($payment_options)) {
            print_r($payment_options);
            $payment_options = [];
        }
    }

    public function payBusinessBkash(Request $request, MyBusiness $business, $amount)
    {
        $tran_id = uniqid();

        $payment_record = DB::table('payments')->insert([
            'user_id' => Auth::id(),
            'name' => $request->name ?? Auth::user()->name,
            'email' => $request->email ?? Auth::user()->email,
            'phone' => $request->phone,
            'amount' => $amount,
            'status' => 'Pending',
            'address' => $request->address,
            'state' => $request->state,
            'post_code' => $request->post_code,
            'transaction_id' => $tran_id,
            'currency' => 'BDT',
            'description' => "bKash payment to seller {$business->name} for {$business->product_name} ({$request->quantity} kg)",
            'business_id' => $business->id,
            'seller_id' => $business->user_id,
            'quantity' => $request->quantity,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return view('frontend.payment.bkash_instruction', [
            'business' => $business,
            'amount' => $amount,
            'tran_id' => $tran_id,
            'quantity' => $request->quantity
        ]);
    }

    public function payBusinessBank(Request $request, MyBusiness $business, $amount)
    {
        $tran_id = uniqid();

        $payment_record = DB::table('payments')->insert([
            'user_id' => Auth::id(),
            'name' => $request->name ?? Auth::user()->name,
            'email' => $request->email ?? Auth::user()->email,
            'phone' => $request->phone,
            'amount' => $amount,
            'status' => 'Pending',
            'address' => $request->address,
            'state' => $request->state,
            'post_code' => $request->post_code,
            'transaction_id' => $tran_id,
            'currency' => 'BDT',
            'description' => "Bank transfer to seller {$business->name} for {$business->product_name} ({$request->quantity} kg)",
            'business_id' => $business->id,
            'seller_id' => $business->user_id,
            'quantity' => $request->quantity,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return view('frontend.payment.bank_instruction', [
            'business' => $business,
            'amount' => $amount,
            'tran_id' => $tran_id,
            'quantity' => $request->quantity
        ]);
    }

    public function confirmBkashPayment(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|string',
            'bkash_transaction_ref' => 'required|string',
            'business_id' => 'required|integer',
            'amount' => 'required|numeric',
            'quantity' => 'required|integer',
        ]);

        $payment = DB::table('payments')->where('transaction_id', $request->transaction_id)->first();
        if (!$payment) {
            return redirect()->back()->with('error', 'Payment record not found.');
        }

        DB::table('payments')->where('transaction_id', $request->transaction_id)->update([
            'status' => 'Completed',
            'description' => $payment->description . " | bKash Ref: {$request->bkash_transaction_ref}",
            'updated_at' => Carbon::now(),
        ]);

        // Save buyer information to seller_buyers table
        if ($payment->seller_id && $payment->business_id) {
            SellerBuyer::create([
                'seller_id' => $payment->seller_id,
                'business_id' => $payment->business_id,
                'buyer_id' => $payment->user_id,
                'buyer_name' => $payment->name,
                'buyer_email' => $payment->email,
                'buyer_phone' => $payment->phone,
                'buyer_address' => $payment->address,
                'buyer_state' => $payment->state,
                'buyer_post_code' => $payment->post_code,
                'quantity' => $payment->quantity ?? 1,
                'amount' => $request->amount,
                'transaction_id' => $request->transaction_id,
                'payment_status' => 'completed',
                'notes' => "bKash payment confirmed - Ref: {$request->bkash_transaction_ref}"
            ]);
        }

        $business = MyBusiness::findOrFail($request->business_id);

        return view('frontend.payment.payment_success', [
            'payment' => $payment,
            'business' => $business,
            'transaction_ref' => $request->bkash_transaction_ref,
            'payment_method' => 'bKash',
            'quantity' => $request->quantity,
        ]);
    }

    public function confirmBankPayment(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|string',
            'bank_transaction_ref' => 'required|string',
            'business_id' => 'required|integer',
            'amount' => 'required|numeric',
            'quantity' => 'required|integer',
        ]);

        $payment = DB::table('payments')->where('transaction_id', $request->transaction_id)->first();
        if (!$payment) {
            return redirect()->back()->with('error', 'Payment record not found.');
        }

        DB::table('payments')->where('transaction_id', $request->transaction_id)->update([
            'status' => 'Completed',
            'description' => $payment->description . " | Bank Ref: {$request->bank_transaction_ref}",
            'updated_at' => Carbon::now(),
        ]);

        // Save buyer information to seller_buyers table
        if ($payment->seller_id && $payment->business_id) {
            SellerBuyer::create([
                'seller_id' => $payment->seller_id,
                'business_id' => $payment->business_id,
                'buyer_id' => $payment->user_id,
                'buyer_name' => $payment->name,
                'buyer_email' => $payment->email,
                'buyer_phone' => $payment->phone,
                'buyer_address' => $payment->address,
                'buyer_state' => $payment->state,
                'buyer_post_code' => $payment->post_code,
                'quantity' => $payment->quantity ?? 1,
                'amount' => $request->amount,
                'transaction_id' => $request->transaction_id,
                'payment_status' => 'completed',
                'notes' => "Bank transfer confirmed - Ref: {$request->bank_transaction_ref}"
            ]);
        }

        $business = MyBusiness::findOrFail($request->business_id);

        return view('frontend.payment.payment_success', [
            'payment' => $payment,
            'business' => $business,
            'transaction_ref' => $request->bank_transaction_ref,
            'payment_method' => 'Bank Transfer',
            'quantity' => $request->quantity,
        ]);
    }

    public function index(Request $request)
    {
        # Here you have to receive all the order data to initate the payment.
        # Let's say, your oder transaction informations are saving in a table called "payments"
        # In "payments" table, order unique identity is "transaction_id". "status" field contain status of the transaction, "amount" is the order amount to be paid and "currency" is for storing Site Currency which will be checked with paid currency.

        $post_data = array();

        $post_data['total_amount'] = $request->amount; # You cant not pay less than 10
        $post_data['currency'] = "BDT";
        $post_data['tran_id'] = uniqid(); // tran_id must be unique

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $request->name;
        $post_data['cus_email'] = $request->email;
        $post_data['cus_add1'] = $request->address;
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = "";
        $post_data['cus_state'] = $request->state;
        $post_data['cus_postcode'] = $request->post_code;
        $post_data['cus_country'] = "Bangladesh";
        $post_data['cus_phone'] = $request->phone;
        $post_data['cus_fax'] = "";
        $post_data['description'] = $request->description;


        # SHIPMENT INFORMATION
        $post_data['ship_name'] = "Store Test";
        $post_data['ship_add1'] = "Dhaka";
        $post_data['ship_add2'] = "Dhaka";
        $post_data['ship_city'] = "Dhaka";
        $post_data['ship_state'] = "Dhaka";
        $post_data['ship_postcode'] = "1000";
        $post_data['ship_phone'] = "";
        $post_data['ship_country'] = "Bangladesh";

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = "Computer";
        $post_data['product_category'] = "Goods";
        $post_data['product_profile'] = "physical-goods";

        # OPTIONAL PARAMETERS
        $post_data['value_a'] = "ref001";
        $post_data['value_b'] = "ref002";
        $post_data['value_c'] = "ref003";
        $post_data['value_d'] = "ref004";


        //**************************** */
        $order_id = OrderPayment::insertGetId([
            'user_id' => Auth::id(),
            'invoice_no' => mt_rand(10000000, 99999999),
            'total' => $request->total,
            'subtotal' => $request->subtotal,
            'discount_percentage' => $request->discount_percentage,
            'created_at' => Carbon::now(),
        ]);


        $carts = Cart::where('user_id', Auth::id())->where('user_ip', request()->ip())->latest()->get();
        foreach ($carts as $cart) {

            OrderitemPayment::insert([
                'order_id' => $order_id,
                'product_id' => $cart->product_id,
                'product_qty' => $cart->qty,
                'created_at' => Carbon::now(),
            ]);

            /*stock or outOfStock */
            $prod = Product::where('id', $cart->product_id)->first();
            $prod->product_quantity = $prod->product_quantity - $cart->qty;
            $prod->update();
        }


        $request->validate([
            'post_code' => 'integer',
        ]);
        #Before  going to initiate the payment order status need to insert or update as Pending.
        $update_product = DB::table('payments')->where('transaction_id', $post_data['tran_id'])
            ->updateOrInsert([
                'order_id' => $order_id,
                'user_id' => auth()->id(),
                'name' => $post_data['cus_name'],
                'email' => $post_data['cus_email'],
                'phone' => $post_data['cus_phone'],
                'amount' => $post_data['total_amount'],
                'status' => 'Pending',
                'address' => $post_data['cus_add1'],
                'state' => $post_data['cus_state'],
                'post_code' => $post_data['cus_postcode'],
                'transaction_id' => $post_data['tran_id'],
                'currency' => $post_data['currency'],
                'description' => $post_data['description'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

        if (Session::has('discount')) {
            session()->forget('discount');
        }
        //delete from cart
        Cart::where('user_id', Auth::id())->where('user_ip', request()->ip())->delete();




        $sslc = new SslCommerzNotification();
        # initiate(Transaction Data , false: Redirect to SSLCOMMERZ gateway/ true: Show all the Payement gateway here )
        $payment_options = $sslc->makePayment($post_data, 'hosted');

        if (!is_array($payment_options)) {
            print_r($payment_options);
            $payment_options = array();
        }

        //=======================


    }





    public function payViaAjax(Request $request)
    {

        # Here you have to receive all the order data to initate the payment.
        # Lets your oder trnsaction informations are saving in a table called "payments"
        # In payments table order uniq identity is "transaction_id","status" field contain status of the transaction, "amount" is the order amount to be paid and "currency" is for storing Site Currency which will be checked with paid currency.

        $post_data = array();
        $post_data['total_amount'] = '10'; # You cant not pay less than 10
        $post_data['currency'] = "BDT";
        $post_data['tran_id'] = uniqid(); // tran_id must be unique



        # CUSTOMER INFORMATION
        $post_data['cus_name'] = 'Customer Name';
        $post_data['cus_email'] = 'customer@mail.com';
        $post_data['cus_add1'] = 'Customer Address';
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = "";
        $post_data['cus_state'] = "";
        $post_data['cus_postcode'] = "";
        $post_data['cus_country'] = "Bangladesh";
        $post_data['cus_phone'] = '8801XXXXXXXXX';
        $post_data['cus_fax'] = "";

        # SHIPMENT INFORMATION
        $post_data['ship_name'] = "Store Test";
        $post_data['ship_add1'] = "Dhaka";
        $post_data['ship_add2'] = "Dhaka";
        $post_data['ship_city'] = "Dhaka";
        $post_data['ship_state'] = "Dhaka";
        $post_data['ship_postcode'] = "1000";
        $post_data['ship_phone'] = "";
        $post_data['ship_country'] = "Bangladesh";

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = "Computer";
        $post_data['product_category'] = "Goods";
        $post_data['product_profile'] = "physical-goods";

        # OPTIONAL PARAMETERS
        $post_data['value_a'] = "ref001";
        $post_data['value_b'] = "ref002";
        $post_data['value_c'] = "ref003";
        $post_data['value_d'] = "ref004";


        #Before  going to initiate the payment order status need to update as Pending.
        $update_product = DB::table('payments')
            ->where('transaction_id', $post_data['tran_id'])
            ->updateOrInsert([
                'user_id' => auth()->id(),
                'name' => $post_data['cus_name'],
                'email' => $post_data['cus_email'],
                'phone' => $post_data['cus_phone'],
                'amount' => $post_data['total_amount'],
                'status' => 'Pending',
                'address' => $post_data['cus_add1'],
                'transaction_id' => $post_data['tran_id'],
                'currency' => $post_data['currency'],
            ]);

        $sslc = new SslCommerzNotification();
        # initiate(Transaction Data , false: Redirect to SSLCOMMERZ gateway/ true: Show all the Payement gateway here )
        $payment_options = $sslc->makePayment($post_data, 'checkout', 'json');

        if (!is_array($payment_options)) {
            print_r($payment_options);
            $payment_options = array();
        }
    }

    public function success(Request $request)
    {
        $tran_id = $request->input('tran_id');
        $amount = $request->input('amount');
        $currency = $request->input('currency');

        $sslc = new SslCommerzNotification();

        #Check order status in order tabel against the transaction id or order id.
        $order_detials = DB::table('payments')
            ->where('transaction_id', $tran_id)
            ->select('*')->first();

        if (Auth::guest() && $order_detials && $order_detials->user_id) {
            Auth::loginUsingId($order_detials->user_id);
        }

        $status_text = 'Unknown';
        $message = '';

        if ($order_detials && $order_detials->status == 'Pending') {
            $validation = $sslc->orderValidate($request->all(), $tran_id, $amount, $currency);

            if ($validation == TRUE) {
                $update_product = DB::table('payments')
                    ->where('transaction_id', $tran_id)
                    ->update(['status' => 'Processing']);

                // Save buyer information to seller_buyers table for business transactions
                if (strpos($order_detials->description, 'Payment to seller') !== false) {
                    $seller_id = $order_detials->seller_id;
                    $business_id = $order_detials->business_id;
                    $quantity = $order_detials->quantity ?? 1;

                    if ($seller_id && $business_id) {
                        SellerBuyer::create([
                            'seller_id' => $seller_id,
                            'business_id' => $business_id,
                            'buyer_id' => $order_detials->user_id,
                            'buyer_name' => $order_detials->name,
                            'buyer_email' => $order_detials->email,
                            'buyer_phone' => $order_detials->phone,
                            'buyer_address' => $order_detials->address,
                            'buyer_state' => $order_detials->state,
                            'buyer_post_code' => $order_detials->post_code,
                            'quantity' => $quantity,
                            'amount' => $amount,
                            'transaction_id' => $tran_id,
                            'payment_status' => 'completed',
                            'notes' => 'Payment successful - Business product purchase'
                        ]);
                    }
                }

                $status_text = 'Completed';
                $message = 'Your payment was successful and the order is now processing.';
            } else {
                $update_product = DB::table('payments')
                    ->where('transaction_id', $tran_id)
                    ->update(['status' => 'Failed']);

                $status_text = 'Failed';
                $message = 'Payment validation failed. Please try again or contact support.';
            }
        } elseif ($order_detials && ($order_detials->status == 'Processing' || $order_detials->status == 'Complete')) {
            $status_text = 'Completed';
            $message = 'This transaction was already processed successfully.';
        } else {
            $status_text = 'Invalid';
            $message = 'Invalid transaction. Please check your payment details and try again.';
        }

        return view('frontend.payment.result', [
            'tran_id' => $tran_id,
            'amount' => $amount,
            'currency' => $currency,
            'status_text' => $status_text,
            'message' => $message,
        ]);
    }

    public function fail(Request $request)
    {
        $tran_id = $request->input('tran_id');

        $order_detials = DB::table('payments')
            ->where('transaction_id', $tran_id)
            ->select('transaction_id', 'status', 'currency', 'amount', 'user_id')->first();

        if (Auth::guest() && $order_detials && $order_detials->user_id) {
            Auth::loginUsingId($order_detials->user_id);
        }

        $status_text = 'Failed';
        $message = 'Your payment was not completed. Please try again or contact support if the problem persists.';

        if (! $order_detials) {
            $status_text = 'Invalid';
            $message = 'Transaction not found. Please return to the store and try again.';
        } elseif ($order_detials->status == 'Processing' || $order_detials->status == 'Complete') {
            $status_text = 'Completed';
            $message = 'This transaction was already completed successfully.';
        } else {
            DB::table('payments')
                ->where('transaction_id', $tran_id)
                ->update(['status' => 'Failed']);
        }

        return view('frontend.payment.result', [
            'tran_id' => $tran_id,
            'amount' => optional($order_detials)->amount,
            'currency' => optional($order_detials)->currency,
            'status_text' => $status_text,
            'message' => $message,
        ]);
    }

    public function cancel(Request $request)
    {
        $tran_id = $request->input('tran_id');

        $order_detials = DB::table('payments')
            ->where('transaction_id', $tran_id)
            ->select('transaction_id', 'status', 'currency', 'amount', 'user_id')->first();

        if (Auth::guest() && $order_detials && $order_detials->user_id) {
            Auth::loginUsingId($order_detials->user_id);
        }

        $status_text = 'Canceled';
        $message = 'Your payment was cancelled. You can return to the marketplace and choose another option.';

        if (! $order_detials) {
            $status_text = 'Invalid';
            $message = 'Transaction not found. Please return to the store and try again.';
        } elseif ($order_detials->status == 'Processing' || $order_detials->status == 'Complete') {
            $status_text = 'Completed';
            $message = 'This transaction was already completed successfully.';
        } else {
            DB::table('payments')
                ->where('transaction_id', $tran_id)
                ->update(['status' => 'Canceled']);
        }

        return view('frontend.payment.result', [
            'tran_id' => $tran_id,
            'amount' => optional($order_detials)->amount,
            'currency' => optional($order_detials)->currency,
            'status_text' => $status_text,
            'message' => $message,
        ]);
    }

    public function ipn(Request $request)
    {
        #Received all the payement information from the gateway
        if ($request->input('tran_id')) #Check transation id is posted or not.
        {

            $tran_id = $request->input('tran_id');

            #Check order status in order tabel against the transaction id or order id.
            $order_details = DB::table('payments')
                ->where('transaction_id', $tran_id)
                ->select('transaction_id', 'status', 'currency', 'amount')->first();

            if ($order_details->status == 'Pending') {
                $sslc = new SslCommerzNotification();
                $validation = $sslc->orderValidate($request->all(), $tran_id, $order_details->amount, $order_details->currency);
                if ($validation == TRUE) {
                    /*
                    That means IPN worked. Here you need to update order status
                    in order table as Processing or Complete.
                    Here you can also sent sms or email for successful transaction to customer
                    */
                    $update_product = DB::table('payments')
                        ->where('transaction_id', $tran_id)
                        ->update(['status' => 'Processing']);

                    echo "Transaction is successfully Completed";
                } else {
                    /*
                    That means IPN worked, but Transation validation failed.
                    Here you need to update order status as Failed in order table.
                    */
                    $update_product = DB::table('payments')
                        ->where('transaction_id', $tran_id)
                        ->update(['status' => 'Failed']);

                    echo "validation Fail";
                }
            } else if ($order_details->status == 'Processing' || $order_details->status == 'Complete') {

                #That means Order status already updated. No need to udate database.

                echo "Transaction is already successfully Completed";
            } else {
                #That means something wrong happened. You can redirect customer to your product page.

                echo "Invalid Transaction";
            }
        } else {
            echo "Invalid Data";
        }
    }
}
