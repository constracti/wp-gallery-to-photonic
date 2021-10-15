<?php

if ( !defined( 'ABSPATH' ) )
	exit;


if ( !class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );


class WP_Gallery_To_Photonic_Main_List_Table extends WP_List_Table {

	function get_columns(): array {
		return [
			'title' => 'Title',
			'author' => 'Author',
			'date' => 'Date',
		];
	}

	function prepare_items( array $items = [] ): void {
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = [];
		$this->_column_headers = [ $columns, $hidden, $sortable, ];
		$this->items = $items;
	}

	function column_title( WP_Post $post ): string {
		$edit_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );
		$view_url = get_the_permalink( $post );
		$actions = [
			'view' => sprintf( '<a href="%s">View</a>', $view_url ),
		];
		if ( array_key_exists( 'tab', $_GET ) && $_GET['tab'] === 'ignored' ) {
			$include_url = admin_url( 'admin.php?action=g2phot_include&id=' . $post->ID );
			$include_url = wp_nonce_url( $include_url, 'g2phot_include_' . $post->ID, 'nonce' );
			$actions['include'] = sprintf( '<a href="%s">Include</a>', $include_url );
		} else {
			$upload_url = admin_url( 'admin.php?page=g2phot&tab=upload&id=' . $post->ID );
			$actions['upload'] = sprintf( '<a href="%s">Upload</a>', $upload_url );
			$exclude_url = admin_url( 'admin.php?action=g2phot_exclude&id=' . $post->ID );
			$exclude_url = wp_nonce_url( $exclude_url, 'g2phot_exclude_' . $post->ID, 'nonce' );
			$actions['exclude'] = sprintf( '<a href="%s">Exclude</a>', $exclude_url );
		}
		ob_start();
?>
<div style="display: flex;">
	<div style="flex-shrink: 0; height: 40px; width: 40px; margin-right: 8px;">
<?php
		echo get_the_post_thumbnail( $post, 'thumbnail', [
			'style' => 'max-height: 100%; height: auto; max-width: 100%; width: auto;',
		] );
?>
	</div>
	<div style="flex-grow: 1;">
		<div><strong><a href="<?= $edit_url ?>"><?= esc_html( $post->post_title ) ?></a></strong></div>
		<div><?= $this->row_actions( $actions ) ?></div>
	</div>
</div>
<?php
		return ob_get_clean();
	}

	function column_date( WP_Post $post ): string {
		return esc_html( $post->post_date );
	}

	function column_author( WP_Post $post ): string {
		$author = get_user_by( 'ID', $post->post_author );
		return esc_html( $author->data->user_login );
	}
}
