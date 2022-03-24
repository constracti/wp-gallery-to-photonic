<?php

if ( !defined( 'ABSPATH' ) )
	exit;


if ( !class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );


class WP_Gallery_To_Photonic_Upload_List_Table extends WP_List_Table {

	function get_columns(): array {
		return [
			'thumbnail' => 'Thumbnail',
			'id' => 'ID',
			'title' => 'Title',
			'caption' => 'Caption',
		];
	}

	function prepare_items( array $items = [] ): void {
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = [];
		$this->_column_headers = [ $columns, $hidden, $sortable, ];
		$this->items = $items;
	}

	function column_thumbnail( int $id ): string {
		$return = '';
		$return .= '<div style="height: 40px; width: 40px;">' . "\n";
		$return .= wp_get_attachment_image( $id, 'thumbnail', FALSE, [
			'style' => 'max-height: 100%; height: auto; max-width: 100%; width: auto;',
		] ) . "\n";
		$return .= '</div>' . "\n";
		return $return;
	}

	function column_id( int $id ): string {
		$href = add_query_arg( [
			'post' => $id,
			'action' => 'edit',
		], admin_url( 'post.php' ) );
		return sprintf( '<a href="%s" target="_blank">%s</a>', esc_url_raw( $href ), esc_html( $id ) );
	}

	function column_title( int $id ): string {
		return esc_html( get_the_title( $id ) );
	}

	function column_caption( int $id ): string {
		return esc_html( wp_get_attachment_caption( $id ) );
	}

	function display_tablenav( $which ): void {}
}
