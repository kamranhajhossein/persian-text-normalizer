<?php
/**
 * Batch scanning / fixing of existing content (used by the admin tool and WP-CLI).
 *
 * @package PersianTextNormalizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bulk normalizer for posts and terms already in the database.
 *
 * Writes go straight to the tables (no revisions, no modified-date bump, no save hooks firing on
 * thousands of posts). Slugs are never changed, so existing URLs and rankings are safe.
 */
class PTN_Bulk {

	/**
	 * Post types processed by default: every public type except attachments.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		if ( post_type_exists( 'wp_block' ) ) {
			$types[] = 'wp_block'; // Reusable blocks / synced patterns.
		}
		return apply_filters( 'ptn_bulk_post_types', $types );
	}

	/**
	 * Total number of posts in scope.
	 *
	 * @param string[] $types Post types.
	 * @return int
	 */
	public static function count_posts( array $types ) {
		global $wpdb;
		if ( ! $types ) {
			return 0;
		}
		$in = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status NOT IN ('auto-draft','trash')", $types ) );
	}

	/**
	 * Process one batch of posts.
	 *
	 * @param string[] $types   Post types.
	 * @param int      $offset  Row offset (by ID order).
	 * @param int      $limit   Batch size.
	 * @param bool     $dry_run Only report.
	 * @return array{processed:int,changed:int,ids:int[],samples:array}
	 */
	public static function posts_batch( array $types, $offset, $limit, $dry_run ) {
		global $wpdb;
		$result = array(
			'processed' => 0,
			'changed'   => 0,
			'ids'       => array(),
			'samples'   => array(),
		);
		if ( ! $types ) {
			return $result;
		}
		$n  = PTN_Plugin::instance()->normalizer();
		$in = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status NOT IN ('auto-draft','trash') ORDER BY ID ASC LIMIT %d OFFSET %d", array_merge( $types, array( (int) $limit, (int) $offset ) ) ) );

		foreach ( $rows as $row ) {
			++$result['processed'];
			$new = array(
				'post_title'   => $n->text( $row->post_title ),
				'post_content' => $n->html( $row->post_content ),
				'post_excerpt' => $n->html( $row->post_excerpt ),
			);
			$diff = array();
			foreach ( $new as $field => $value ) {
				if ( $value !== $row->$field ) {
					$diff[ $field ] = $value;
				}
			}
			if ( ! $diff ) {
				continue;
			}
			++$result['changed'];
			$result['ids'][] = (int) $row->ID;
			if ( count( $result['samples'] ) < 5 ) {
				$result['samples'][] = array(
					'id'     => (int) $row->ID,
					'title'  => $new['post_title'],
					'fields' => array_keys( $diff ),
				);
			}
			if ( ! $dry_run ) {
				$wpdb->update( $wpdb->posts, $diff, array( 'ID' => $row->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( (int) $row->ID );
			}
		}
		return $result;
	}

	/**
	 * Normalize all term names and descriptions (they are few, so one pass).
	 *
	 * @param bool $dry_run Only report.
	 * @return array{processed:int,changed:int}
	 */
	public static function terms( $dry_run ) {
		global $wpdb;
		$n       = PTN_Plugin::instance()->normalizer();
		$changed = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT t.term_id, t.name, tt.term_taxonomy_id, tt.taxonomy, tt.description FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id" );
		$seen = array();
		foreach ( $rows as $row ) {
			$name = $n->text( $row->name );
			$desc = $n->html( $row->description );
			$hit  = false;
			if ( $name !== $row->name && ! isset( $seen[ $row->term_id ] ) ) {
				$hit = true;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->terms, array( 'name' => $name ), array( 'term_id' => $row->term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
			}
			$seen[ $row->term_id ] = true;
			if ( $desc !== $row->description ) {
				$hit = true;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $desc ), array( 'term_taxonomy_id' => $row->term_taxonomy_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
			}
			if ( $hit ) {
				++$changed;
				if ( ! $dry_run ) {
					clean_term_cache( (int) $row->term_id, $row->taxonomy );
				}
			}
		}
		return array(
			'processed' => count( $rows ),
			'changed'   => $changed,
		);
	}
}
