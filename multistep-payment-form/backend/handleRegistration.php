<?php

class RegisterBooking {
	public function createReservation($user_id, $reserve_date, $confirmation, $eventType = 'reservation', $eventId = null) {
		$args = array(
			'post_title' => $confirmation,
			'post_type' => $eventType,
			'post_status' => 'publish',
		);

		$booking_id = wp_insert_post($args);

		if ($booking_id) {
			if ($eventType === 'reservation') {
				update_field('reserve_date', $reserve_date, $booking_id);
			}

			if ($eventType === 'event-reservation') {
				update_field('event_date', $reserve_date, $booking_id);
				update_field('event_id', $eventId, $booking_id);
			}

			update_field('user_id', $user_id, $booking_id);
		}
		
		return $booking_id;
	}

	public function getReservations($user_id, $date = null) {
		$restrictions = get_field('enabledisable_button', 'option');

		$meta_args = [
			[
				'key' => 'user_id',
				'value' => $user_id,
			],
		];

		if ($restrictions === 'allusers' && $date !== null) {
			//phpcs:ignore
			$format_date = date('Ymd', strtotime($date));

			$meta_args[] = [
				'key' => 'reserve_date',
				'value' => $format_date,
			];
		}

		$args = array(
			'post_type' => 'reservation',
			'posts_per_page' => -1,
			'meta_query' => $meta_args,
		);

		$book = new WP_Query($args);

		if ($book->have_posts()) {
			return count($book->posts);
		}

		return 0;
	}

	public function getEventReservations($user_id, $date, $event_id) {
		$meta_args = [
			[
				'key' => 'user_id',
				'value' => $user_id,
			],
			[
				'key' => 'event_date',
				'value' => $date,
			],
			[
				'key' => 'event_id',
				'value' => $event_id,
			]
		];

		$args = array(
			'post_type' => 'event-reservation',
			'posts_per_page' => -1,
			'meta_query' => $meta_args,
		);

		$book = new WP_Query($args);

		if ($book->have_posts()) {
			return count($book->posts);
		}

		return 0;
	}
}