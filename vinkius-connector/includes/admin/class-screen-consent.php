<?php
/**
 * Consent screen (Consent Hub).
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\Models\ConsentRepo;

defined( 'ABSPATH' ) || exit;

/**
 * Every visitor consent on record, with scopes, origin (hashed IP only)
 * and revoke actions.
 */
class ScreenConsent extends ScreenBase {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'consent';
	}

	/**
	 * Prepare view data.
	 *
	 * @return array
	 */
	protected function prepare() {
		$rows = ConsentRepo::list_all( 100 );

		foreach ( $rows as &$row ) {
			$row['scopes'] = json_decode( (string) $row['scopes'], true ) ?: array();
		}
		unset( $row );

		return array(
			'rows'      => $rows,
			'scopes'    => ConsentRepo::SCOPES,
			'shortcode' => '[vinkius_consent]',
		);
	}
}
