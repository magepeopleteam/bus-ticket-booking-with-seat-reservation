<?php
/** Expiring unpaid app reservations without resurrecting expired inventory. */
if ( ! defined( 'ABSPATH' ) ) { die; }

if ( ! class_exists( 'WBTM_App_Lifecycle' ) ) {
	class WBTM_App_Lifecycle {
		const DEADLINE = '_wbtm_app_payment_deadline';
		const EXPIRED  = '_wbtm_app_expired';
		const CRON     = 'wbtm_app_expire_booking';
		private static $save_locks = array();

		public function __construct() {
			add_action( self::CRON, array( __CLASS__, 'expire' ) );
			add_action( 'init', array( $this, 'register_status' ) );
			add_filter( 'wc_order_statuses', array( $this, 'order_statuses' ) );
			add_action( 'woocommerce_before_order_object_save', array( __CLASS__, 'guard_late_payment' ), 1 );
			add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'finish_payment_save' ), PHP_INT_MAX, 4 );
			add_action( 'shutdown', array( __CLASS__, 'release_failed_saves' ), PHP_INT_MAX );
		}

		public function register_status() {
			register_post_status( 'wc-wbtm-review', array( 'label' => __( 'Payment review', 'addon-bus--ticket-booking-with-seat-pro' ), 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'Payment review <span class="count">(%s)</span>', 'Payment review <span class="count">(%s)</span>', 'addon-bus--ticket-booking-with-seat-pro' ) ) );
		}
		public function order_statuses( $statuses ) { $statuses['wc-wbtm-review'] = __( 'Payment review', 'addon-bus--ticket-booking-with-seat-pro' ); return $statuses; }

		public static function schedule( $id ) {
			$settings = WBTM_App_Settings::get();
			$deadline = time() + max( 5, min( 120, (int) ( $settings['app_payment_timeout_minutes'] ?? 15 ) ) ) * MINUTE_IN_SECONDS;
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : false;
			if ( $order ) { $order->update_meta_data( self::DEADLINE, $deadline ); $order->save_meta_data(); }
			foreach ( self::rows( $id ) as $row ) { update_post_meta( $row->ID, '_wbtm_app', 1 ); update_post_meta( $row->ID, self::DEADLINE, $deadline ); }
			wp_schedule_single_event( $deadline, self::CRON, array( (int) $id ) );
		}

		private static function rows( $id ) {
			return get_posts( array( 'post_type' => array( 'wbtm_bus_booking', 'wbtm_service_booking' ), 'post_status' => 'publish', 'posts_per_page' => 100, 'meta_key' => 'wbtm_order_id', 'meta_value' => absint( $id ) ) );
		}

		/** Lazy cleanup keeps inventory correct when a low-traffic site's cron is late. */
		public static function expire_bus( $bus_id ) {
			$posts = get_posts( array( 'post_type' => 'wbtm_bus_booking', 'post_status' => 'publish', 'posts_per_page' => 100, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'wbtm_bus_id', 'value' => absint( $bus_id ) ), array( 'key' => '_wbtm_app', 'value' => 1 ), array( 'key' => 'wbtm_order_status', 'value' => 'pending' ), array( 'key' => self::DEADLINE, 'value' => time(), 'compare' => '<=', 'type' => 'NUMERIC' ) ) ) );
			$ids = array(); foreach ( $posts as $post_id ) { $ids[ (int) get_post_meta( $post_id, 'wbtm_order_id', true ) ] = true; }
			foreach ( array_keys( $ids ) as $id ) { if ( $id ) { self::expire( $id ); } }
		}

		public static function expire( $id ) {
			$rows = self::rows( $id ); if ( ! $rows ) { return false; }
			$bus_ids = array();
			foreach ( $rows as $row ) { $bus_ids[] = (int) get_post_meta( $row->ID, 'wbtm_bus_id', true ); }
			$bus_ids = array_values( array_unique( $bus_ids ) ); sort( $bus_ids, SORT_NUMERIC ); $locks = array();
			try {
				foreach ( $bus_ids as $bus_id ) {
					if ( ! WBTM_Cart_Helper::acquire_trip_lock( $bus_id, '', '', '', 5 ) ) { return false; }
					$locks[] = $bus_id;
				}
				$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : false;
				$head = $rows[0]->ID;
				$deadline = $order ? (int) $order->get_meta( self::DEADLINE ) : (int) get_post_meta( $head, self::DEADLINE, true );
				$app = $order ? $order->get_meta( '_wbtm_app' ) : get_post_meta( $head, '_wbtm_app', true );
				$status = $order ? $order->get_status() : get_post_meta( $head, 'wbtm_order_status', true );
				if ( ! $app || ! $deadline || $deadline > time() || 'pending' !== $status ) { return false; }
				// Offline payment is an operator commitment, not an online hold timer.
				if ( $order && in_array( $order->get_payment_method(), array( 'bacs', 'cheque', 'cod' ), true ) ) { return false; }
				if ( ! $order && 'offline' === get_post_meta( $head, 'wbtm_payment_method', true ) ) { return false; }
				foreach ( $rows as $row ) { update_post_meta( $row->ID, self::EXPIRED, time() ); update_post_meta( $row->ID, 'wbtm_order_status', 'cancelled' ); }
				if ( $order ) { $order->update_meta_data( self::EXPIRED, time() ); $order->update_status( 'cancelled', 'The unpaid app reservation expired. A later verified payment requires reconciliation.' ); }
				WBTM_Query::flush_booked_map();
				wp_clear_scheduled_hook( self::CRON, array( (int) $id ) );
				return true;
			} finally { foreach ( array_reverse( $locks ) as $bus_id ) { WBTM_Cart_Helper::release_trip_lock( $bus_id, '', '', '' ); } }
		}

		/** Preserve transaction evidence, but never re-reserve seats after timeout. */
		public static function guard_late_payment( $order ) {
			if ( ! $order instanceof WC_Order || ! $order->get_meta( '_wbtm_app' ) ) { return; }
			if ( ! in_array( $order->get_status( 'edit' ), array( 'processing', 'completed', 'on-hold', 'partially-paid' ), true ) ) { return; }
			if ( ! array_key_exists( 'status', $order->get_changes() ) ) { return; }
			$rows = self::rows( $order->get_id() ); $bus_ids = array(); $locks = array();
			foreach ( $rows as $row ) { $bus_ids[] = (int) get_post_meta( $row->ID, 'wbtm_bus_id', true ); }
			$bus_ids = array_unique( $bus_ids ); sort( $bus_ids, SORT_NUMERIC );
			$retained = false;
			try {
				foreach ( $bus_ids as $bus_id ) {
					if ( ! WBTM_Cart_Helper::acquire_trip_lock( $bus_id, '', '', '', 5 ) ) { throw new RuntimeException( 'Inventory is busy. Payment requires reconciliation.' ); }
					$locks[] = $bus_id;
				}
				// The gateway can hold an object loaded BEFORE cron cancelled the order.
				// Always merge the persisted marker, not the incoming object's old meta.
				$persisted = wc_get_order( $order->get_id() );
				if ( $persisted ) { $persisted->read_meta_data( true ); }
				$expired = $persisted ? (int) $persisted->get_meta( self::EXPIRED ) : 0;
				$deadline = $persisted ? (int) $persisted->get_meta( self::DEADLINE ) : 0;
				if ( ! $expired && $deadline && $deadline <= time() ) {
					self::expire( $order->get_id() );
					$persisted = wc_get_order( $order->get_id() ); if ( $persisted ) { $persisted->read_meta_data( true ); $expired = (int) $persisted->get_meta( self::EXPIRED ); }
				}
				if ( $expired ) {
					$order->update_meta_data( self::EXPIRED, $expired );
					$order->set_status( 'wbtm-review' );
					$order->update_meta_data( '_wbtm_app_payment_review', 'A payment arrived after the seat reservation expired. Check gateway funds and arrange a refund or a new booking manually.' );
				}
				// WC_Order::save() writes data THEN runs status_transition(). The lock
				// must span both operations, not just this pre-save inspection hook.
				self::$save_locks[ spl_object_id( $order ) ][] = $locks; $retained = true;
			} finally { if ( ! $retained ) { foreach ( array_reverse( $locks ) as $bus_id ) { WBTM_Cart_Helper::release_trip_lock( $bus_id, '', '', '' ); } } }
		}

		public static function finish_payment_save( $id, $from, $to, $order ) {
			$key = spl_object_id( $order );
			if ( empty( self::$save_locks[ $key ] ) ) { return; }
			$locks = array_pop( self::$save_locks[ $key ] );
			foreach ( array_reverse( $locks ) as $bus_id ) { WBTM_Cart_Helper::release_trip_lock( $bus_id, '', '', '' ); }
			if ( ! self::$save_locks[ $key ] ) { unset( self::$save_locks[ $key ] ); }
		}
		public static function release_failed_saves() {
			foreach ( self::$save_locks as $frames ) { foreach ( array_reverse( $frames ) as $locks ) { foreach ( array_reverse( $locks ) as $bus_id ) { WBTM_Cart_Helper::release_trip_lock( $bus_id, '', '', '' ); } } }
			self::$save_locks = array();
		}

		public static function standalone_status( $id, $status ) {
			if ( in_array( $status, array( 'processing', 'completed', 'on-hold' ), true ) ) { self::expire( $id ); }
			if ( in_array( $status, array( 'processing', 'completed', 'on-hold' ), true ) && get_post_meta( $id, self::EXPIRED, true ) ) {
				update_post_meta( $id, '_wbtm_app_payment_review', 'A payment arrived after the seat reservation expired. Operator reconciliation required.' );
				return 'payment-review';
			}
			return $status;
		}
	}
	new WBTM_App_Lifecycle();
}
