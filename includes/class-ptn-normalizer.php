<?php
/**
 * Pure text normalization routines. No WordPress dependency, so it can be unit-tested in isolation.
 *
 * @package PersianTextNormalizer
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PTN_TESTING' ) ) {
	exit;
}

/**
 * Normalizes Persian text: Arabic letter variants, digits, ZWNJ and diacritics.
 */
class PTN_Normalizer {

	const ZWNJ = "\u{200C}";

	/**
	 * Arabic letter variants => Persian letters.
	 *
	 * @var array<string,string>
	 */
	const LETTERS = array(
		"\u{064A}" => "\u{06CC}", // ي Arabic Yeh            => ی
		"\u{0649}" => "\u{06CC}", // ى Alef Maksura          => ی
		"\u{0643}" => "\u{06A9}", // ك Arabic Kaf            => ک
	);

	/**
	 * Arabic-Indic digits (٠-٩).
	 *
	 * @var string[]
	 */
	const ARABIC_DIGITS = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );

	/**
	 * Persian (Extended Arabic-Indic) digits (۰-۹).
	 *
	 * @var string[]
	 */
	const PERSIAN_DIGITS = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

	/**
	 * Segments of HTML that must never be modified.
	 * Order matters: explicit ignore markers first, then raw-text elements, then comments and tags.
	 */
	const PROTECTED_PATTERN = '~<!--\s*ptn:ignore\s*-->.*?<!--\s*/ptn:ignore\s*-->'
		. '|\[ptn_ignore\].*?\[/ptn_ignore\]'
		. '|<(script|style|pre|code|textarea)\b[^>]*>.*?</\1\s*>'
		. '|<!--.*?-->'
		. '|<[^>]+>~is';

	/**
	 * Active options.
	 *
	 * @var array<string,bool>
	 */
	private $opts;

	/**
	 * @param array<string,bool> $opts Overrides for self::defaults().
	 */
	public function __construct( array $opts = array() ) {
		$this->opts = array_merge( self::defaults(), $opts );
	}

	/**
	 * Default normalization switches for stored content.
	 *
	 * @return array<string,bool>
	 */
	public static function defaults() {
		return array(
			'letters'        => true,  // ي/ك/ى → ی/ک.
			'arabic_digits'  => true,  // ٠-٩ → ۰-۹ (keeps Latin digits untouched).
			'zwnj'           => true,  // Clean up duplicated / misplaced ZWNJ.
			'teh_marbuta'    => false, // ة → ه (off: changes spelling of some loanwords).
			'strip_tashkeel' => false, // Remove harakat (off for content, on for search).
			'strip_tatweel'  => false, // Remove kashida ـ.
		);
	}

	/**
	 * Normalize plain text (no HTML awareness).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	public function text( $text ) {
		if ( ! is_string( $text ) || '' === $text || ! $this->has_arabic_script( $text ) ) {
			return $text;
		}

		$o = $this->opts;

		if ( $o['letters'] ) {
			$text = strtr( $text, self::LETTERS );
		}
		if ( $o['teh_marbuta'] ) {
			$text = str_replace( "\u{0629}", "\u{0647}", $text );
		}
		if ( $o['arabic_digits'] ) {
			$text = str_replace( self::ARABIC_DIGITS, self::PERSIAN_DIGITS, $text );
		}
		if ( $o['strip_tashkeel'] ) {
			// Fathatan..Sukun, extra marks, superscript alef. Keeps U+0654 (hamza above) which is meaningful in هٔ.
			$text = preg_replace( '/[\x{064B}-\x{0653}\x{0655}-\x{065F}\x{0670}]/u', '', $text );
		}
		if ( $o['strip_tatweel'] ) {
			$text = str_replace( "\u{0640}", '', $text );
		}
		if ( $o['zwnj'] ) {
			$text = self::clean_zwnj( $text );
		}

		return $text;
	}

	/**
	 * Normalize HTML, touching only text nodes. Tags, attributes, comments (incl. Gutenberg block
	 * delimiters), <script>/<style>/<pre>/<code>/<textarea> and explicit ignore regions are preserved byte-for-byte.
	 *
	 * @param string $html Input.
	 * @return string
	 */
	public function html( $html ) {
		if ( ! is_string( $html ) || '' === $html || ! $this->has_arabic_script( $html ) ) {
			return $html;
		}
		if ( false === strpos( $html, '<' ) && false === strpos( $html, '[ptn_ignore' ) ) {
			return $this->text( $html );
		}

		if ( ! preg_match_all( self::PROTECTED_PATTERN, $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return $this->text( $html );
		}

		$out    = '';
		$cursor = 0;
		foreach ( $m[0] as $match ) {
			list( $segment, $offset ) = $match;
			if ( $offset > $cursor ) {
				$out .= $this->text( substr( $html, $cursor, $offset - $cursor ) );
			}
			$out   .= $segment;
			$cursor = $offset + strlen( $segment );
		}
		if ( $cursor < strlen( $html ) ) {
			$out .= $this->text( substr( $html, $cursor ) );
		}

		return $out;
	}

	/**
	 * Normalize a search phrase for maximum recall: letters, diacritics, kashida, and ZWNJ → space
	 * so "می‌خواهم", "میخواهم" and "می خواهم" style queries find each other via WP's AND-of-terms search.
	 *
	 * @param string $query Raw search string.
	 * @return string
	 */
	public static function search( $query ) {
		if ( ! is_string( $query ) || '' === $query ) {
			return $query;
		}
		$n = new self(
			array(
				'letters'        => true,
				'arabic_digits'  => true,
				'zwnj'           => false,
				'strip_tashkeel' => true,
				'strip_tatweel'  => true,
			)
		);
		$query = $n->text( $query );
		// Ezafe/hamza: "خانهٔ" and "خانۀ" should match "خانه".
		$query = str_replace( array( "\u{06C0}", "\u{0654}" ), array( "\u{0647}", '' ), $query );
		$query = str_replace( array( self::ZWNJ, "\u{200F}", "\u{200E}", "\u{FEFF}" ), array( ' ', '', '', '' ), $query );
		return trim( preg_replace( '/\s+/u', ' ', $query ) );
	}

	/**
	 * Convert Persian and Arabic-Indic digits to Latin 0-9 (for phone numbers, postcodes, national IDs).
	 *
	 * @param string $value Input.
	 * @return string
	 */
	public static function latin_digits( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		$latin = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$value = str_replace( self::PERSIAN_DIGITS, $latin, $value );
		return str_replace( self::ARABIC_DIGITS, $latin, $value );
	}

	/**
	 * Tidy zero-width non-joiners: collapse repeats, drop them next to whitespace/punctuation or at edges,
	 * and drop ZWNJ after letters that never join forward (where it has no visual effect).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	public static function clean_zwnj( $text ) {
		if ( false === strpos( $text, self::ZWNJ ) ) {
			return $text;
		}
		$z    = self::ZWNJ;
		$text = preg_replace( "/{$z}{2,}/u", $z, $text );
		// Next to whitespace (whitespace itself is kept) or at string boundaries.
		$text = preg_replace( "/{$z}(?=\s)|(?<=\s){$z}/u", '', $text );
		$text = preg_replace( "/^{$z}|{$z}$/u", '', $text );
		// Before/after punctuation and digits.
		$text = preg_replace( "/{$z}(?=[\p{P}\p{N}])|(?<=[\p{P}\p{N}]){$z}/u", '', $text );
		return $text;
	}

	/**
	 * Whether the string contains any Arabic-script code point (fast path to skip Latin-only content).
	 *
	 * @param string $text Input.
	 * @return bool
	 */
	private function has_arabic_script( $text ) {
		return 1 === preg_match( '/[\x{0600}-\x{06FF}\x{200C}]/u', $text );
	}
}
