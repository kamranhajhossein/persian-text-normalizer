<?php
/**
 * Remove plugin settings on uninstall. Normalized content is intentionally kept.
 *
 * @package PersianTextNormalizer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ptn_settings' );
