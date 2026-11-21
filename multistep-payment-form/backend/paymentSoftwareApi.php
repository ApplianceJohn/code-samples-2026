<?php

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/reserve/process-payment', array(
    'methods' => 'POST',
    'callback' => 'product_vantiv_authorize_only',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/reserve/payment-callback', array(
    'methods' => 'GET',
    'callback' => 'product_vantiv_callback',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/reserve/confirmation', array(
    'methods' => 'GET',
    'callback' => 'product_vantiv_confirmation',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/events/confirmation', array(
    'methods' => 'GET',
    'callback' => 'event_vantiv_confirmation',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

function generate_uuid_v4() {
  $data = random_bytes(16);
  $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
  $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Authorize a Credit Card Only without charging
 *
 * @param WP_REST_Request $request
 * @return array Returns an array with the URL, setup ID, and args.
 */
function product_vantiv_authorize_only(WP_REST_Request $request) {
  // list of error strings
  $errors = [];

  // validate first, last, address, city, state, zip, terms accept
  $cardholder_name = $request->get_param('firstName') . ' ' . $request->get_param('lastName');
  $email = $request->get_param('email');
  $phone = $request->get_param('phone');
  $address1 = $request->get_param('address1');
  $address2 = $request->get_param('address2');
  $city = $request->get_param('city');
  $state = $request->get_param('state');
  $zip_code = $request->get_param('zip');
  $terms_accept = $request->get_param('termsAccept');
  $amount = $request->get_param('amount');

  $return_url = get_site_url() . "/wp-json/client/v1/reserve/payment-callback";
  $return_url_title = 'NONE';

  $is_event = $request->has_param('eventId');

  if (!trim($cardholder_name)) {
      array_push($errors, 'firstName');
  }
  if (!trim($email)) {
      array_push($errors, 'email');
  }
  if (!trim($phone)) {
      array_push($errors, 'phone');
  }
  if (!trim($address1)) {
      array_push($errors, 'address1');
  }
  if (!trim($city)) {
      array_push($errors, 'city');
  }
  if (!trim($state)) {
      array_push($errors, 'state');
  }
  if (!trim($zip_code)) {
      array_push($errors, 'zip');
  }
  if (!trim($terms_accept)) {
      array_push($errors, 'termsAccept');
  }
  if (!trim($amount)) {
      array_push($errors, 'amount');
  }

  if (count($errors) > 0) {
      return new WP_REST_Response(['status' => 400, 'fields' => $errors], 400);
  }

  $transaction = [];
  if (!$amount) {
    $transaction['transaction_amount'] = "4.00";
  } else {
    $transaction['transaction_amount'] = number_format((float)$amount, 2, '.', '');
  }
  $transaction['reference_number'] = generate_uuid_v4();
  $transaction['ticket_number'] = generate_uuid_v4();

  $transaction_setup = [];
  $transaction_setup['return_url'] = $return_url;
  $transaction_setup['return_url_title'] = $return_url_title;

  $card = [];
  $card['cardholder_name'] = $cardholder_name;

  $payment_account = [];
  $payment_account['payment_account_reference_number'] = (int)time();

  $address = [];
  $address['billing_name'] = $cardholder_name;
  $address['billing_address1'] = $address1;
  $address['billing_city'] = $city;
  $address['billing_state'] = $state;
  $address['billing_zipcode'] = $zip_code;
  $address['billing_email'] = $email;
  $address['billing_phone'] = $phone;

  $data_args = [
    'address' => $address,
    'card' => $card,
    'payment_account' => $payment_account,
    'transaction' => $transaction,
    'transaction_setup' => $transaction_setup,
    'is_event' => $is_event,
  ];

  $transaction_data = vantiv_transaction_setup($data_args);

  return array_merge($transaction_data, ['args' => $data_args]);
}

/**
 * Returns data from the Vantiv iframe after submission.
 *
 * @param WP_REST_Request $request
 * @return void
 * @throws Exception If an error occurs during the callback process.
 */
function product_vantiv_callback(WP_REST_Request $request) {
  header('Content-Type: text/html');
	$body = $request->get_params();
  $script = 'window.parent.postMessage({ name: "worldpayResponse", status: false }, "*");';
  
  if ( (int)$body['ExpressResponseCode'] === 0 ) {
    $script = 'window.parent.postMessage({ name: "worldpayResponse", status: true, transaction:' . wp_json_encode( $body ) . ' }, "*");';
  }

  // phpcs:ignore
  echo '<div style="padding:20px;"><p style="font-weight:700;font-family:sans-serif;">Your booking and transaction are being processed. Please wait.</p></div><script>' . $script . '</script>';
  exit();
}

function product_vantiv_confirmation(WP_REST_Request $request) {
  header('Content-Type: text/html');
  // phpcs:ignore
  echo '<div style="padding:20px;background-color:#d9f99d;"><p style="font-weight:700;font-family:sans-serif;">Thank you for your booking! See you at the nonspecific location, de-emphasized for anonymity.</p></div>';
  exit();
}

function event_vantiv_confirmation(WP_REST_Request $request) {
  header('Content-Type: text/html');
  // phpcs:ignore
  echo '<div style="padding:20px;background-color:#d9f99d;"><p style="font-weight:700;font-family:sans-serif;">Thank you for your booking! See you at the event.</p></div>';
  exit();
}