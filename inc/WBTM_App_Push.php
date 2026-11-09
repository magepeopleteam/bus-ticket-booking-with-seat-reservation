<?php
/** Queued Expo status notifications with bounded retry and receipt handling. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Push' ) ) {
	class WBTM_App_Push {
		const JOB_PREFIX = 'wbtm_app_push_job_';

		public function __construct() {
			add_action( 'wbtm_order_status_change', array( $this, 'queue_status' ), 50, 3 );
			add_action( 'wbtm_app_push_send', array( $this, 'send' ) );
			add_action( 'wbtm_app_push_receipts', array( $this, 'receipts' ) );
		}

		public static function enabled() {
			$settings = WBTM_App_Settings::get();
			return WBTM_App_Auth::enabled() && 'yes' === $settings['app_push_enabled'] && '' !== $settings['app_expo_project_id'];
		}

		public function queue_status( $status, $bus_id, $order_id ) {
			if ( ! self::enabled() || ! in_array( $status, array( 'processing', 'completed', 'on-hold', 'failed', 'cancelled', 'refunded', 'partially-paid' ), true ) ) {
				return;
			}
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
			$user_id = $order instanceof WC_Order ? $order->get_customer_id() : absint( get_post_meta( $order_id, 'wbtm_user_id', true ) );
			$devices = $user_id ? array_values( array_unique( get_user_meta( $user_id, WBTM_App_Account::DEVICE_META ) ) ) : array();
			if ( ! $devices ) {
				return;
			}
			$key = hash( 'sha256', $user_id . '|' . $order_id . '|' . $status );
			if ( get_transient( 'wbtm_app_push_dedupe_' . $key ) ) {
				return;
			}
			set_transient( 'wbtm_app_push_dedupe_' . $key, 1, MINUTE_IN_SECONDS );
			$id = bin2hex( random_bytes( 16 ) );
			add_option( self::JOB_PREFIX . $id, array( 'user_id' => $user_id, 'booking_id' => absint( $order_id ), 'devices' => array_slice( $devices, 0, 10 ), 'attempt' => 0, 'receipt_attempt' => 0, 'receipts' => array(), 'expires' => time() + DAY_IN_SECONDS ), '', false );
			wp_schedule_single_event( time() + 1, 'wbtm_app_push_send', array( $id ) );
		}

		private function request( $path, $body ) {
			$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
			if ( defined( 'WBTM_EXPO_ACCESS_TOKEN' ) && is_string( WBTM_EXPO_ACCESS_TOKEN ) && '' !== WBTM_EXPO_ACCESS_TOKEN ) {
				$headers['Authorization'] = 'Bearer ' . WBTM_EXPO_ACCESS_TOKEN;
			}
			// Expo's documented send/ticket/receipt protocol, with no customer PII.
			// https://docs.expo.dev/push-notifications/sending-notifications/
			$response = wp_remote_post( 'https://exp.host/--/api/v2/push/' . $path, array( 'timeout' => 10, 'headers' => $headers, 'body' => wp_json_encode( $body ) ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			return is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : false;
		}

		public function send( $id ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
				return;
			}
			WBTM_App_Auth::locked( 'push_' . $id, function () use ( $id ) {
				$job = get_option( self::JOB_PREFIX . $id, array() );
				if ( ! $job || ! self::enabled() || $job['expires'] <= time() ) {
					delete_option( self::JOB_PREFIX . $id );
					return;
				}
				$body = array();
				$keys = array();
				foreach ( $job['devices'] as $key ) {
					$device = WBTM_App_Account::device_for_push( $key, $job['user_id'] );
					if ( $device ) {
						$keys[] = $key;
						$body[] = array( 'to' => $device['token'], 'sound' => 'default', 'title' => __( 'Booking update', 'addon-bus--ticket-booking-with-seat-pro' ), 'body' => __( 'Your booking status has changed. Open the app to view the latest details.', 'addon-bus--ticket-booking-with-seat-pro' ), 'data' => array( 'booking_id' => $job['booking_id'], 'site_url' => home_url( '/' ) ), 'ttl' => 3600 );
					}
				}
				$retry = array();
				$data = $body ? $this->request( 'send', $body ) : array();
				foreach ( $keys as $index => $key ) {
					$ticket = false !== $data && isset( $data[ $index ] ) ? $data[ $index ] : array();
					if ( isset( $ticket['status'], $ticket['id'] ) && 'ok' === $ticket['status'] ) {
						$job['receipts'][ $ticket['id'] ] = $key;
					} elseif ( isset( $ticket['details']['error'] ) && 'DeviceNotRegistered' === $ticket['details']['error'] ) {
						WBTM_App_Account::remove_device( $key, $job['user_id'] );
					} else {
						$retry[] = $key;
					}
				}
				$job['devices'] = $retry;
				++$job['attempt'];
				update_option( self::JOB_PREFIX . $id, $job, false );
				if ( $retry && $job['attempt'] < 4 ) {
					wp_schedule_single_event( time() + min( 900, 30 * ( 2 ** $job['attempt'] ) ), 'wbtm_app_push_send', array( $id ) );
				} elseif ( $job['receipts'] ) {
					wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'wbtm_app_push_receipts', array( $id ) );
				} else {
					delete_option( self::JOB_PREFIX . $id );
				}
			} );
		}

		public function receipts( $id ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
				return;
			}
			WBTM_App_Auth::locked( 'push_' . $id, function () use ( $id ) {
				$job = get_option( self::JOB_PREFIX . $id, array() );
				if ( ! $job || ! self::enabled() || $job['expires'] <= time() || empty( $job['receipts'] ) ) {
					delete_option( self::JOB_PREFIX . $id );
					return;
				}
				$data = $this->request( 'getReceipts', array( 'ids' => array_keys( $job['receipts'] ) ) );
				foreach ( $job['receipts'] as $receipt_id => $key ) {
					if ( false === $data || ! isset( $data[ $receipt_id ] ) ) {
						continue;
					}
					if ( isset( $data[ $receipt_id ]['details']['error'] ) && 'DeviceNotRegistered' === $data[ $receipt_id ]['details']['error'] ) {
						WBTM_App_Account::remove_device( $key, $job['user_id'] );
					}
					unset( $job['receipts'][ $receipt_id ] );
				}
				++$job['receipt_attempt'];
				if ( $job['receipts'] && $job['receipt_attempt'] < 4 ) {
					update_option( self::JOB_PREFIX . $id, $job, false );
					wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'wbtm_app_push_receipts', array( $id ) );
				} else {
					delete_option( self::JOB_PREFIX . $id );
				}
			} );
		}
	}
	new WBTM_App_Push();
}
