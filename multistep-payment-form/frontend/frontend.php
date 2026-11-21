<?php
$attributes = wp_parse_args(
  $args ?? [], // phpcs:ignore
  [
    'event_id'   => null,
		'event_name' => null,
		'user_type'  => '',
  ]
);

[
  'event_id'   		 => $event_id,
	'event_name' 		 => $event_name,
	'user_type'  		 => $user_type,
] = $attributes;

$max_tickets = get_field('total_tickets_allowed_per_person', $event_id);

$guest_option = [];

$special_guest_types = get_field('reserve_cloud_special_guest_types', $event_id);

if ($special_guest_types && count($special_guest_types) !== 0) {
	foreach ($special_guest_types as $pair) {
		if (user_has_role(get_current_user_id(), $pair['applicable_roles'])) {
			$guest_option = [
				'type' => $pair['type'],
				'price' => $pair['price']
			];
			break;
		}
	}
}

if (count($guest_option) === 0) {
	if (get_field('reserve_cloud_member_guest_type', $event_id) && $user_type === 'Member') {
		$guest_option = [
			'type' => get_field('reserve_cloud_member_guest_type', $event_id),
			'price' => get_field('reserve_cloud_member_guest_price', $event_id)
		];
	} else {
		$guest_option = [
			'type' => get_field('reserve_cloud_guest_type', $event_id),
			'price' => get_field('reserve_cloud_guest_price', $event_id)
		];
	}
}

$is_free = intval($guest_option['price']) === 0;
?>
<section>
	<h2 class="text-3xl font-bold mb-2"><?= esc_html( $event_name ); ?></h2>
	<form id="event-form-<?= esc_attr( $event_id ); ?>" action="/wp-json/client/v1/reserve/book" method="post" class="mb-8" data-module="EventMultiForm" data-total-steps="4">
		<div class="form-section">
			<div class="form-section__fields">
				<div class="flex gap-4">
					<div>
						<p class="block mb-1 text-xl font-bold font-heading">Ticket Type</p>
						<p class="mb-4"><?= esc_html($guest_option['type']); ?></p>
					</div>
					<div>
						<p class="block mb-1 text-xl font-bold font-heading">Price</p>
						<p class="mb-4"><?= esc_html('$' . number_format((float)$guest_option['price'], 2)); ?></p>
					</div>
				</div>
				<label for="ticket-quantity-<?= esc_attr( $event_id ); ?>" class="block mb-1 text-xl font-bold font-heading">Quantity</label>
				<span class="text-xs leading-none">This event has a limit of <?= esc_html( $max_tickets ); ?> tickets per customer.</span>
				<input id="ticket-quantity-<?= esc_attr( $event_id ); ?>" name="ticket_quantity" type="number" min="1" max="<?= esc_attr( $max_tickets ); ?>" value="" class="ticket_quantity mb-4 w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required />
			</div>
			<button type="button" class="next-button m-0 py-2 px-4 text-white bg-black" style="display: none;" data-action="fetchTicketData">Continue</button>
		</div>
		<div class="form-section hidden">
			<h2 class="text-xl font-bold mb-4">Contact Info</h2>
			<div class="grid grid-cols-2 gap-4 mb-4">
				<div class="mb-4 col-span-2 md:col-span-1">
					<label for="first_name-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">First Name <span class="text-red-500">*</span></label>
					<input type="text" id="first_name-<?= esc_attr( $event_id ); ?>" name="first_name" class="first_name w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
				<div class="mb-4 col-span-2 md:col-span-1">
					<label for="last_name-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">Last Name <span class="text-red-500">*</span></label>
					<input type="text" id="last_name-<?= esc_attr( $event_id ); ?>" name="last_name" class="last_name w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
				<div class="mb-4 col-span-2 md:col-span-1">
					<label for="phone_number-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">Phone Number <span class="text-red-500">*</span></label>
					<input type="tel" id="phone_number-<?= esc_attr( $event_id ); ?>" name="phone_number" class="phone_number w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
				<div class="mb-4 col-span-2 md:col-span-1">
					<label for="email-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">Email Address <span class="text-red-500">*</span></label>
					<input type="email" id="email-<?= esc_attr( $event_id ); ?>" name="email" class="email w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
			</div>
			<div class="fancy-checkbox relative flex col-span-12 mb-8">
				<input type="checkbox" id="mailingList-<?= esc_attr( $event_id ); ?>" name="mailingList" class="mailingList opacity-0 absolute -left-full" checked />
				<label for="mailingList-<?= esc_attr( $event_id ); ?>" class="mt-1.5 pl-10 text-xs font-medium before:rounded-full">I would like to receive emails.</label>
			</div>
			<button type="button" class="prev-button m-0 py-2 px-4 text-black border border-black">Back</button>
			<button type="button" class="next-button m-0 ml-2 py-2 px-4 text-white bg-black" style="display: none;">Continue</button>
		</div>
		<!-- don't show the credit card form if the event is free -->
		<?php if ( ! $is_free ) : ?>
		<div class="form-section hidden">
			<h2 class="text-xl font-bold mb-4">Credit Card Billing Address</h2>
			<div class="grid grid-cols-6 gap-4 mb-4">
				<div class="mb-4 col-span-6 md:col-span-3">
					<label for="billing_first_name-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">First Name <span class="text-red-500">*</span></label>
					<input type="text" id="billing_first_name-<?= esc_attr( $event_id ); ?>" name="billing_first_name" class="billing_first_name w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
				<div class="mb-4 col-span-6 md:col-span-3">
					<label for="billing_last_name-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">Last Name <span class="text-red-500">*</span></label>
					<input type="text" id="billing_last_name-<?= esc_attr( $event_id ); ?>" name="billing_last_name" class="billing_last_name w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
				<div class="mb-4 col-span-6 lg:col-span-3">
					<label for="billing_address_1-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">Address 1 <span class="text-red-500">*</span></label>
					<input type="text" id="billing_address_1-<?= esc_attr( $event_id ); ?>" name="billing_address_1" class="billing_address_1 w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
				<div class="mb-4 col-span-6 lg:col-span-3">
					<label for="billing_address_2-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">Address 2</label>
					<input type="text" id="billing_address_2-<?= esc_attr( $event_id ); ?>" name="billing_address_2" class="billing_address_2 w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500">
				</div>
				<div class="mb-4 col-span-6 lg:col-span-2">
					<label for="billing_city-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">City <span class="text-red-500">*</span></label>
					<input type="text" id="billing_city-<?= esc_attr( $event_id ); ?>" name="billing_city" class="billing_city w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
				<div class="mb-4 col-span-6 md:col-span-3 lg:col-span-2">
					<label for="billing_state-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">State <span class="text-red-500">*</span></label>
					<select id="billing_state-<?= esc_attr( $event_id ); ?>" name="billing_state" class="billing_state w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500 invalid:!text-black invalid:!font-normal" required>
						<option value="AL">Alabama</option>
						<option value="AK">Alaska</option>
						<option value="AZ">Arizona</option>
						<option value="AR">Arkansas</option>
						<option value="CA">California</option>
						<option value="CO">Colorado</option>
						<option value="CT">Connecticut</option>
						<option value="DE">Delaware</option>
						<option value="DC">District Of Columbia</option>
						<option value="FL">Florida</option>
						<option value="GA">Georgia</option>
						<option value="HI">Hawaii</option>
						<option value="ID">Idaho</option>
						<option value="IL">Illinois</option>
						<option value="IN">Indiana</option>
						<option value="IA">Iowa</option>
						<option value="KS">Kansas</option>
						<option value="KY">Kentucky</option>
						<option value="LA">Louisiana</option>
						<option value="ME">Maine</option>
						<option value="MD">Maryland</option>
						<option value="MA">Massachusetts</option>
						<option value="MI">Michigan</option>
						<option value="MN">Minnesota</option>
						<option value="MS">Mississippi</option>
						<option value="MO">Missouri</option>
						<option value="MT">Montana</option>
						<option value="NE">Nebraska</option>
						<option value="NV">Nevada</option>
						<option value="NH">New Hampshire</option>
						<option value="NJ">New Jersey</option>
						<option value="NM">New Mexico</option>
						<option value="NY">New York</option>
						<option value="NC">North Carolina</option>
						<option value="ND">North Dakota</option>
						<option value="OH">Ohio</option>
						<option value="OK">Oklahoma</option>
						<option value="OR">Oregon</option>
						<option value="PA">Pennsylvania</option>
						<option value="RI">Rhode Island</option>
						<option value="SC">South Carolina</option>
						<option value="SD">South Dakota</option>
						<option value="TN">Tennessee</option>
						<option value="TX">Texas</option>
						<option value="UT">Utah</option>
						<option value="VT">Vermont</option>
						<option value="VA">Virginia</option>
						<option value="WA">Washington</option>
						<option value="WV">West Virginia</option>
						<option value="WI">Wisconsin</option>
						<option value="WY">Wyoming</option>
					</select>
				</div>
				<div class="mb-4 col-span-6 md:col-span-3 lg:col-span-2">
					<label for="billing_zip-<?= esc_attr( $event_id ); ?>" class="block mb-2 text-xl font-bold font-heading">ZIP <span class="text-red-500">*</span></label>
					<input type="text" id="billing_zip-<?= esc_attr( $event_id ); ?>" name="billing_zip" minlength="5" inputmode="numeric" pattern="(\d{5}([\-]\d{4})?)" class="billing_zip w-full px-3 py-2 border border-black focus:outline-none focus:ring focus:ring-blue-500" required>
				</div>
			</div>
			<div class="fancy-checkbox relative flex col-span-12 mb-8">
				<input type="checkbox" id="termsOfUse-<?= esc_attr( $event_id ); ?>" name="termsOfUse" class="termsOfUse opacity-0 absolute -left-full" required />
				<label for="termsOfUse-<?= esc_attr( $event_id ); ?>" class="mt-1.5 pl-10 text-xs font-medium before:rounded-full">
					I accept the <button class="open-terms-modal m-0" type="button"><span class="underline before:hidden after:hidden">terms of use</span></button>.</label>
			</div>
			<button type="button" class="prev-button m-0 py-2 px-4 text-black border border-black">Back</button>
			<button type="button" class="next-button m-0 ml-2 py-2 px-4 text-white bg-black" style="display: none;">Continue</button>
		</div>
		<?php endif; ?>
		<div id="reserve-summary-<?= esc_attr( $event_id ); ?>" class="reserve-summary form-section hidden">
			<div class="flex items-baseline">
				<h2 class="text-2xl font-bold mb-4 mr-1">Summary</h2>
				<span>(<button type="button" class="prev-button m-0" type="button"><strong>edit</strong></button>)</span>
			</div>
			<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
				<div>
					<h3 class="font-bold font-sans">Reservation Information</h3>
					<p class="mb-1"><span id="summary-reservename-<?= esc_attr( $event_id ); ?>" class="summary-reservename"></span></p>
					<p class="mb-1"><span id="summary-email-<?= esc_attr( $event_id ); ?>" class="summary-email"></span></p>
					<p class="mb-1">Date: <span id="summary-date-<?= esc_attr( $event_id ); ?>" class="summary-date"></span></p>
					<p class="mb-1">Quantity: <span id="summary-number-<?= esc_attr( $event_id ); ?>" class="summary-number"></span></p>
					<p class="mb-1">Total Due: $<span id="summary-price-<?= esc_attr( $event_id ); ?>" class="summary-price"></span></p>
				</div>
				<?php if ( ! $is_free ) : ?>
				<div>
					<h3 class="font-bold font-sans">Billing Info</h3>
					<p class="mb-1"><span id="summary-fullname-<?= esc_attr( $event_id ); ?>" class="summary-fullname"></span></p>
					<p class="mb-1"><span id="summary-address-<?= esc_attr( $event_id ); ?>" class="summary-address"></span></p>
					<p class="mb-1"><span id="summary-citystatezip-<?= esc_attr( $event_id ); ?>" class="summary-citystatezip"></span></p>
					<p class="mb-1"><span id="summary-phone-<?= esc_attr( $event_id ); ?>" class="summary-phone"></span></p>
				</div>
				<?php endif; ?>
			</div>
			<?php if ( ! $is_free ) : ?>
			<div id="worldpay-container-<?= esc_attr( $event_id ); ?>" class="worldpay-container mb-4 min-h-[300px] relative flex justify-center" data-name="worldpayContainer">
				<span class="iframe-loader self-center absolute z-0"></span>
				<div data-name="wordpayIframe" class="z-10 w-full [&_iframe]:bg-white"></div>
			</div>
			<?php else: ?>
			<div class="success-notice mb-4" data-name="successNotice" style="padding:20px;background-color:#d9f99d;display:none;"><p class="mb-0" style="font-weight:700;font-family:sans-serif;">Thank you for your booking! See you at the event.</p></div>
			<?php endif; ?>
			<p class="mb-4">By clicking "<?php echo $is_free ? 'Reserve Now' : 'PROCESS TRANSACTION'; ?>" I agree to the <button class="open-cancellation-modal m-0" type="button"><span class="underline before:hidden after:hidden"><strong>Cancellation Policy</strong></span></button></p>
			<p class="mb-4"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" height="16" class="inline mr-1"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M144 144v48H304V144c0-44.2-35.8-80-80-80s-80 35.8-80 80zM80 192V144C80 64.5 144.5 0 224 0s144 64.5 144 144v48h16c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V256c0-35.3 28.7-64 64-64H80z"/></svg><em>Information transmitted using SSL encryption.</em></p>
			<button type="button" class="prev-button m-0 py-2 px-4 text-black border border-black">Back</button>
			<?php if ($is_free): ?>
			<button type="button" class="reserve-button m-0 ml-2 py-2 px-4 text-white bg-black" data-name="reserveButton" data-action="submitFreeBooking">Reserve Now</button>
			<?php endif; ?>
			<input type="reset" class="close-button hidden cursor-pointer m-0 ml-2 py-2 px-4 text-white bg-black" data-name="closeButton" data-action="resetForm" value="Close" />
		</div>
		<div class="errorsBox hidden mt-5 p-5 bg-red-100 border-l-8 border-l-red-800" id="errorsBox-<?= esc_attr( $event_id ); ?>">
			<h3 class="mb-2 text-xl font-sans font-bold text-red-800">Error</h3>
			<div class="body"></div>
		</div>
		<input type="hidden" id="booking-code-<?= esc_attr( $event_id ); ?>" name="booking_code" value="" />
		<input type="hidden" id="ticket-type-<?= esc_attr( $event_id ); ?>" name="ticket_type" value="<?= esc_attr($guest_option['type']); ?>" />
		<input type="hidden" id="ticket-price-<?= esc_attr( $event_id ); ?>" name="ticket_price" value="" />
		<input type="hidden" id="event-id-<?= esc_attr( $event_id ); ?>" name="event_id" value="<?= esc_attr( $event_id ); ?>" />
		<input type="hidden" id="event-date-<?= esc_attr( $event_id ); ?>" name="event_date" value="<?= esc_attr(get_field('start_date', $event_id)); ?>" />
		<input type="hidden" id="event-max-<?= esc_attr( $event_id ); ?>" name="event_max" value="<?= esc_attr(get_field('total_tickets_allowed_per_person', $event_id)); ?>" />
		<input type="hidden" id="user-id-<?= esc_attr( $event_id ); ?>" name="user_id" value="<?= esc_attr(get_current_user_id()); ?>" />
	</form>
</section>