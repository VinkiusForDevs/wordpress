<?php
/**
 * Internationalization: 6-locale catalogs with a user-selectable override.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\I18n;

use VinkiusConnector\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Loads translations for the user-selectable locales and installs a gettext
 * hook so the active locale wins even when the site language differs.
 *
 * Locales: en (base .pot), pt_BR, fr_FR, es_ES, de_DE, ja_JP.
 *
 * The plugin ships PO catalogs (source-of-truth for translators). WordPress
 * ships no compiler in the admin runtime, so PO files are parsed here
 * directly; if .mo files are ever compiled and placed in languages/, the
 * standard loader is used first and this parser acts as a fallback.
 *
 * Conventions: all user-facing strings use `__()`/`_e()`/`esc_html_e()`
 * with the text domain VC_TEXT_DOMAIN. Plural forms (_n) are avoided in
 * the catalog so PO files stay a single msgid/msgstr pair.
 */
final class I18n {

	/**
	 * Supported locales (code => label, English labels are UI-independent).
	 *
	 * @var array<string, string>
	 */
	private static $supported = array(
		'en'     => 'English',
		'pt_BR'  => 'Português (Brasil)',
		'fr_FR'  => 'Français',
		'es_ES'  => 'Español',
		'de_DE'  => 'Deutsch',
		'ja_JP'  => '日本語',
	);

	/**
	 * Parsed translation maps per locale (locale => msgid => msgstr).
	 *
	 * @var array<string, array<string, string>>
	 */
	private static $maps = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'load' ) );
		add_filter( 'gettext', array( $this, 'gettext' ), 20, 3 );
	}

	/**
	 * Resolve the active locale code ("site" => current site locale).
	 *
	 * @return string Locale code (e.g. pt_BR) or '' for the base.
	 */
	public static function active_locale() {
		$choice = (string) Options::get( 'language', 'site' );

		if ( 'site' === $choice ) {
			$choice = get_locale();
		}

		// Normalize: accept ja_JP / pt_BR etc.; fall back to '' (base) when unknown.
		foreach ( array_keys( self::$supported ) as $code ) {
			$parts = array_map( 'strtolower', array( $code, $choice ) );

			if ( $parts[0] === $parts[1] ) {
				return $code;
			}
		}

		// Site locales like 'en_US', 'pt_BR', 'fr_FR', 'es_ES', 'de_DE', 'ja_JP'.
		$map = array(
			'en_us' => '',
			'pt_br' => 'pt_BR',
			'fr_fr' => 'fr_FR',
			'es_es' => 'es_ES',
			'de_de' => 'de_DE',
			'ja_jp' => 'ja_JP',
		);
		$key = strtolower( $choice );

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Load the active catalog.
	 *
	 * @return void
	 */
	public function load() {
		$locale = self::active_locale();

		if ( '' === $locale ) {
			return; // Base language: English ships in the code.
		}

		$dir = VC_PLUGIN_DIR . 'languages/';

		// 1) Standard loader first (in case compiled .mo files are present).
		load_plugin_textdomain( VC_TEXT_DOMAIN, false, dirname( VC_PLUGIN_BASENAME ) . '/languages' );

		// 2) Runtime PO parsing fallback (the shipped source of truth).
		$po = $dir . VC_TEXT_DOMAIN . '-' . $locale . '.po';

		if ( file_exists( $po ) ) {
			self::$maps[ $locale ] = self::parse_po( $po );
		}
	}

	/**
	 * gettext filter: apply the PO map for our domain.
	 *
	 * @param string $translation Translated string.
	 * @param string $text         Original string.
	 * @param string $domain       Text domain.
	 * @return string
	 */
	public function gettext( $translation, $text, $domain ) {
		if ( VC_TEXT_DOMAIN !== $domain ) {
			return $translation;
		}

		$locale = self::active_locale();

		if ( '' !== $locale && isset( self::$maps[ $locale ][ $text ] ) ) {
			return self::$maps[ $locale ][ $text ];
		}

		return $translation;
	}

	/**
	 * Locale select options for the settings screen.
	 *
	 * @return array<string, string>
	 */
	public static function supported() {
		return self::$supported;
	}

	/**
	 * Minimal PO parser (msgid/msgstr, no plurals/contexts in this catalog).
	 *
	 * @param string $file PO file path.
	 * @return array<string, string>
	 */
	private static function parse_po( $file ) {
		$lines = file( $file, FILE_IGNORE_NEW_LINES );

		if ( false === $lines ) {
			return array();
		}

		$map       = array();
		$entry_id  = '';
		$entry_str = '';
		$has_entry = false;
		$current   = null; // 'id' | 'str' | null.

		foreach ( $lines as $line ) {
			$line = rtrim( $line );

			if ( '' === $line ) {
				// Entry boundary.
				if ( $has_entry && '' !== $entry_id && '' !== $entry_str ) {
					$map[ $entry_id ] = $entry_str;
				}
				$entry_id  = '';
				$entry_str = '';
				$has_entry = false;
				$current   = null;
				continue;
			}

			if ( 0 === strpos( $line, '#' ) ) {
				continue;
			}

			if ( preg_match( '/^msgid\s+"(.*)"\s*$/', $line, $m ) ) {
				if ( $has_entry && '' !== $entry_id && '' !== $entry_str ) {
					$map[ $entry_id ] = $entry_str;
				}
				$entry_id  = self::decode( $m[1] );
				$entry_str = '';
				$has_entry = true;
				$current   = 'id';
				continue;
			}

			if ( preg_match( '/^msgstr\s+"(.*)"\s*$/', $line, $m ) ) {
				$entry_str = self::decode( $m[1] );
				$current   = 'str';
				continue;
			}

			if ( preg_match( '/^"(.*)"\s*$/', $line, $m ) ) {
				$decoded = self::decode( $m[1] );

				if ( 'id' === $current ) {
					$entry_id .= $decoded;
				} elseif ( 'str' === $current ) {
					$entry_str .= $decoded;
				}
			}
		}

		if ( $has_entry && '' !== $entry_id && '' !== $entry_str ) {
			$map[ $entry_id ] = $entry_str;
		}

		return $map;
	}

	/**
	 * Decode C-style escapes inside PO string literals.
	 *
	 * @param string $s Encoded string.
	 * @return string
	 */
	private static function decode( $s ) {
		return stripcslashes( $s );
	}
}
