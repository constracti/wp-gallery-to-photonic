<?php

/*
 * Plugin Name: WP Gallery to Photonic
 * Plugin URI: https://github.com/constracti/wp-gallery-to-photonic
 * Description: Upload WP Galleries as Albums in Google Photos and display using Photonic plugin.
 * Author: constracti
 * Version: 0.1
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */


if ( !defined( 'ABSPATH' ) )
	exit;


class WP_Gallery_To_Photonic {

	private static $DIR;
	private static $URL;

	private static $SCOPES = [
		'https://www.googleapis.com/auth/photoslibrary.appendonly',
	];

	public function init(): void {
		self::$DIR = plugin_dir_path( __FILE__ );
		self::$URL = plugin_dir_url( __FILE__ );

		// submenu page

		add_action( 'admin_menu', function(): void {
			$parent_slug = 'photonic-options-manager';
			$page_title = 'WP Gallery to Photonic';
			$menu_title = 'Addon: Upload';
			$capability = 'manage_options';
			$menu_slug = 'g2phot';
			$position = 50;
			add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, function(): void {
?>
<div class="wrap">
	<h1>WP Gallery to Photonic</h1>
	<h2 class="nav-tab-wrapper">
<?php
				$tab_list = apply_filters( 'g2phot_tab_list', [] );
				$tab_curr = array_key_exists( 'tab', $_GET ) && is_string( $_GET['tab'] ) ? $_GET['tab'] : '';
				foreach ( $tab_list as $tab_slug => $tab_name ) {
					$class = [];
					$class[] = 'nav-tab';
					if ( $tab_slug === $tab_curr )
						$class[] = 'nav-tab-active';
						$class = implode( ' ', $class );
						$url = admin_url( 'admin.php?page=g2phot' );
						if ( !empty( $tab_slug ) )
							$url .= '&tab=' . $tab_slug;
?>
		<a class="<?= $class ?>" href="<?= $url ?>"><?= esc_html( $tab_name ) ?></a>
<?php
				}
?>
	</h2>
<?php
				do_action( 'g2phot_tab_html_' . $tab_curr );
?>
</div>
<?php
			}, $position );
		}, 11 );

		// main tab

		add_filter( 'g2phot_tab_list', function( array $tab_list ): array {
			$tab_list[''] = 'Main';
			return $tab_list;
		} );

		add_action( 'g2phot_tab_html_', function(): void {
			$refresh_token = get_option( 'g2phot_refresh' );
			if ( empty( $refresh_token ) ) {
				$url = admin_url( 'admin.php?page=g2phot&tab=settings' );
				echo sprintf( '<p><a href="%s" class="button button-primary">Settings</a></p>', $url );
				return;
			}
			$posts = get_posts( [
				's' => '[gallery ',
				'post_type' => 'post',
				'posts_per_page' => 10,
				'order' => 'ASC',
				'post__not_in' => get_option( 'g2phot_ignored', [] ),
			] );
			require_once( self::$DIR . 'main-table.php' );
			$table = new WP_Gallery_To_Photonic_Main_List_Table();
			$table->prepare_items( $posts );
			$table->display();
		} );

		// upload hidden tab

		add_action( 'g2phot_tab_html_upload', function(): void {
			if ( !array_key_exists( 'id', $_GET ) || !is_string( $_GET['id'] ) )
				return;
			$post = get_post( intval( $_GET['id'] ) );
			$thumb = get_post_thumbnail_id( $post );
			$regex = get_shortcode_regex( [ 'gallery', ] );
?>
<h2>ID</h2>
<p><?= esc_html( $post->ID ) ?></p>
<h2>title</h2>
<p><a href="<?= get_the_permalink( $post ) ?>" target="_blank"><?= esc_html( $post->post_title ) ?></a></p>
<h2>thumbnail</h2>
<p><?= $thumb ?></p>
<p><a href="<?= admin_url( sprintf( 'post.php?post=%d&action=edit', $post->ID ) ) ?>" class="button">edit</a></p>
<?php
			$m = NULL;
			if ( has_shortcode( $post->post_content, 'gallery' ) )
				preg_match( '/' . $regex . '/s', $post->post_content, $m );
			if ( !is_null( $m ) && !empty( $m ) ) {
				$text = $m[0];
?>
<h2>shortcode</h2>
<p><code><?= esc_html( $text ) ?></code></p>
<?php
				$atts = shortcode_parse_atts( $m[3] );
				$keys = array_keys( $atts );
				if ( count( $keys ) === 1 && $keys[0] === 'ids' ) {
					$url = admin_url( sprintf( 'admin.php?action=g2phot_upload&id=%d&md5=%s', $post->ID, md5( $m[0] ) ) );
					$url = wp_nonce_url( $url, 'g2phot_upload_' . $post->ID, 'nonce' );
					$url1 = $url . '&field=title';
					$url2 = $url . '&field=caption';
?>
<p>
	<a href="<?= $url ?>" class="button button-primary">Upload</a>
	<a href="<?= $url1 ?>" class="button button-primary">Upload with Title</a>
	<a href="<?= $url2 ?>" class="button button-primary">Upload with Caption</a>
</p>
<?php
					$ids = array_map( 'intval', explode( ',', $atts['ids'] ) );
					require_once( self::$DIR . 'upload-table.php' );
					$table = new WP_Gallery_To_Photonic_Upload_List_Table();
					$table->prepare_items( $ids );
					$table->display();
				}
			}
		} );

		// ignored tab

		add_filter( 'g2phot_tab_list', function( array $tab_list ): array {
			$tab_list['ignored'] = 'Ignored';
			return $tab_list;
		} );

		add_action( 'g2phot_tab_html_ignored', function(): void {
			$ignored = get_option( 'g2phot_ignored', [] );
			$posts = !empty( $ignored ) ? get_posts( [
				'post_type' => 'post',
				'posts_per_page' => -1,
				'post__in' => $ignored,
			] ) : [];
			require_once( self::$DIR . 'ignored-table.php' );
			$table = new WP_Gallery_To_Photonic_Ignored_List_Table();
			$table->prepare_items( $posts );
			$table->display();
		} );

		// settings tab

		add_filter( 'g2phot_tab_list', function( array $tab_list ): array {
			$tab_list['settings'] = 'Settings';
			return $tab_list;
		} );

		add_action( 'g2phot_tab_html_settings', function(): void {
			$client_id = get_option( 'g2phot_client_id' );
			$client_secret = get_option( 'g2phot_client_secret' );
			$url = admin_url( 'admin.php?action=g2phot_client' );
			$url = wp_nonce_url( $url, 'g2phot_client', 'nonce' );
?>
<h2 class="title">Client Details</h2>
<form method="post" action="<?= $url ?>">
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="client_id">Client ID</label>
				</th>
				<td>
					<input type="text" class="regular-text" name="client_id" id="client_id" value="<?= esc_attr( $client_id ) ?>">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="client_secret">Client Secret</label>
				</th>
				<td>
					<input type="text" class="regular-text" name="client_secret" id="client_secret" value="<?= esc_attr( $client_secret ) ?>">
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<button type="submit" class="button button-primary">Save</button>
	</p>
</form>
<?php
		} );

		add_action( 'g2phot_tab_html_settings', function(): void {
			$client_id = get_option( 'g2phot_client_id' );
			$client_secret = get_option( 'g2phot_client_secret' );
			if ( empty( $client_id ) || empty( $client_secret ) )
				return;
			$refresh_token = get_option( 'g2phot_refresh' );
?>
<h2 class="title">Authentication</h2>
<?php
			if ( !empty( $refresh_token ) ) {
?>
<p><strong>Refresh Token</strong></p>
<p><code><?= esc_html( $refresh_token ) ?></code></p>
<?php
				$url = admin_url( 'admin.php?action=g2phot_clear' );
				$url = wp_nonce_url( $url, 'g2phot_clear', 'nonce' );
?>
<p><a href="<?= $url ?>" class="button">Delete Token</a></p>
<?php
			} else {
				$url = admin_url( 'admin.php?action=g2phot_auth' );
				$url = wp_nonce_url($url, 'g2phot_auth', 'nonce' );
?>
<p><a href="<?= $url ?>" class="button button-primary">Authenticate</a></p>
<?php
			}
		} );

		// exclude action

		add_action( 'admin_action_g2phot_exclude', function(): void {
			if ( !current_user_can( 'manage_options' ) )
				wp_die( 'role' );
			if ( !array_key_exists( 'id', $_GET ) || !is_string( $_GET['id'] ) )
				wp_die( 'id' );
			if ( !self::verify_nonce( 'g2phot_exclude_' . $_GET['id'] ) )
				wp_die( 'nonce' );
			$id = intval( $_GET['id'] );
			$ignored = get_option( 'g2phot_ignored', [] );
			if ( !in_array( $id, $ignored, TRUE ) ) {
				$ignored[] = $id;
				update_option( 'g2phot_ignored', $ignored );
			}
			header( 'Location: ' . admin_url( 'admin.php?page=g2phot' ) );
			exit;
		} );

		// include action

		add_action( 'admin_action_g2phot_include', function(): void {
			if ( !current_user_can( 'manage_options' ) )
				wp_die( 'role' );
			if ( !array_key_exists( 'id', $_GET ) || !is_string( $_GET['id'] ) )
				wp_die( 'id' );
			if ( !self::verify_nonce( 'g2phot_include_' . $_GET['id'] ) )
				wp_die( 'nonce' );
			$id = intval( $_GET['id'] );
			$ignored = get_option( 'g2phot_ignored', [] );
			$key = array_search( $id, $ignored );
			if ( $key !== FALSE ) {
				unset( $ignored[$key] );
				$ignored = array_values( $ignored );
				if ( !empty( $ignored ) )
					update_option( 'g2phot_ignored', $ignored );
				else
					delete_option( 'g2phot_ignored' );
			}
			header( 'Location: ' . admin_url( 'admin.php?page=g2phot&tab=ignored' ) );
			exit;
		} );

		// client action

		add_action( 'admin_action_g2phot_client', function(): void {
			if ( !current_user_can( 'manage_options' ) )
				wp_die( 'role' );
			if ( !self::verify_nonce( 'g2phot_client' ) )
				wp_die( 'nonce' );
			if ( !array_key_exists( 'client_id', $_POST ) || !is_string( $_POST['client_id'] ) )
				wp_die( 'form' );
			if ( !array_key_exists( 'client_secret', $_POST ) || !is_string( $_POST['client_secret'] ) )
				wp_die( 'form' );
			$client_id = trim( $_POST['client_id'] );
			$client_secret = trim( $_POST['client_secret'] );
			if ( !empty( $client_id ) )
				update_option( 'g2phot_client_id', $client_id );
			else
				delete_option( 'g2phot_client_id' );
			if ( !empty( $client_secret ) )
				update_option( 'g2phot_client_secret', $client_secret );
			else
				delete_option( 'g2phot_client_secret' );
			delete_option( 'g2phot_refresh' );
			header( 'Location: ' . admin_url( 'admin.php?page=g2phot&tab=settings' ) );
			exit;
		} );

		// auth action

		add_action( 'admin_action_g2phot_auth', function(): void {
			if ( !current_user_can( 'manage_options' ) )
				wp_die( 'role' );

			require_once( self::$DIR . 'vendor/autoload.php' );

			$oauth2 = new Google\Auth\OAuth2( [
				'clientId'           => get_option( 'g2phot_client_id' ),
				'clientSecret'       => get_option( 'g2phot_client_secret' ),
				'authorizationUri'   => 'https://accounts.google.com/o/oauth2/v2/auth',
				'redirectUri'        => admin_url( 'admin.php?action=g2phot_auth' ),
				'tokenCredentialUri' => 'https://www.googleapis.com/oauth2/v4/token',
				'scope'              => self::$SCOPES,
			] );

			if ( !array_key_exists( 'code', $_GET ) ) {
				if ( !self::verify_nonce( 'g2phot_auth' ) )
					wp_die( 'nonce' );
				$authentication_url = $oauth2->buildFullAuthorizationUri( [
					'access_type' => 'offline',
					'prompt'      => 'consent',
				] );
				header( 'Location: ' . $authentication_url );
				exit;
			} else {
				// With the code returned by the OAuth flow, we can retrieve the refresh token.
				$oauth2->setCode( $_GET['code'] );
				$auth_token = $oauth2->fetchAuthToken();
				$refresh_token = $auth_token['refresh_token'];
				update_option( 'g2phot_refresh', $refresh_token );
				header( 'Location: ' . admin_url( 'admin.php?page=g2phot&tab=settings' ) );
				exit;
			}
		} );

		// clear action

		add_action( 'admin_action_g2phot_clear', function(): void {
			if ( !current_user_can( 'manage_options' ) )
				wp_die( 'role' );
			if ( !self::verify_nonce( 'g2phot_clear' ) )
				wp_die( 'nonce' );
			delete_option( 'g2phot_refresh' );
			header( 'Location: ' . admin_url( 'admin.php?page=g2phot&tab=settings' ) );
			exit;
		} );

		// upload action

		add_action( 'admin_action_g2phot_upload', function(): void {
			if ( !current_user_can( 'manage_options' ) )
				wp_die( 'role' );
			if ( !array_key_exists( 'id', $_GET ) || !is_string( $_GET['id'] ) )
				wp_die( 'id' );
			if ( !self::verify_nonce( 'g2phot_upload_' . $_GET['id'] ) )
				wp_die( 'nonce' );
			$post = get_post( $_GET['id'] );
			if ( is_null( $post ) )
				wp_die( 'id' );
			$thumb = get_post_thumbnail_id( $post );
			$regex = get_shortcode_regex( [ 'gallery', ] );
			$m = NULL;
			if ( !has_shortcode( $post->post_content, 'gallery' ) )
				wp_die( 'shortcode' );
			preg_match( '/' . $regex . '/s', $post->post_content, $m );
			if ( is_null( $m ) || empty( $m ) )
				wp_die( 'shortcode' );
			if ( !array_key_exists( 'md5', $_GET ) || !is_string( $_GET['md5'] ) || md5( $m[0] ) !== $_GET['md5'] )
				wp_die( 'md5' );
			$atts = shortcode_parse_atts( $m[3] );
			$ids = array_map( 'intval', explode( ',', $atts['ids'] ) );
			$field = NULL;
			if ( array_key_exists( 'field', $_GET ) && is_string( $_GET['field'] ) ) {
				switch ( $_GET['field'] ) {
					case 'title':
						$field = 'get_the_title';
						break;
					case 'caption':
						$field = 'wp_get_attachment_caption';
						break;
				}
			}

			require_once( self::$DIR . 'vendor/autoload.php' );

			// https://developers.google.com/photos/library/guides/get-started-php

			// Use the OAuth flow provided by the Google API Client Auth library
			// to authenticate users. See the file /src/common/common.php in the samples for a complete
			// authentication example.
			$auth_credentials = new Google\Auth\Credentials\UserRefreshCredentials( self::$SCOPES, [
				'client_id' => get_option( 'g2phot_client_id' ),
				'client_secret' => get_option( 'g2phot_client_secret' ),
				'refresh_token' => get_option( 'g2phot_refresh' ),
			] );
			// Set up the Photos Library Client that interacts with the API
			$photosLibraryClient = new Google\Photos\Library\V1\PhotosLibraryClient( [
				'credentials' => $auth_credentials,
			] );
			// Create a new Album object with at title
			$newAlbum = Google\Photos\Library\V1\PhotosLibraryResourceFactory::album( $post->post_title );
			// Make the call to the Library API to create the new album
			$createdAlbum = $photosLibraryClient->createAlbum( $newAlbum );
			// The creation call returns the ID of the new album
			$albumId = $createdAlbum->getId();

			$requests = [];
			$request = NULL;
			foreach ( $ids as $id ) {
				if ( !is_null( $request ) && count( $request->ids ) === 50 )
					$request = NULL;
				if ( is_null( $request ) ) {
					$request = (object) [
						'ids' => [],
					];
					$requests[] = $request;
				}
				$request->ids[] = $id;
			}

			foreach ( $requests as $request ) {
				$request->newMediaItems = [];

				foreach ( $request->ids as $id ) {
					$mimeType = get_post_mime_type( $id );
					$path = get_attached_file( $id );
					$filename = basename( $path );
					$caption = !is_null( $field ) ? $field( $id ) : NULL;

					// Create a new upload request by opening the file
					// and specifying the media type (e.g. "image/png")
					$token = $photosLibraryClient->upload( file_get_contents( $path ), NULL, $mimeType );

					// Create a NewMediaItem with the following components:
					// - uploadToken obtained from the previous upload request
					// - filename that will be shown to the user in Google Photos
					// - description that will be shown to the user in Google Photos
					$request->newMediaItems[] = Google\Photos\Library\V1\PhotosLibraryResourceFactory::newMediaItemWithDescriptionAndFileName( $token, $caption, $filename );
				}

				$response = $photosLibraryClient->batchCreateMediaItems( $request->newMediaItems, [
					'albumId' => $albumId,
				] );
			}

			$search = $m[0];
			$replace = sprintf( '[photonic type="google" view="photos" album_id="%s"]', $albumId );
			$post->post_content = str_replace( $search, $replace, $post->post_content );
			wp_update_post( $post );

			$imgs = get_posts( [
				'post_type' => 'attachment',
				'posts_per_page' => -1,
				'post__in' => $ids,
				'post_parent__in' => [ $post->ID, ],
				'fields' => 'ids',
			] );
			foreach ( $imgs as $img ) {
				if ( $img === $thumb )
					continue;
				wp_delete_attachment( $img );
			}

			header( 'Location: ' . admin_url( 'admin.php?page=g2phot' ) );
		} );
	}

	private function verify_nonce( string $action ): bool {
		if ( !array_key_exists( 'nonce', $_GET ) )
			return FALSE;
		if ( !is_string( $_GET['nonce'] ) )
			return FALSE;
		if ( !wp_verify_nonce( $_GET['nonce'], $action ) )
			return FALSE;
		return TRUE;
	}
}


WP_Gallery_To_Photonic::init();
