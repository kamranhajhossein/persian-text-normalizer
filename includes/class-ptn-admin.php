<?php
/**
 * Admin screen: settings, live tester and the bulk fixer.
 *
 * @package PersianTextNormalizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tools → Persian Normalizer.
 */
class PTN_Admin {

	const SLUG  = 'persian-text-normalizer';
	const NONCE = 'ptn_bulk';
	const BATCH = 50;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_ptn_bulk', array( __CLASS__, 'ajax_bulk' ) );
		add_action( 'wp_ajax_ptn_preview', array( __CLASS__, 'ajax_preview' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PTN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * @param string[] $links Plugin row links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Settings', 'persian-text-normalizer' ) . '</a>' );
		return $links;
	}

	/**
	 * Add the Tools submenu.
	 */
	public static function menu() {
		add_management_page(
			__( 'Persian Text Normalizer', 'persian-text-normalizer' ),
			__( 'Persian Normalizer', 'persian-text-normalizer' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Labels for every setting, grouped.
	 *
	 * @return array<string,array<string,array{0:string,1:string}>>
	 */
	private static function fields() {
		return array(
			__( 'Where to normalize', 'persian-text-normalizer' ) => array(
				'on_save'     => array( __( 'Posts, pages & products on save', 'persian-text-normalizer' ), __( 'Title, content and excerpt. HTML tags, block markup, <code>/<pre> and ignored regions stay untouched.', 'persian-text-normalizer' ) ),
				'on_comments' => array( __( 'New comments & reviews', 'persian-text-normalizer' ), '' ),
				'on_terms'    => array( __( 'Categories, tags & attributes', 'persian-text-normalizer' ), '' ),
				'on_search'   => array( __( 'Search queries', 'persian-text-normalizer' ), __( 'Site and WooCommerce product search: Arabic letters, diacritics and kashida are normalized and ZWNJ is treated as a space, so «مي‌خواهم» finds «می‌خواهم».', 'persian-text-normalizer' ) ),
				'on_slugs'    => array( __( 'New URL slugs', 'persian-text-normalizer' ), __( 'Prevents duplicate URLs that differ only in ي/ی. Existing slugs are never changed.', 'persian-text-normalizer' ) ),
				'form_digits' => array( __( 'Latin digits in number fields', 'persian-text-normalizer' ), __( 'Converts ۰۹۱۲… to 0912… in phone, mobile, postcode and national-ID fields of WooCommerce (classic & block checkout), Contact Form 7, Gravity Forms and other forms — so validation, SMS gateways and payment gateways stop failing.', 'persian-text-normalizer' ) ),
			),
			__( 'What to normalize', 'persian-text-normalizer' )  => array(
				'arabic_digits'  => array( __( 'Arabic digits ٠١٢ → Persian ۰۱۲', 'persian-text-normalizer' ), __( 'Latin digits are never touched.', 'persian-text-normalizer' ) ),
				'zwnj'           => array( __( 'Clean up ZWNJ (نیم‌فاصله)', 'persian-text-normalizer' ), __( 'Removes repeated ZWNJs and ZWNJs next to spaces, punctuation or digits.', 'persian-text-normalizer' ) ),
				'teh_marbuta'    => array( __( 'ة → ه', 'persian-text-normalizer' ), __( 'Off by default: some loanwords are intentionally written with ة.', 'persian-text-normalizer' ) ),
				'strip_tashkeel' => array( __( 'Remove diacritics (اِعراب) from stored text', 'persian-text-normalizer' ), __( 'Off by default. Search always ignores diacritics.', 'persian-text-normalizer' ) ),
				'strip_tatweel'  => array( __( 'Remove kashida (ـ) from stored text', 'persian-text-normalizer' ), '' ),
			),
		);
	}

	/**
	 * Settings API registration.
	 */
	public static function register_settings() {
		register_setting(
			'ptn',
			PTN_Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => PTN_Plugin::default_settings(),
			)
		);
	}

	/**
	 * Checkboxes → booleans; unknown keys dropped.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,bool>
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( array_keys( PTN_Plugin::default_settings() ) as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}
		return $out;
	}

	/**
	 * Enqueue JS/CSS on our screen only.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ) {
		if ( 'tools_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'ptn-admin', plugins_url( 'assets/admin.css', PTN_FILE ), array(), PTN_VERSION );
		wp_enqueue_script( 'ptn-admin', plugins_url( 'assets/admin.js', PTN_FILE ), array(), PTN_VERSION, true );
		wp_localize_script(
			'ptn-admin',
			'PTN',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => array(
					'scanning'  => __( 'Scanning…', 'persian-text-normalizer' ),
					'fixing'    => __( 'Fixing…', 'persian-text-normalizer' ),
					'scanDone'  => __( 'Scan finished: %1$d of %2$d posts and %3$d terms need fixing. Nothing was changed.', 'persian-text-normalizer' ),
					'fixDone'   => __( 'Done: %1$d posts and %2$d terms were normalized.', 'persian-text-normalizer' ),
					'confirm'   => __( 'This rewrites existing content directly in the database. Take a backup first. Continue?', 'persian-text-normalizer' ),
					'error'     => __( 'Request failed. Please reload the page and try again.', 'persian-text-normalizer' ),
					'clean'     => __( 'Everything is already clean.', 'persian-text-normalizer' ),
					'edit'      => __( 'Edit', 'persian-text-normalizer' ),
					'fieldsMap' => array(
						'post_title'   => __( 'title', 'persian-text-normalizer' ),
						'post_content' => __( 'content', 'persian-text-normalizer' ),
						'post_excerpt' => __( 'excerpt', 'persian-text-normalizer' ),
					),
				),
			)
		);
	}

	/**
	 * Page markup.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = PTN_Plugin::settings();
		?>
		<div class="wrap ptn-wrap">
			<h1><?php esc_html_e( 'Persian Text Normalizer', 'persian-text-normalizer' ); ?></h1>
			<p class="ptn-lead"><?php esc_html_e( 'Fixes the Arabic «ي/ك», mixed digits and broken ZWNJs that split your search results, break checkout validation and create duplicate URLs.', 'persian-text-normalizer' ); ?></p>

			<div class="ptn-grid">
				<form method="post" action="options.php" class="ptn-card">
					<?php settings_fields( 'ptn' ); ?>
					<?php foreach ( self::fields() as $group => $items ) : ?>
						<h2><?php echo esc_html( $group ); ?></h2>
						<?php foreach ( $items as $key => $label ) : ?>
							<label class="ptn-toggle">
								<input type="checkbox" name="<?php echo esc_attr( PTN_Plugin::OPTION . '[' . $key . ']' ); ?>" value="1" <?php checked( $s[ $key ] ); ?>>
								<span class="ptn-toggle-text">
									<strong><?php echo esc_html( $label[0] ); ?></strong>
									<?php if ( $label[1] ) : ?>
										<small><?php echo esc_html( $label[1] ); ?></small>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					<?php endforeach; ?>
					<?php submit_button(); ?>
				</form>

				<div class="ptn-side">
					<div class="ptn-card">
						<h2><?php esc_html_e( 'Try it', 'persian-text-normalizer' ); ?></h2>
						<textarea id="ptn-try" dir="rtl" rows="4" placeholder="<?php esc_attr_e( 'Paste some text…', 'persian-text-normalizer' ); ?>">كتاب‌‌هاي جديد سال ١٤٠٥ ‌</textarea>
						<div class="ptn-out-label"><?php esc_html_e( 'Stored as', 'persian-text-normalizer' ); ?></div>
						<div id="ptn-try-out" class="ptn-out" dir="rtl"></div>
						<div class="ptn-out-label"><?php esc_html_e( 'Searched as', 'persian-text-normalizer' ); ?></div>
						<div id="ptn-try-search" class="ptn-out" dir="rtl"></div>
						<div class="ptn-out-label"><?php esc_html_e( 'Digits in forms', 'persian-text-normalizer' ); ?></div>
						<div id="ptn-try-digits" class="ptn-out" dir="ltr"></div>
					</div>

					<div class="ptn-card">
						<h2><?php esc_html_e( 'Fix existing content', 'persian-text-normalizer' ); ?></h2>
						<p><?php esc_html_e( 'Applies the settings above to every published post, page, product and term already on the site. Slugs, modification dates and revisions are left alone.', 'persian-text-normalizer' ); ?></p>
						<p>
							<button type="button" class="button" id="ptn-scan"><?php esc_html_e( 'Scan (dry run)', 'persian-text-normalizer' ); ?></button>
							<button type="button" class="button button-primary" id="ptn-fix"><?php esc_html_e( 'Fix everything', 'persian-text-normalizer' ); ?></button>
						</p>
						<progress id="ptn-progress" max="100" value="0" hidden></progress>
						<p id="ptn-status" aria-live="polite"></p>
						<ul id="ptn-samples"></ul>
						<p class="description"><?php echo wp_kses( __( 'Need to keep a passage untouched (e.g. a Quranic verse)? Wrap it in <code>[ptn_ignore]…[/ptn_ignore]</code> or <code>&lt;!-- ptn:ignore --&gt;…&lt;!-- /ptn:ignore --&gt;</code>. WP-CLI: <code>wp ptn fix --dry-run</code>', 'persian-text-normalizer' ), array( 'code' => array() ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Verify capability + nonce for AJAX calls.
	 */
	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
	}

	/**
	 * Live preview of the three normalizers.
	 */
	public static function ajax_preview() {
		self::guard();
		$text = isset( $_POST['text'] ) ? (string) wp_unslash( $_POST['text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$text = mb_substr( $text, 0, 5000 );
		wp_send_json_success(
			array(
				'stored' => PTN_Plugin::instance()->normalizer()->html( $text ),
				'search' => PTN_Normalizer::search( $text ),
				'digits' => PTN_Normalizer::latin_digits( $text ),
			)
		);
	}

	/**
	 * Bulk steps: start → posts (repeated) → terms.
	 */
	public static function ajax_bulk() {
		self::guard();
		$step    = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
		$dry_run = empty( $_POST['fix'] );
		$types   = PTN_Bulk::post_types();

		if ( ! $dry_run && function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		switch ( $step ) {
			case 'start':
				wp_send_json_success(
					array(
						'total' => PTN_Bulk::count_posts( $types ),
						'batch' => self::BATCH,
					)
				);
				break;
			case 'posts':
				$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
				$batch  = PTN_Bulk::posts_batch( $types, $offset, self::BATCH, $dry_run );
				foreach ( $batch['samples'] as &$sample ) {
					$sample['edit'] = get_edit_post_link( $sample['id'], 'raw' );
				}
				unset( $sample );
				wp_send_json_success( $batch );
				break;
			case 'terms':
				wp_send_json_success( PTN_Bulk::terms( $dry_run ) );
				break;
			default:
				wp_send_json_error( null, 400 );
		}
	}
}
