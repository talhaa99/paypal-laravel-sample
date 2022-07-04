<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalController extends Controller
{
    /**
     * process transaction.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processTransaction($amount)
    {
        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $paypalToken = $provider->getAccessToken();

            $response = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('successTransaction'),
                    "cancel_url" => route('cancelTransaction'),
                ],
                "purchase_units" => [
                    0 => [
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => (int)$amount
                        ]
                    ]
                ]
            ]);

            if (isset($response['id']) && $response['id'] != null) {
                foreach ($response['links'] as $link) {
                    if ($link['rel'] == 'approve') {
                        return redirect()->away($link['href']);
                    }
                }

                Session::flash('error', 'Something went wrong.');
                return redirect()->route('homepage');

            } else {
                Session::flash('error', $response['message'] ?? 'Something went wrong.');
                return redirect()->route('homepage');
            }
        } catch (\Throwable $throwable) {
            Session::flash('error', $throwable->getMessage() ?? 'Something went wrong.');
            return redirect()->route('homepage');
        }
    }

    /**
     * success transaction.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function successTransaction(Request $request)
    {
        DB::beginTransaction();
        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();
            $response = $provider->capturePaymentOrder($request['token']);

            if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                Session::flash('success', 'Transaction Successfully Completed.');
                return redirect()->route('homepage');
            } else {
                DB::rollBack();
                Session::flash('error', $response['message'] ?? 'Something went wrong.');
                return redirect()->route('homepage');
            }
        } catch (\Throwable $throwable) {
            DB::rollBack();
            Session::flash('error', $throwable->getMessage() ?? 'Something went wrong.');
            return redirect()->route('homepage');
        }
    }

    /**
     * cancel transaction.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancelTransaction(Request $request)
    {
        Session::flash('error', 'Payment cancelled.');
        return redirect()->route('homepage');
    }
}
