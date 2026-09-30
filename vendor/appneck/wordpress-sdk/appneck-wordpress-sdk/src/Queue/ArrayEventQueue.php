<?php

namespace Appneck\Sdk\Queue;

/**
 * In-memory queue with the same semantics as TableEventQueue, including
 * the drop-oldest cap. Shipped rather than kept in tests/ so the package
 * can be exercised with no database — and so a plugin author can try the
 * SDK in WP-CLI without touching their site's tables.
 */
final class ArrayEventQueue implements EventQueue {

	/** @var int */
	private $max_events;

	/** @var array<int, array<string, mixed>> */
	private $events = array();

	/** @var int */
	private $next_id = 1;

	public function __construct( $max_events = TableEventQueue::MAX_EVENTS ) {
		$this->max_events = (int) $max_events;
	}

	public function push( $type, array $payload, $occurred_at = null ) {
		$this->events[] = array(
			'id'          => $this->next_id++,
			'type'        => (string) $type,
			'payload'     => $payload,
			'occurred_at' => null !== $occurred_at ? (string) $occurred_at : gmdate( 'c' ),
		);

		// Drop oldest — see TableEventQueue::enforce_cap for why.
		while ( count( $this->events ) > $this->max_events ) {
			array_shift( $this->events );
		}

		return true;
	}

	public function take( $limit ) {
		return array_slice( $this->events, 0, (int) $limit );
	}

	public function forget( array $ids ) {
		$before = count( $this->events );

		$this->events = array_values(
			array_filter(
				$this->events,
				static function ( array $event ) use ( $ids ) {
					return ! in_array( $event['id'], $ids, false );
				}
			)
		);

		return $before - count( $this->events );
	}

	public function count() {
		return count( $this->events );
	}

	public function purge() {
		$this->events = array();
	}

	/** Test helper: the raw contents, oldest first. */
	public function all() {
		return $this->events;
	}
}
