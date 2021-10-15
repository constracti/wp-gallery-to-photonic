<?php

if ( !defined( 'ABSPATH' ) )
	exit;


if ( !class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );


class WP_Gallery_To_Photonic_Ignored_List_Table extends WP_List_Table {

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
		$include_url = admin_url( 'admin.php?action=g2phot_include&id=' . $post->ID );
		$include_url = wp_nonce_url( $include_url, 'g2phot_include_' . $post->ID, 'nonce' );
		$actions = [
			'view' => sprintf( '<a href="%s">View</a>', $view_url ),
			'include' => sprintf( '<a href="%s">Include</a>', $include_url ),
		];
		ob_start();
?>
<div><strong><a href="<?= $edit_url ?>"><?= esc_html( $post->post_title ) ?></a></strong></div>
<div><?= $this->row_actions( $actions ) ?></div>
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
