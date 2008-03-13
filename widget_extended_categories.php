<?php
/*
Plugin Name: Extended Category Widget
Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
Description: Replacement of the category widget to allow for greater customization of the category widget.
Version: 1.2
Author: Peter van der Does
Author URI: http://blog.avirtualhome.com/

Copyright 2008  Peter van der Does  (email : peter@avirtualhome.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function widget_extended_categories_init() {
	// Widgets exists?
	if ( ! function_exists( 'wp_register_sidebar_widget' ) || ! function_exists( 'wp_register_widget_control' ) ) {
		return;
	}

	function widget_extended_categories($args) {
		extract( $args );
		$options = get_option( 'widget_extended_categories' );
		$c = $options['count'] ? '1' : '0';
		$h = $options['hierarchical'] ? '1' : '0';
		$e = $options['hide_empty'] ? '1' : '0';
		$s = $options['sort_column'] ? $options['sort_column'] : 'name';
		$o = $options['sort_order'] ? $options['sort_order'] : 'asc';
		$title = empty( $options['title'] ) ? __( 'Categories' ) : $options['title'];
		$style = empty( $options['style'] ) ? 'list' : $options['style'];
		$cat_args = array ('orderby'=>$s, 'order'=>$o, 'show_count'=>$c, 'hide_empty'=>$e, 'hierarchical'=>$h, 'title_li'=>'', 'show_option_none'=>__( 'Select Category' ));
		
		echo $before_widget;
		echo $before_title . $title . $after_title;
		?>
<ul>
<?php
		if ( $style == 'list' ) {
			wp_list_categories( $cat_args );
		} else {
			wp_dropdown_categories( $cat_args );
			?> 
                        <script lang='javascript'><!--
                        var dropdown = document.getElementById("cat");
                        function onCatChange() {
                            if ( dropdown.options[dropdown.selectedIndex].value > 0 ) {
                                location.href = "<?php
			echo get_option( 'home' );
			?>/?cat="+dropdown.options[dropdown.selectedIndex].value;
                            }
                        }
                        dropdown.onchange = onCatChange;
--></script>
<?php
		}
		?>
                </ul>
<?php
		echo $after_widget;
	}

	function widget_extended_categories_control() {
		// Get actual options
		$options = $newoptions = get_option( 'widget_extended_categories' );
		if ( ! is_array( $options ) ) {
			$options = $newoptions = array ();
		}
		
		// Post to new options array
		if ( $_POST['categories-submit'] ) {
			$newoptions['title'] = strip_tags( stripslashes( $_POST['categories-title'] ) );
			$newoptions['count'] = isset( $_POST['categories-count'] );
			$newoptions['hierarchical'] = isset( $_POST['categories-hierarchical'] );
			$newoptions['hide_empty'] = isset( $_POST['categories-hide_empty'] );
			$newoptions['sort_column'] = strip_tags( stripslashes( $_POST['categories-sort_column'] ) );
			$newoptions['sort_order'] = strip_tags( stripslashes( $_POST['categories-sort_order'] ) );
			$newoptions['style'] = strip_tags( stripslashes( $_POST['categories-style'] ) );
		}
		
		// Update if new options
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option( 'widget_extended_categories', $options );
		}
		
		// Prepare data for display
		$title = htmlspecialchars( $options['title'], ENT_QUOTES );
		$count = $options['count'] ? 'checked="checked"' : '';
		$hierarchical = $options['hierarchical'] ? 'checked="checked"' : '';
		$hide_empty = $options['hide_empty'] ? 'checked="checked"' : '';
		$sort_id = ($options['sort_column'] == 'ID') ? ' SELECTED' : '';
		$sort_name = ($options['sort_column'] == 'name') ? ' SELECTED' : '';
		$sort_count = ($options['sort_column'] == 'count') ? ' SELECTED' : '';
		$sort_order_a = ($options['sort_order'] == 'asc') ? ' SELECTED' : '';
		$sort_order_d = ($options['sort_order'] == 'desc') ? ' SELECTED' : '';
		$style_list = ($options['style'] == 'list') ? ' SELECTED' : '';
		$style_drop = ($options['style'] == 'drop') ? ' SELECTED' : '';
		?>
<div><label for="categories-title"><?php
		_e( 'Title:' );
		?>
                <input style="width: 250px;" id="categories-title"
	name="categories-title" type="text" value="<?php
		echo $title;
		?>" /> </label> <label for="categories-count"
	style="line-height: 35px; display: block;">Show post counts <input
	class="checkbox" type="checkbox" <?php
		echo $count;
		?>
	id="categories-count" name="categories-count" /> </label> <label
	for="categories-hierarchical"
	style="line-height: 35px; display: block;">Show hierarchy <input
	class="checkbox" type="checkbox" <?php
		echo $hierarchical;
		?>
	id="categories-hierarchical" name="categories-hierarchical" /> </label>

<label for="categories-hide_empty"
	style="line-height: 35px; display: block;">Hide empty categories <input
	class="checkbox" type="checkbox" <?php
		echo $hide_empty;
		?>
	id="categories-hide_empty" name="categories-hide_empty" /> </label> <label
	for="categories-sort_column" style="line-height: 35px; display: block;">Sort
by <select id="categories-sort_column" name="categories-sort_column">
	<option value="ID" <?php
		echo $sort_id?>>ID</option>
	<option value="name" <?php
		echo $sort_name?>>Name</option>
	<option value="count" <?php
		echo $sort_count?>>Count</option>
</select> </label> <label for="categories-sort_order"
	style="line-height: 35px; display: block;">Sort order <select
	id="categories-sort_order" name="categories-sort_order">
	<option value="asc" <?php
		echo $sort_order_a?>>Ascending</option>
	<option value="desc" <?php
		echo $sort_order_d?>>Descending</option>
</select> </label> <label for="categories-style"
	style="line-height: 35px; display: block;">Display style <select
	id="categories-style" name="categories-style">
	<option value='list' <?php
		echo $style_list;
		?>>List</option>
	<option value='drop' <?php
		echo $style_drop;
		?>>Drop down</option>
</select> </label> <input type="hidden" id="categories-submit"
	name="categories-submit" value="1" /></div>
<?php
	}

	function widget_extended_categories_register() {
		wp_register_sidebar_widget( 'extended-categories', 'Extended Categories', 'widget_extended_categories' );
		wp_register_widget_control( 'extended-categories', 'Extended Categories', 'widget_extended_categories_control', array ('width'=>300, 'height'=>245) );
	}
	
	// Launch Widgets
	widget_extended_categories_register();
}
add_action( 'plugins_loaded', 'widget_extended_categories_init' );
?>
