<?php

namespace Appneck\Sdk\Queue;

/**
 * The local buffer between a plugin calling track() and the SDK actually
 * sending anything.
 *
 * It exists so track() never makes an HTTP call. A plugin author calling
 * $sdk->track( 'booking_created', … ) inside a page load must not add
 * network latency to a site they have embedded this in — the event is
 * written locally and a scheduled flush ships it later.
 *
 * An interface so the signing/flush logic can be tested without a
 * database, and so a plugin with unusual storage needs can substitute
 * its own.
 */
interface EventQueue {

	/**
	 * @param string               $type    heartbeat|custom_event|error_report
	 * @param array<string, mixed> $payload
	 * @param string|null          $occurred_at ISO-8601; now if omitted.
	 * @return bool False if the event could not be stored.
	 */
	public function push( $type, array $payload, $occurred_at = null );

	/**
	 * Oldest first — the order they happened, which is the order the
	 * backend should receive them in.
	 *
	 * @param int $limit
	 * @return array<int, array{id: mixed, type: string, payload: array, occurred_at: string}>
	 */
	public function take( $limit );

	/**
	 * Removes events by the ids returned from take(). Called for events
	 * the server accepted AND for ones it permanently rejected — both are
	 * resolved; only unresolved events stay queued.
	 *
	 * @param array<int, mixed> $ids
	 * @return int Number removed.
	 */
	public function forget( array $ids );

	/** @return int */
	public function count();

	/** Drops everything. Used at uninstall. */
	public function purge();
}
