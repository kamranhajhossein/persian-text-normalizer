<?php
/**
 * WP-CLI commands.
 *
 * @package PersianTextNormalizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize Persian text from the command line.
 */
class PTN_CLI {

	/**
	 * Normalize existing posts and terms.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * [--post_type=<types>]
	 * : Comma-separated post types. Defaults to all public types.
	 *
	 * [--skip-terms]
	 * : Do not touch category/tag/attribute names.
	 *
	 * [--batch=<size>]
	 * : Rows per batch.
	 * ---
	 * default: 200
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ptn fix --dry-run
	 *     wp ptn fix --post_type=product,page
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	public function fix( $args, $assoc_args ) {
		$dry   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$types = isset( $assoc_args['post_type'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc_args['post_type'] ) ) ) : PTN_Bulk::post_types();
		$size  = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch', 200 ) );
		$total = PTN_Bulk::count_posts( $types );

		$progress = \WP_CLI\Utils\make_progress_bar( $dry ? 'Scanning posts' : 'Fixing posts', $total );
		$changed  = 0;
		$ids      = array();
		for ( $offset = 0; $offset < $total; $offset += $size ) {
			$r        = PTN_Bulk::posts_batch( $types, $offset, $size, $dry );
			$changed += $r['changed'];
			$ids      = array_merge( $ids, $r['ids'] );
			$progress->tick( $r['processed'] );
			if ( $r['processed'] < $size ) {
				break;
			}
		}
		$progress->finish();

		if ( $dry && $ids ) {
			\WP_CLI::log( 'Posts that would change: ' . implode( ', ', array_slice( $ids, 0, 50 ) ) . ( count( $ids ) > 50 ? ' …' : '' ) );
		}

		$terms = array( 'changed' => 0 );
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-terms', false ) ) {
			$terms = PTN_Bulk::terms( $dry );
		}

		$verb = $dry ? 'would be normalized' : 'normalized';
		\WP_CLI::success( sprintf( '%d of %d posts and %d terms %s.', $changed, $total, $terms['changed'], $verb ) );
	}

	/**
	 * Show how a string is normalized.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : Text to normalize.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ptn test "كتاب‌هاي ١٤٠٥"
	 *
	 * @param array $args Positional.
	 */
	public function test( $args ) {
		$text = (string) $args[0];
		\WP_CLI::log( 'stored : ' . PTN_Plugin::instance()->normalizer()->html( $text ) );
		\WP_CLI::log( 'search : ' . PTN_Normalizer::search( $text ) );
		\WP_CLI::log( 'digits : ' . PTN_Normalizer::latin_digits( $text ) );
	}
}
