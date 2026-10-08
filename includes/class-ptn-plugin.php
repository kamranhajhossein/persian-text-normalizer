<?php
/**
 * Wires the normalizer into WordPress / WooCommerce.
 *
 * @package PersianTextNormalizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
class PTN_Plugin {

	const OPTION = 'ptn_settings';

	/**
	 * Request field names (regex) that hold numbers and must use Latin digits.
	 */
	const DIGIT_FIELDS = '/(phone|mobile|tel|cell|postcode|postal|zip|national|melli|meli|code_?posti|shaba|iban|card_?number|otp|verification_?code)/i';

	/**
	 * @var PTN_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var PTN_Normalizer
	 */
	private $normalizer;

	/**
	 * @return PTN_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Feature switches shown on the settings page.
	 *
	 * @return array<string,bool>
	 */
	public static function default_settings() {
		return array(
			// Where to normalize.
			'on_save'        => true,  // Post title / content / excerpt on save.
			'on_comments'    => true,  // New comments.
			'on_terms'       => true,  // Category, tag & product attribute names.
			'on_search'      => true,  // Front-end & WooCommerce search queries.
			'on_slugs'       => true,  // New slugs (never rewrites existing URLs).
			'form_digits'    => true,  // Persian digits → Latin in phone / postcode / ID fields.
			// What to normalize in stored text.
			'arabic_digits'  => true,
			'zwnj'           => true,
			'teh_marbuta'    => false,
			'strip_tashkeel' => false,
			'strip_tatweel'  => false,
		);
	}

	/**
	 * Current settings merged over defaults.
	 *
	 * @return array<string,bool>
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::default_settings(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Normalizer configured from settings (shared by save hooks, bulk tool and WP-CLI).
	 *
	 * @return PTN_Normalizer
	 */
	public function normalizer() {
		if ( null === $this->normalizer ) {
			$s = self::settings();
			$this->normalizer = new PTN_Normalizer(
				/**
				 * Filter the normalizer options used for stored content.
				 *
				 * @param array $opts Options (letters, arabic_digits, zwnj, teh_marbuta, strip_tashkeel, strip_tatweel).
				 */
				apply_filters(
					'ptn_normalizer_options',
					array(
						'letters'        => true,
						'arabic_digits'  => (bool) $s['arabic_digits'],
						'zwnj'           => (bool) $s['zwnj'],
						'teh_marbuta'    => (bool) $s['teh_marbuta'],
						'strip_tashkeel' => (bool) $s['strip_tashkeel'],
						'strip_tatweel'  => (bool) $s['strip_tatweel'],
					)
				)
			);
		}
		return $this->normalizer;
	}

	/**
	 * Drop the cached normalizer (after settings change).
	 */
	public function reset() {
		$this->normalizer = null;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		$s = self::settings();

		add_shortcode( 'ptn_ignore', array( $this, 'shortcode_ignore' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'reset' ) );

		if ( $s['on_save'] ) {
			add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 20, 2 );
		}
		if ( $s['on_comments'] ) {
			add_filter( 'preprocess_comment', array( $this, 'filter_comment' ), 5 );
		}
		if ( $s['on_terms'] ) {
			add_filter( 'pre_insert_term', array( $this, 'filter_term_name' ), 5 );
			add_filter( 'pre_term_name', array( $this, 'filter_term_name' ), 5 );
			add_filter( 'pre_term_description', array( $this, 'filter_html' ), 5 );
		}
		if ( $s['on_search'] ) {
			add_action( 'pre_get_posts', array( $this, 'filter_search_query' ), 5 );
		}
		if ( $s['on_slugs'] ) {
			add_filter( 'sanitize_title', array( $this, 'filter_slug' ), 5, 3 );
		}
		if ( $s['form_digits'] ) {
			// Runs before WooCommerce, Contact Form 7, Gravity Forms, etc. read the request.
			add_action( 'init', array( $this, 'normalize_request_digits' ), 0 );
			// WooCommerce block checkout (Store API) posts JSON, which never reaches $_POST.
			add_filter( 'rest_request_before_callbacks', array( $this, 'normalize_rest_digits' ), 5, 3 );
		}
	}

	/**
	 * [ptn_ignore]...[/ptn_ignore] renders its content unchanged; it only shields it from normalization.
	 *
	 * @param array  $atts    Unused.
	 * @param string $content Enclosed content.
	 * @return string
	 */
	public function shortcode_ignore( $atts, $content = '' ) {
		return do_shortcode( (string) $content );
	}

	/**
	 * @param array $data    Slashed post data.
	 * @param array $postarr Raw post array.
	 * @return array
	 */
	public function filter_post_data( $data, $postarr ) {
		$skip_types = apply_filters( 'ptn_skip_post_types', array( 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'wp_global_styles', 'wp_font_family', 'wp_font_face' ) );
		if ( in_array( $data['post_type'], $skip_types, true ) ) {
			return $data;
		}
		$n = $this->normalizer();
		foreach ( array( 'post_title' => 'text', 'post_excerpt' => 'html', 'post_content' => 'html' ) as $field => $mode ) {
			if ( isset( $data[ $field ] ) && '' !== $data[ $field ] ) {
				$data[ $field ] = wp_slash( $n->$mode( wp_unslash( $data[ $field ] ) ) );
			}
		}
		return $data;
	}

	/**
	 * @param array $comment Comment data.
	 * @return array
	 */
	public function filter_comment( $comment ) {
		$n = $this->normalizer();
		if ( isset( $comment['comment_content'] ) ) {
			$comment['comment_content'] = $n->html( $comment['comment_content'] );
		}
		if ( isset( $comment['comment_author'] ) ) {
			$comment['comment_author'] = $n->text( $comment['comment_author'] );
		}
		return $comment;
	}

	/**
	 * @param string|WP_Error $name Term name.
	 * @return string|WP_Error
	 */
	public function filter_term_name( $name ) {
		return is_string( $name ) ? $this->normalizer()->text( $name ) : $name;
	}

	/**
	 * @param string $html Markup.
	 * @return string
	 */
	public function filter_html( $html ) {
		return $this->normalizer()->html( $html );
	}

	/**
	 * Normalize the search phrase of front-end queries (also covers WooCommerce product search).
	 *
	 * @param WP_Query $query Query.
	 */
	public function filter_search_query( $query ) {
		if ( ! $query->is_search() && '' === (string) $query->get( 's' ) ) {
			return;
		}
		if ( is_admin() && ! wp_doing_ajax() && ! apply_filters( 'ptn_normalize_admin_search', true ) ) {
			return;
		}
		$s = $query->get( 's' );
		if ( is_string( $s ) && '' !== $s ) {
			$query->set( 's', PTN_Normalizer::search( $s ) );
		}
	}

	/**
	 * Normalize slugs only when they are being created ("save" context). Query-context lookups are left alone
	 * so existing URLs that contain Arabic letters keep resolving.
	 *
	 * @param string $title     Sanitized title so far.
	 * @param string $raw_title Original title.
	 * @param string $context   'save' or 'query'.
	 * @return string
	 */
	public function filter_slug( $title, $raw_title = '', $context = 'display' ) {
		if ( 'save' !== $context || ! is_string( $title ) ) {
			return $title;
		}
		// Slugs: letters only + drop ZWNJ in favour of a dash, never touch Latin text.
		$slug = ( new PTN_Normalizer( array( 'zwnj' => false ) ) )->text( $title );
		return str_replace( PTN_Normalizer::ZWNJ, '-', $slug );
	}

	/**
	 * Convert Persian/Arabic digits to Latin in request fields that hold numbers (phone, mobile, postcode, national ID…).
	 */
	public function normalize_request_digits() {
		if ( empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		/**
		 * Regex matched against POST field names whose digits should become Latin.
		 *
		 * @param string $pattern PCRE pattern.
		 */
		$pattern = apply_filters( 'ptn_digit_fields_pattern', self::DIGIT_FIELDS );

		// phpcs:disable WordPress.Security.NonceVerification
		$_POST = $this->walk_digits( $_POST, $pattern );
		foreach ( $_POST as $k => $v ) {
			if ( isset( $_REQUEST[ $k ] ) ) {
				$_REQUEST[ $k ] = $v;
			}
		}
		// phpcs:enable
	}

	/**
	 * Same digit conversion for JSON REST requests (WooCommerce Store API checkout, cart address updates, …).
	 *
	 * @param mixed           $response Pre-dispatch response.
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public function normalize_rest_digits( $response, $handler, $request ) {
		$routes = apply_filters( 'ptn_digit_rest_routes', array( '/wc/store' ) );
		$route  = $request->get_route();
		$hit    = false;
		foreach ( (array) $routes as $prefix ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				$hit = true;
				break;
			}
		}
		if ( ! $hit ) {
			return $response;
		}
		$pattern = apply_filters( 'ptn_digit_fields_pattern', self::DIGIT_FIELDS );
		$json    = $request->get_json_params();
		if ( is_array( $json ) ) {
			foreach ( $this->walk_digits( $json, $pattern ) as $key => $value ) {
				if ( $value !== $json[ $key ] ) {
					$request->set_param( $key, $value );
				}
			}
		}
		return $response;
	}

	/**
	 * Recursively convert digits in matching keys. WooCommerce Blocks/Store API sends JSON instead (handled separately).
	 *
	 * @param array  $data    Request data.
	 * @param string $pattern Field-name regex.
	 * @param bool   $inherit Parent key already matched.
	 * @return array
	 */
	private function walk_digits( array $data, $pattern, $inherit = false ) {
		foreach ( $data as $key => $value ) {
			$match = $inherit || preg_match( $pattern, (string) $key );
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->walk_digits( $value, $pattern, $match );
			} elseif ( $match && is_string( $value ) ) {
				$data[ $key ] = PTN_Normalizer::latin_digits( $value );
			}
		}
		return $data;
	}
}
