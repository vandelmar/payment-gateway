<?php

namespace App\Http\Controllers;

use App\Midtrans\Notification;
use Illuminate\Http\Request;
use App\Donation;
use Veritrans_Config;
use Veritrans_Snap;
use Veritrans_Notification;

class DonationController extends Controller
{
    public function __construct()
    {
        Veritrans_Config::$serverKey = config('services.midtrans.serverKey');
        Veritrans_Config::$isProduction = config('services.midtrans.isProduction');
        Veritrans_Config::$isSanitized = config('services.midtrans.isSanitized');
        Veritrans_Config::$is3ds = config('services.midtrans.is3ds');
    }

    public function index()
    {
        $donation = Donation::orderBy('id', 'DESC')->paginate(8);
        return view('welcome', compact('donation'));
    }

    public function create()
    {
        return view('donation');
    }

    public function store(Request $request)
    {
        \DB::transaction(function () use ($request) {
           $donation = Donation::create([
               'donation_code' => 'SANDBOX-' . uniqid(),
               'donor_name' => $request->donor_name,
               'donor_email' => $request->donor_email,
               'donation_type' => $request->donation_type,
               'amount' => floatval($request->amount),
               'note' => $request->note
           ]);

           $payload = [
               'transaction_details' => [
                   'order_id' => $donation->id,
                   'gross_amount' => $donation->amount,
               ],
               'customer_details' => [
                   'first_name' => $donation->donor_name,
                   'email' => $donation->donor_email,
               ],
               'item_details' => [
                   [
                       'id' => $donation->donation_type,
                       'price' => $donation->amount,
                       'quantity' => 1,
                       'name' => ucwords(str_replace('_', ' ', $donation->donation_type))
                   ]
                ]
           ];

           $snapToken = Veritrans_Snap::getSnapToken($payload);
           $donation->snap_token = $snapToken;
           $donation->save();

           $this->response['snap_token'] = $snapToken;
        });

        return response()->json($this->response);
    }

    public function notification(Request $request)
    {
        $notif = new Notification();
        \DB::transaction(function () use ($notif) {

            $transactionStatus = $notif->transaction_status;
            $paymentType = $notif->payment_type;
            $orderId = $notif->order_id;
            $fraudStatus = $notif->fraud_status;
            $donation = Donation::findOrFail($orderId);

            if ($transactionStatus == 'capture'){
                if ($paymentType == 'credit_card') {

                    if ($fraudStatus == 'challenge') {
                        $donation->setStatusPending();
                    } else {
                        $donation->setStatusSuccess();
                    }
                }
            } elseif ($transactionStatus == 'settlement'){
                $donation->setStatusSuccess();
            } elseif ($transactionStatus == 'pending'){
                $donation->setStatusPending();
            } elseif ($transactionStatus == 'deny'){
                $donation->setStatusFailed();
            } elseif ($transactionStatus == 'expired'){
                $donation->setStatusExpired();
            } elseif ($transactionStatus == 'cancel'){
                $donation->setStatusFailed();
            }
        });

        return;
    }
}
