<?php

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/reserve/book', array(
    'methods' => 'POST',
    'callback' => 'client_book',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/reserve/options', array(
    'methods' => 'GET',
    'callback' => 'client_options',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/reserve/user', array(
    'methods' => 'GET',
    'callback' => 'client_product_user',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/reserve/check', array(
    'methods' => 'GET',
    'callback' => 'client_product_check',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/events/check', array(
    'methods' => 'GET',
    'callback' => 'client_events_check',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

add_action( 'rest_api_init', function () {
  register_rest_route( 'client/v1', '/events/user', array(
    'methods' => 'GET',
    'callback' => 'client_events_user',
    'permission_callback' => function () {
      return true;
    }
  ) );
} );

function client_options(WP_REST_Request $request) {
  $reserveCloud = new ReserveCloud();
  $body = $request->get_params();

  $query = http_build_query([
    'requestGuid' => getGUID(),
    'requestName' => 'GetEventGuestListFieldOptions',
    'parameters'  => "[['siteNames',['" . $body['siteName'] . "']]]"
  ]);

  $eventOptions = $reserveCloud->getReserveCloudInfo($query);
  
  return $eventOptions;
}

function client_product_get_dates() {
  $reserveCloud = new ReserveCloud();

  $query_one = http_build_query([
    'requestGuid' => getGUID(),
    'requestName' => 'EventGuestListCheckAvailability',
    'maxResults'  => 90,
    'parameters'  => "[['siteNames',['products']],['desiredDateFrom','05/01/" . gmdate('Y') . "'],['desiredDateTo','10/01/" . gmdate('Y') . "'],['partySize','1'],['functionTypes',['product Rental']]]"
  ]);

  $query_two = http_build_query([
    'requestGuid' => getGUID(),
    'requestName' => 'EventGuestListCheckAvailability',
    'maxResults'  => 90,
    'parameters'  => "[['siteNames',['products']],['desiredDateFrom','05/01/" . gmdate('Y') . "'],['desiredDateTo','10/01/" . gmdate('Y') . "'],['partySize','2'],['functionTypes',['product Rental']]]"
  ]);

  $productData = $reserveCloud->getReserveCloudInfo($query_one);
  $productDataTwo = array_column($reserveCloud->getReserveCloudInfo($query_two), null, "dateString");

  forEach ($productData as $product) {
    if (isset($productDataTwo[$product->dateString])){
      $product->bookingCodeTwo = $productDataTwo[$product->dateString]->bookingCode;
    }
  }
  
  return $productData;
}

function client_product_check(WP_REST_Request $request) {
  $reserveCloud = new ReserveCloud();
  $body = $request->get_params();

  //phpcs:ignore
  $date = date('m/d/Y', strtotime($body['date']));

  $query = http_build_query([
    'requestGuid' => getGUID(),
    'requestName' => 'EventGuestListCheckAvailability',
    'maxResults'  => 1,
    'parameters'  => "[['siteNames',['products']],['desiredDateFrom','" . $date . "'],['desiredDateTo','" . $date . "'],['partySize','" . $body['size'] . "'],['functionTypes',['product Rental']]]"
  ]);

  $productData = $reserveCloud->getReserveCloudInfo($query);
  
  return $productData;
}

function client_product_user(WP_REST_Request $request) {
  $registerBooking = new RegisterBooking();
  $body = $request->get_params();

  $reserve_count = $registerBooking->getproductReservations($body['reserveId'], $body['date']);

  return [$body['reserveId'], $reserve_count];
}

function client_events_user(WP_REST_Request $request) {
  $registerBooking = new RegisterBooking();
  $body = $request->get_params();

  $reserve_count = $registerBooking->getEventReservations($body['reserveId'], $body['date'], $body['eventId']);

  return [$body['reserveId'], $reserve_count];
}

function client_events_check(WP_REST_Request $request) {
  $reserveCloud = new ReserveCloud();
  $body = $request->get_params();

  //phpcs:ignore
  $date = date('m/d/Y', strtotime($body['date']));

  $query_one = http_build_query([
    'requestGuid' => getGUID(),
    'requestName' => 'EventGuestListCheckAvailability',
    'maxResults'  => 90,
    'parameters'  => "[['siteNames',['Client']],['desiredDateFrom','" . $date . "'],['desiredDateTo','" . $date . "'],['partySize','" . $body['size'] . "'],['functionTypes',['Ticket Sales']]]"
  ]);

  $allEvents = $reserveCloud->getReserveCloudInfo($query_one);
  $eventData = '';

  foreach( $allEvents as $event ) {
    foreach ( $event->guestTypes as $guest ) {
      if ($guest->guestType === trim($body['guestType'])) {
        $eventData = $event;
      }

      if (!empty($eventData)) break;
    }
    if (!empty($eventData)) break;
  }
  
  return $eventData;
}

function client_book(WP_REST_Request $request) {
  $reserveCloud = new ReserveCloud();
  $registerBooking = new RegisterBooking();
  $body = $request->get_params();
  $process_transaction = [];

  if (intval($body['amount']) !== 0) {
    $process_transaction = vantiv_transaction_sale($body);
    $body['transaction'] = $process_transaction;
  }

  $data = [
    'parameters' => [
      'bookingCode' => $body["bookingCode"],
      'firstName' => $body["firstName"],
      'lastName' => $body["lastName"],
      'email' => $body["email"],
      "mobilePhone" => $body["phone"],
      'comments' => $body["notes"],
      'optIn' => $body["optIn"] ? 'true' : 'false',
      'partyMix' => [[
        'guestType' => $body["guestType"],
        'size' => $body["size"],
      ]],
    ]
  ];

  if (intval($body['amount']) !== 0 && !empty($process_transaction)) {
    $data['parameters'] = array_merge($data['parameters'], [
      'cardAbbreviation' => $body['transaction']['card_lastfour'],
      'cardBrand' => $body['transaction']['card_brand'],
      'cardExpirationMonth' => $body['transaction']['card_exp_month'],
      'cardExpirationYear' => '20' . $body['transaction']['card_exp_year'],
      'cardHolderName' => $body["firstName"] . " " . $body["lastName"],
      'confirmationStatus' => "Paid",
      'paymentAmount' => "$" . $body['amount'],
      'paymentDate' => gmdate('m/d/Y'),
      'paymentDescription' => "",
      'paymentDetails' => "",
      'paymentMethod' => 'Credit Card',
      'paymentVantivApprovalNumber' => $body['transaction']['approval_number'],
      'paymentVantivCustomerCode' => '0',
      'paymentVantivReferenceNumber' => $body['transaction']['reference_number'],
      'paymentVantivTicketNumber' => $body['transaction']['ticket_number'],
      'paymentVantivTransactionId' => $body['transaction']['id'],
      'vantivAccountId' => 9999999,
      'vantivPaymentAccountId' => $body['PaymentAccountID'],
    ]);
  }

  $bookingResponse = $reserveCloud->putEventBookingRequest($data);
  
  if (!is_wp_error( $bookingResponse )) {
    //phpcs:ignore
    $date = date('Ymd', strtotime($body['date']));
    $reserve_id = $body['userId'];
    $parse_booking = json_decode($bookingResponse['body'], true);

    for ($i = 0; $i < $body['size']; $i++) {
      $registerBooking->createReservation($reserve_id, $date, $parse_booking["confirmationNumber"], $body['eventType'], $body['eventId'] ?? null);
    }
  }
  
  return new WP_REST_Response($bookingResponse, 200);
}