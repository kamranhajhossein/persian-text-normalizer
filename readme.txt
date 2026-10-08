=== Persian Text Normalizer ===
Contributors: kamranhajhossein
Tags: persian, farsi, woocommerce, search, normalizer
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fixes Arabic «ي/ك», mixed digits and broken ZWNJs across posts, products, search, slugs and checkout forms.

== Description ==

* Normalizes Arabic yeh/kaf to Persian, Arabic-Indic digits to Persian and cleans ZWNJs on save (text nodes only).
* Search ignores letter variants, diacritics and kashida; ZWNJ is treated as a space.
* Converts Persian/Arabic digits to Latin in phone, postcode and national-ID fields (WooCommerce classic & block checkout, CF7, Gravity Forms).
* Normalizes new slugs without ever changing existing URLs.
* Bulk fixer with dry run (Tools → Persian Normalizer) and WP-CLI: `wp ptn fix --dry-run`.
* Keep passages untouched with `[ptn_ignore]…[/ptn_ignore]` or `<!-- ptn:ignore -->…<!-- /ptn:ignore -->`.

== Changelog ==

= 1.0.0 =
* First release.
