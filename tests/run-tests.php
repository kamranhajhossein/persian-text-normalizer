<?php
/**
 * Dependency-free test runner for PTN_Normalizer.  Run: php tests/run-tests.php
 *
 * @package PersianTextNormalizer
 */

define( 'PTN_TESTING', true );
require __DIR__ . '/../includes/class-ptn-normalizer.php';

$failures = 0;
$count    = 0;

/**
 * @param string $name     Test label.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 */
function check( $name, $expected, $actual ) {
	global $failures, $count;
	++$count;
	if ( $expected === $actual ) {
		echo "  ✓ {$name}\n";
		return;
	}
	++$failures;
	echo "  ✗ {$name}\n    expected: " . var_export( $expected, true ) . "\n    actual:   " . var_export( $actual, true ) . "\n";
}

$z = "\u{200C}";
$n = new PTN_Normalizer();

echo "Letters & digits\n";
check( 'Arabic yeh/kaf → Persian', 'کتاب یک', $n->text( 'كتاب يك' ) );
check( 'Alef maksura → yeh', 'موسی', $n->text( 'موسى' ) );
check( 'Arabic-Indic digits → Persian digits', 'سال ۱۴۰۵', $n->text( 'سال ١٤٠٥' ) );
check( 'Latin digits untouched', 'سال 2026', $n->text( 'سال 2026' ) );
check( 'Latin-only text returned as-is', 'Hello World', $n->text( 'Hello World' ) );
check( 'Teh marbuta untouched by default', 'دائرة', $n->text( 'دائرة' ) );
check( 'Teh marbuta → heh when enabled', 'دائره', ( new PTN_Normalizer( array( 'teh_marbuta' => true ) ) )->text( 'دائرة' ) );

echo "ZWNJ\n";
check( 'Collapse repeated ZWNJ', "می{$z}خواهم", $n->text( "می{$z}{$z}{$z}خواهم" ) );
check( 'Drop ZWNJ next to space', 'سلام دنیا', $n->text( "سلام{$z} {$z}دنیا" ) );
check( 'Keep newlines when dropping ZWNJ', "الف\nب", $n->text( "الف{$z}\nب" ) );
check( 'Drop ZWNJ at edges', 'کتاب', $n->text( "{$z}کتاب{$z}" ) );
check( 'Drop ZWNJ before punctuation', 'کتاب‌ها.', $n->text( "کتاب{$z}ها{$z}." ) );
check( 'Keep valid ZWNJ', "کتاب{$z}ها", $n->text( "کتاب{$z}ها" ) );

echo "HTML safety\n";
check(
	'Only text nodes change',
	'<p class="يك"><a href="/كتاب">کتاب</a> یک</p>',
	$n->html( '<p class="يك"><a href="/كتاب">كتاب</a> يك</p>' )
);
check(
	'Gutenberg block comments preserved',
	'<!-- wp:paragraph {"x":"ي"} --><p>ی</p><!-- /wp:paragraph -->',
	$n->html( '<!-- wp:paragraph {"x":"ي"} --><p>ي</p><!-- /wp:paragraph -->' )
);
check( '<code> and <pre> preserved', '<code>ك</code> ک <pre>ي</pre>', $n->html( '<code>ك</code> ك <pre>ي</pre>' ) );
check( 'ptn:ignore comment region preserved', 'ی <!-- ptn:ignore --><p>بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</p><!-- /ptn:ignore --> ی', $n->html( 'ي <!-- ptn:ignore --><p>بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</p><!-- /ptn:ignore --> ي' ) );
check( '[ptn_ignore] shortcode preserved', 'ی [ptn_ignore]ي[/ptn_ignore]', $n->html( 'ي [ptn_ignore]ي[/ptn_ignore]' ) );
check( 'Plain text through html()', 'یک', $n->html( 'يك' ) );

echo "Search\n";
check( 'ZWNJ becomes space', 'می خواهم', PTN_Normalizer::search( "مي{$z}خواهم" ) );
check( 'Tashkeel and tatweel stripped', 'محمد', PTN_Normalizer::search( 'مُحَمَّـــد' ) );
check( 'Ezafe hamza stripped', 'خانه من', PTN_Normalizer::search( 'خانهٔ من' ) );
check( 'Precomposed heh-hamza', 'خانه', PTN_Normalizer::search( 'خانۀ' ) );
check( 'Whitespace collapsed', 'کفش ورزشی', PTN_Normalizer::search( "  كفش   ورزشي " ) );

echo "Latin digits\n";
check( 'Persian digits', '09121234567', PTN_Normalizer::latin_digits( '۰۹۱۲۱۲۳۴۵۶۷' ) );
check( 'Arabic-Indic digits', '1234567890', PTN_Normalizer::latin_digits( '١٢٣٤٥٦٧٨٩٠' ) );
check( 'Mixed', '+98 912-000', PTN_Normalizer::latin_digits( '+۹8 ٩۱2-۰٠0' ) );

echo "\n{$count} checks, {$failures} failed\n";
exit( $failures ? 1 : 0 );
