<?php
class AVH_EC_Admin
{
	/**
	 *
	 * @var AVH_EC_Core
	 */
	var $core;

	/**
	 *
	 * @var AVH_EC_Category_Group
	 */
	var $catgrp;

	var $hooks = array ();
	var $message;

	/**
	 * PHP5 constructor
	 *
	 */
	function __construct ()
	{

		// Initialize the plugin
		$this->core = & AVH_EC_Singleton::getInstance( 'AVH_EC_Core' );
		$this->catgrp = & AVH_EC_Singleton::getInstance( 'AVH_EC_Category_Group' );

		add_action( 'wp_ajax_delete-group', array (&$this, 'ajaxDeleteGroup' ) );

		// Admin menu
		add_action( 'admin_menu', array (&$this, 'actionAdminMenu' ) );
		add_filter( 'plugin_action_links_extended-categories-widget/widget_extended_categories.php', array (&$this, 'filterPluginActions' ), 10, 2 );

		// Register Style and Scripts
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
		wp_register_script( 'avhec-categorygroup-js', AVHEC_PLUGIN_URL . '/js/avh-ec.categorygroup' . $suffix . '.js', array ('jquery' ), $this->core->version, true );
		wp_register_style( 'avhec-admin-css', AVHEC_PLUGIN_URL . '/css/avh-ec.admin.css', array ('wp-admin' ), $this->core->version, 'screen' );

		// Metaboxes for the Category Group on the post and page pages
		add_meta_box( 'avhec_category_group_box_ID', __( 'Category Group', 'avh-ec' ), array (&$this, 'metaboxPostCategoryGroup' ), 'post', 'side', 'core' );
		add_meta_box( 'avhec_category_group_box_ID', __( 'Category Group', 'avh-ec' ), array (&$this, 'metaboxPostCategoryGroup' ), 'page', 'side', 'core' );

		// Actions used for editing posts
		add_action( 'load-post.php', array (&$this, 'actionLoadPostPage' ) );
		add_action( 'load-page.php', array (&$this, 'actionLoadPostPage' ) );
		add_action( 'save_post', array (&$this, 'actionSaveCategoryGroupTaxonomy' ) );

		// Actions related to adding and deletes categories
		add_action( "created_category", array ($this, 'actionCreatedCategory' ), 10, 2 );
		add_action( "delete_category", array ($this, 'actionDeleteCategory' ), 10, 2 );

		add_filter( 'manage_categories_group_columns', array (&$this, 'filterManageCategoriesGroupColumns' ) );
		add_filter( 'explain_nonce_delete-avhecgroup', array (&$this, 'filterExplainNonceDeleteGroup' ), 10, 2 );

		return;
	}

	/**
	 * PHP4 Constructor
	 *
	 */
	function AVH_EC_Admin ()
	{
		$this->__construct();
	}

	/**
	 * Shows a metabox on the page post.php and page.php
	 * This function gets called in edit-form-advanced.php
	 *
	 * @param $post
	 */
	function metaboxPostCategoryGroup ( $post )
	{
		$options = $this->core->getOptions();
		echo '<p id=\'avhec-cat-group\'';

		echo '<input type="hidden" name="avhec_category_group_nonce" id="avhec_category_group_nonce" value="' . wp_create_nonce( 'avhec_category_group_nonce' ) . '" />';

		// Get all the taxonomy terms
		$category_groups = get_terms( $this->catgrp->taxonomy_name, array ('hide_empty' => FALSE ) );

		echo ' <select name=\'post_avhec_category_group\' id=\'post_avhec_category_group\' class=\'postform\'>';
		$current_category_group = wp_get_object_terms( $post->ID, $this->catgrp->taxonomy_name );

		foreach ( $category_groups as $group ) {
			if ( ! is_wp_error( $current_category_group ) && ! empty( $current_category_group ) && ! strcmp( $group->term_id, $current_category_group[0]->term_id ) ) {
				echo '<option value="' . $group->term_id . '" selected=\'selected\'>' . $group->name . "</option>\n";
			} else {
				if ( empty( $current_category_group ) && $options['cat_group']['default_group'] == $group->term_id ) {
					echo '<option value="' . $group->term_id . '" selected=\'selected\'>' . $group->name . "</option>\n";
				} else {
					echo '<option value="' . $group->term_id . '">' . $group->name . "</option>\n";
				}
			}
		}
		echo '</select>';
		echo '<em>Selecting the group \'none\' will not show the widget on the page.</em>';
		echo '</p>';
	}

	/**
	 * Called when the post/page is saved. It associates the selected Category Group with the post
	 *
	 * @param $post_id
	 * @return
	 */
	function actionSaveCategoryGroupTaxonomy ( $post_id )
	{
		if ( ! wp_verify_nonce( $_POST['avhec_category_group_nonce'], 'avhec_category_group_nonce' ) ) {
			return $post_id;
		}

		// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		// Check permissions
		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return $post_id;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}

		// OK, we're authenticated: we need to find and save the data
		$group_term_id = ( int ) $_POST['post_avhec_category_group'];
		wp_set_object_terms( $post_id, $group_term_id, $this->catgrp->taxonomy_name );

		return $post_id;

	}

	/**
	 * When a category is created this function is called to add the new category to the group all
	 * @param $term_id
	 * @param $term_taxonomy_id
	 */
	function actionCreatedCategory ( $term_id, $term_taxonomy_id )
	{
		$group_id = $this->catgrp->getTermIDBy( 'slug', 'all' );
		$this->catgrp->setCategoriesForGroup( $group_id, ( array ) $term_id );
	}

	/**
	 * When a category is deleted this function is called so the category is deleted from every group as well.
	 *
	 * @param object $term
	 * @param int $term_taxonomy_id
	 */
	function actionDeleteCategory ( $term_id, $term_taxonomy_id )
	{
		$this->catgrp->doDeleteCategoryFromGroup( $term_id );
	}

	/**
	 * Enqueues the style on the post.php and page.php pages
	 * @WordPress Action load-$pagenow
	 *
	 */
	function actionLoadPostPage ()
	{
		wp_enqueue_style( 'avhec-admin-css' );
	}

	/**
	 * Add the Tools and Options to the Management and Options page repectively
	 *
	 * @WordPress Action admin_menu
	 *
	 */
	function actionAdminMenu ()
	{

		// Add menu system
		$folder = $this->core->getBaseDirectory( AVHEC_PLUGIN_DIR );
		add_menu_page( 'AVH Extended Categories', 'AVH Extended Categories', 'manage_options', $folder, array (&$this, 'doMenuOverview' ) );
		$this->hooks['menu_overview'] = add_submenu_page( $folder, 'AVH Extended Categories: ' . __( 'Overview', 'avh-ec' ), __( 'Overview', 'avh-ec' ), 'manage_options', $folder, array (&$this, 'doMenuOverview' ) );
		$this->hooks['menu_general'] = add_submenu_page( $folder, 'AVH Extended Categories: ' . __( 'General Options', 'avh-ec' ), __( 'General Options', 'avh-ec' ), 'manage_options', 'avhec-general', array (&$this, 'doMenuGeneral' ) );
		$this->hooks['menu_category_groups'] = add_submenu_page( $folder, 'AVH Extended Categories: ' . __( 'Category Groups', 'avh-ec' ), __( 'Category Groups', 'avh-ec' ), 'manage_options', 'avhec-grouped', array (&$this, 'doMenuCategoryGroup' ) );
		$this->hooks['menu_faq'] = add_submenu_page( $folder, 'AVH Extended Categories:' . __( 'F.A.Q', 'avh-ec' ), __( 'F.A.Q', 'avh-ec' ), 'manage_options', 'avhec-faq', array (&$this, 'doMenuFAQ' ) );

		// Add actions for menu pages
		add_action( 'load-' . $this->hooks['menu_overview'], array (&$this, 'actionLoadPageHook_Overview' ) );
		add_action( 'load-' . $this->hooks['menu_general'], array (&$this, 'actionLoadPageHook_General' ) );
		add_action( 'load-' . $this->hooks['menu_category_groups'], array (&$this, 'actionLoadPageHook_CategoryGroup' ) );
		add_action( 'load-' . $this->hooks['menu_faq'], array (&$this, 'actionLoadPageHook_faq' ) );
	}

	/**
	 * Setup everything needed for the Overview page
	 *
	 */
	function actionLoadPageHook_Overview ()
	{
		// Add metaboxes
		add_meta_box( 'avhecBoxCategoryGroupList', __( 'Group Overview', 'avh-ec' ), array (&$this, 'metaboxCategoryGroupList' ), $this->hooks['menu_overview'], 'normal', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_script( 'avhec-categorygroup-js' );
		wp_enqueue_style( 'avhec-admin-css' );
	}

	/**
	 * Menu Page Overview
	 *
	 * @return none
	 */
	function doMenuOverview ()
	{
		global $screen_layout_columns;

		// This box can't be unselectd in the the Screen Options
		add_meta_box( 'avhecBoxAnnouncements', __( 'Announcements', 'avh-ec' ), array (&$this, 'metaboxAnnouncements' ), $this->hooks['menu_overview'], 'side', '' );
		add_meta_box( 'avhecBoxDonations', __( 'Donations', 'avh-ec' ), array (&$this, 'metaboxDonations' ), $this->hooks['menu_overview'], 'side', '' );

		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}

		echo '<div class="wrap avhec-wrap">';
		echo $this->displayIcon( 'index' );
		echo '<h2>' . 'AVH Extended Categories - ' . __( 'Overview', 'avh-ec' ) . '</h2>';
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_overview'], 'normal', '' );
		echo "			</div>";
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_overview'], 'side', '' );
		echo '			</div>';
		echo '		</div>';

		echo '<br class="clear"/>';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '</div>'; // wrap


		$this->printMetaboxGeneralNonces();
		$this->printMetaboxJS( 'overview' );
		$this->printAdminFooter();
	}

	/**
	 * Setup everything needed for the General Options page
	 *
	 */
	function actionLoadPageHook_General ()
	{
		// Add metaboxes
		add_meta_box( 'avhecBoxOptions', __( 'Options', 'avh-ec' ), array (&$this, 'metaboxOptions' ), $this->hooks['menu_general'], 'normal', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_style( 'avhec-admin-css' );
	}

	/**
	 * Menu Page General Options
	 *
	 * @return none
	 */
	function doMenuGeneral ()
	{
		global $screen_layout_columns;

		$groups = get_terms( $this->catgrp->taxonomy_name, array ('hide_empty' => FALSE ) );
		foreach ( $groups as $group ) {
			$group_id[] = $group->term_id;
			$groupname[] = $group->name;
		}

		$options_general[] = array ('avhec[general][alternative_name_select_category]', '<em>Select Category</em> Alternative', 'text', 20, 'Alternative text for Select Category.' );
		$options_general[] = array ('avhec[cat_group][home_group]', 'Home Group', 'dropdown', $group_id, $groupname, 'Select which group to show on the home page.<br />Selecting the group \'none\' will not show the widget on the page.' );
		$options_general[] = array ('avhec[cat_group][no_group]', 'Nonexistence Group', 'dropdown', $group_id, $groupname, 'Select which group to show when there is no group associated with the post.<br />Selecting the group \'none\' will not show the widget on the page.' );
		$options_general[] = array ('avhec[cat_group][default_group]', 'Default Group', 'dropdown', $group_id, $groupname, 'Select which group will be the default group when editing a post.<br />Selecting the group \'none\' will not show the widget on the page.' );

		if ( isset( $_POST['updateoptions'] ) ) {
			check_admin_referer( 'avh_ec_generaloptions' );

			$formoptions = $_POST['avhec'];
			$options = $this->core->getOptions();

			//$all_data = array_merge( $options_general );
			$all_data = $options_general;
			foreach ( $all_data as $option ) {
				$section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
				$section = substr( $section, 0, strpos( $section, '][' ) );
				$option_key = rtrim( $option[0], ']' );
				$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );

				switch ( $section )
				{
					case 'general' :
					case 'cat_group' :
						$current_value = $options[$section][$option_key];
						break;
				}
				// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to 0.
				$newval = (isset( $formoptions[$section][$option_key] ) ? attribute_escape( $formoptions[$section][$option_key] ) : 0);
				if ( $newval != $current_value ) { // Only process changed fields.
					switch ( $section )
					{
						case 'general' :
						case 'cat_group' :
							$options[$section][$option_key] = $newval;
							break;
					}
				}
			}
			$this->core->saveOptions( $options );
			$this->message = __( 'Options saved', 'avh-ec' );
			$this->status = 'updated fade';

		}
		$this->displayMessage();

		$actual_options = $this->core->getOptions();
		foreach ( $actual_options['cat_group'] as $key => $value ) {
			if ( ! (in_array( $value, ( array ) $group_id )) ) {
				$actual_options['cat_group'][$key] = $this->catgrp->getTermIDBy( 'slug', 'none' );
			}
		}

		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}
		$data['options_general'] = $options_general;
		$data['actual_options'] = $actual_options;

		// This box can't be unselectd in the the Screen Options
		add_meta_box( 'avhecBoxDonations', __( 'Donations', 'avh-ec' ), array (&$this, 'metaboxDonations' ), $this->hooks['menu_general'], 'side', 'core' );

		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}

		echo '<div class="wrap avhec-wrap">';
		echo $this->displayIcon( 'index' );
		echo '<h2>' . 'AVH Extended Categories - ' . __( 'General Options', 'avh-ec' ) . '</h2>';
		$admin_base_url = $this->core->info['siteurl'] . '/wp-admin/admin.php?page=';
		echo '<form name="avhec-generaloptions" id="avhec-generaloptions" method="POST" action="' . $admin_base_url . 'avhec-general' . '" accept-charset="utf-8" >';
		wp_nonce_field( 'avh_ec_generaloptions' );

		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '		<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_general'], 'normal', $data );
		echo "			</div>";
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_general'], 'side', $data );
		echo '			</div>';
		echo '		</div>';

		echo '<br class="clear"/>';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '<p class="submit"><input	class="button"	type="submit" name="updateoptions" value="' . __( 'Save Changes', 'avhf-ec' ) . '" /></p>';
		echo '</form>';

		echo '</div>'; // wrap


		$this->printMetaboxGeneralNonces();
		$this->printMetaboxJS( 'general' );
		$this->printAdminFooter();
	}

	/**
	 * Options Metabox
	 *
	 */
	function metaboxOptions ( $data )
	{
		echo $this->printOptions( $data['options_general'], $data['actual_options'] );
	}

	/**
	 * Setup everything needed for the Category Group page
	 *
	 */
	function actionLoadPageHook_CategoryGroup ()
	{

		// Add metaboxes
		add_meta_box( 'avhecBoxCategoryGroupAdd', __( 'Add Group', 'avh-ec' ), array (&$this, 'metaboxCategoryGroupAdd' ), $this->hooks['menu_category_groups'], 'normal', 'core' );
		add_meta_box( 'avhecBoxCategoryGroupList', __( 'Group Overview', 'avh-ec' ), array (&$this, 'metaboxCategoryGroupList' ), $this->hooks['menu_category_groups'], 'side', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'avhec-categorygroup-js' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_style( 'avhec-admin-css' );
	}

	/**
	 * Menu Page Category Group
	 *
	 * @return none
	 */
	function doMenuCategoryGroup ()
	{
		global $screen_layout_columns;

		$data_add_group_default = array ('name' => '', 'slug' => '', 'description' => '' );
		$data_add_group_new = $data_add_group_default;

		$options_add_group[] = array ('avhec_add_group[add][name]', ' Group Name', 'text', 20, 'The name is used to identify the group.' );
		$options_add_group[] = array ('avhec_edit_group[add][slug]', ' Slug Group', 'text', 20, 'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.' );
		$options_add_group[] = array ('avhec_add_group[add][description]', ' Description', 'textarea', 40, 'Description is not prominent by default.', 5 );

		$options_edit_group[] = array ('avhec_edit_group[edit][name]', ' Group Name', 'text', 20, 'The name is used to identify the group.' );
		$options_edit_group[] = array ('avhec_edit_group[edit][slug]', ' Slug Group', 'text', 20, 'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.' );
		$options_edit_group[] = array ('avhec_edit_group[edit][description]', ' Description', 'textarea', 40, 'Description is not prominent by default.', 5 );
		$options_edit_group[] = array ('avhec_edit_group[edit][categories]', ' Categories', 'catlist', 0, 'Select categories to be included in the group.' );

		if ( isset( $_POST['addgroup'] ) ) {
			check_admin_referer( 'avh_ec_addgroup' );

			$formoptions = $_POST['avhec_add_group'];

			$data_add_group_new['name'] = $formoptions['add']['name'];
			$data_add_group_new['slug'] = empty( $formoptions['add']['slug'] ) ? sanitize_title( $data_add_group_new['name'] ) : sanitize_title( $formoptions['add']['slug'] );
			$data_add_group_new['decsription'] = $formoptions['add']['description'];

			$id = $this->catgrp->getTermIDBy( 'slug', $data_add_group_new['slug'] );
			if ( ! $id ) {
				$group_id = $this->catgrp->doInsertGroup( $data_add_group_new['name'], array ('description' => $data_add_group_new['description'], 'slug' => $data_add_group_new['slug'] ) );
				$this->catgrp->setCategoriesForGroup( $group_id );
				$this->message = __( 'Category group saved', 'avh-ec' );
				$this->status = 'updated fade';
				$data_add_group_new = $data_add_group_default;

			} else {
				$group = $this->catgrp->getGroup( $id );
				$this->message = __( 'Category group conflicts with ', 'avh-ec' ) . $group->name;
				$this->message .= '<br />' . __( 'Same slug is used. ', 'avh-ec' );
				$this->status = 'error';

			}
			$this->displayMessage();
		}
		$data_add_group['add'] = $data_add_group_new;
		$data['add'] = array ('form' => $options_add_group, 'data' => $data_add_group );

		if ( isset( $_GET['action'] ) ) {
			$action = $_GET['action'];

			switch ( $action )
			{
				case 'edit' :
					$group_id = ( int ) $_GET['group_ID'];
					$group = $this->catgrp->getGroup( $group_id );
					$cats = $this->catgrp->getCategoriesFromGroup( $group_id );

					$data_edit_group['edit'] = array ('group_id' => $group_id, 'name' => $group->name, 'slug' => $group->slug, 'description' => $group->description, 'categories' => $cats );
					$data['edit'] = array ('form' => $options_edit_group, 'data' => $data_edit_group );

					add_meta_box( 'avhecBoxCategoryGroupEdit', __( 'Edit Group', 'avh-ec' ) . ': ' . $group->name, array (&$this, 'metaboxCategoryGroupEdit' ), $this->hooks['menu_category_groups'], 'normal', 'low' );
					break;
				case 'delete' :
					if ( ! isset( $_GET['group_ID'] ) ) {
						wp_redirect( $this->getBackLink() );
						exit();
					}

					$group_id = ( int ) $_GET['group_ID'];
					check_admin_referer( 'delete-avhecgroup_' . $group_id );

					if ( ! current_user_can( 'manage_categories' ) ) {
						wp_die( __( 'Cheatin&#8217; uh?' ) );
					}
					$this->catgrp->doDeleteGroup( $group_id );
					break;
				default :
					;
					break;
			}
		}

		if ( isset( $_POST['editgroup'] ) ) {
			check_admin_referer( 'avh_ec_editgroup' );

			$formoptions = $_POST['avhec_edit_group'];
			$selected_categories = $_POST['post_category'];

			$group_id = ( int ) $_POST['avhec-group_id'];
			$group = $this->catgrp->getGroup( $group_id );
			if ( is_object( $group ) ) {
				$id = wp_update_term( $group->term_id, $this->catgrp->taxonomy_name, array ('name' => $formoptions['edit']['name'], 'slug' => $formoptions['edit']['slug'], 'description' => $formoptions['edit']['description'] ) );
				if ( ! is_wp_error( $id ) ) {
					$this->catgrp->setCategoriesForGroup( $group_id, $selected_categories );
					$this->message = __( 'Category group updated', 'avh-ec' );
					$this->status = 'updated fade';
				} else {
					$this->message = __( 'Category group not updated', 'avh-ec' );
					$this->message .= '<br />' . __( 'Duplicate slug detected', 'avh-ec' );
					$this->status = 'error';
				}
			} else {
				$this->message = __( 'Unknown category group', 'avh-ec' );
				$this->status = 'error';
			}
			$this->displayMessage();
		}

		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}

		// This box can't be unselectd in the the Screen Options
		//add_meta_box( 'avhecBoxDonations', __( 'Donations', 'avh-ec' ), array (&$this, 'metaboxDonations' ), $this->hooks['menu_category_groups'], 'side', 'core' );


		echo '<div class="wrap avhec-wrap">';
		echo $this->displayIcon( 'index' );
		echo '<h2>' . 'AVH Extended Categories - ' . __( 'Category Groups', 'avh-ec' ) . '</h2>';
		$admin_base_url = $this->core->info['siteurl'] . '/wp-admin/admin.php?page=';

		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';

		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_category_groups'], 'normal', $data );
		echo "			</div>";

		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_category_groups'], 'side', $data );
		echo '			</div>';

		echo '		</div>'; // dashboard-widgets
		echo '<br class="clear" />';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '</div>'; // wrap


		$this->printMetaboxGeneralNonces();
		$this->printMetaboxJS( 'grouped' );
		$this->printAdminFooter();
	}

	/**
	 * Metabox for Adding a group
	 * @param $data
	 */
	function metaboxCategoryGroupAdd ( $data )
	{
		echo '<form name="avhec-addgroup" id="avhec-addgroup" method="POST" action="' . $this->getBackLink() . '" accept-charset="utf-8" >';
		wp_nonce_field( 'avh_ec_addgroup' );
		echo $this->printOptions( $data['add']['form'], $data['add']['data'] );
		echo '<p class="submit"><input	class="button"	type="submit" name="addgroup" value="' . __( 'Add group', 'avh-ec' ) . '" /></p>';
		echo '</form>';
	}

	/**
	 * Metabox for showing the groups as a list
	 *
	 * @param $data
	 */
	function metaboxCategoryGroupList ( $data )
	{
		echo '<form id="posts-filter" action="" method="get">';

		echo '<div class="clear"></div>';

		echo '<table class="widefat fixed" cellspacing="0">';
		echo '<thead>';
		echo '<tr>';
		print_column_headers( 'categories_group' );
		echo '</tr>';
		echo '</thead>';

		echo '<tfoot>';
		echo '<tr>';
		print_column_headers( 'categories_group', false );
		echo '</tr>';
		echo '</tfoot>';

		echo '<tbody id="the-list" class="list:group">';
		$this->printCategoryGroupRows();
		echo '</tbody>';
		echo '</table>';

		echo '<br class="clear" />';
		echo '</form>';

	//echo '</div>';
	}

	/**
	 * Metabox Category Group Edit
	 *
	 */
	function metaboxCategoryGroupEdit ( $data )
	{
		echo '<form name="avhec-editgroup" id="avhec-editgroup" method="POST" action="' . $this->getBackLink() . '" accept-charset="utf-8" >';
		wp_nonce_field( 'avh_ec_editgroup' );
		echo $this->printOptions( $data['edit']['form'], $data['edit']['data'] );
		echo '<input type="hidden" value="' . $data['edit']['data']['edit']['group_id'] . '" name="avhec-group_id" id="avhec-group_id">';
		echo '<p class="submit"><input	class="button"	type="submit" name="editgroup" value="' . __( 'Update group', 'avh-ec' ) . '" /></p>';
		echo '</form>';
	}

	/**
	 * Setup everything needed for the FAQ page
	 *
	 */
	function actionLoadPageHook_faq ()
	{

		add_meta_box( 'avhecBoxFAQ', __( 'F.A.Q.', 'avh-ec' ), array (&$this, 'metaboxFAQ' ), $this->hooks['menu_faq'], 'normal', 'core' );
		add_meta_box( 'avhecBoxTranslation', __( 'Translation', 'avh-ec' ), array (&$this, 'metaboxTranslation' ), $this->hooks['menu_faq'], 'normal', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_style( 'avhec-admin-css' );

	}

	/**
	 * Menu Page FAQ
	 *
	 * @return none
	 */
	function doMenuFAQ ()
	{
		global $screen_layout_columns;

		// This box can't be unselectd in the the Screen Options
		add_meta_box( 'avhecBoxAnnouncements', __( 'Announcements', 'avh-ec' ), array (&$this, 'metaboxAnnouncements' ), $this->hooks['menu_faq'], 'side', 'core' );
		add_meta_box( 'avhecBoxDonations', __( 'Donations', 'avh-ec' ), array (&$this, 'metaboxDonations' ), $this->hooks['menu_faq'], 'side', 'core' );

		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}

		echo '<div class="wrap avhec-wrap">';
		echo $this->displayIcon( 'index' );
		echo '<h2>' . 'AVH Extended Categories - ' . __( 'F.A.Q', 'avh-ec' ) . '</h2>';
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_faq'], 'normal', '' );
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->hooks['menu_faq'], 'side', '' );
		echo '			</div>';
		echo '		</div>';
		echo '<br class="clear"/>';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '</div>'; // wrap


		$this->printMetaboxGeneralNonces();
		$this->printMetaboxJS( 'faq' );
		$this->printAdminFooter();
	}

	/**
	 * Translation Metabox
	 * @return unknown_type
	 */
	function metaboxTranslation ()
	{
		echo '<p>A language pack can be created for this plugin. The .pot file is included with the plugin and can be found in the directory extended-categories-widget/2.8/lang';
		echo 'If you have created a language pack you can send the .po, and if you have it the .mo file, to me and I will include the files with the plugin';
		echo 'More information about translating can found at http://codex.wordpress.org/Translating_WordPress . This page is dedicated for translating WordPress but the instructions are the same for this plugin.';
		echo '</p>';
		echo '<p>';
		echo 'I have also setup a project in Launchpad for translating the plugin. Just visit <a href="http://bit.ly/95WyJ" target="_blank" title="AVH Extended Categories Translation Project">http://bit.ly/95WyJ</a>';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">Available Languages</span></p><p>';
		echo 'Czech - Čeština (cs_CZ)  in Launchpad - Dirty Mind - <a href="http://dirtymind.ic.cz" target="_blank">http://dirtymind.ic.cz</a><br />';
		echo 'Spanish - Español (es_ES) in Launchpad<br />';
		echo 'Italian - Italiano (it_IT) in Launchpad - Gianni Diurno - <a href="http://gidibao.net" target="_blank">http://gidibao.net</a><br />';
		echo 'French - Français (fr_FR) in Launchpad - BeAlCoSt - <a href="http://www.aclanester56.com/" target="_blank">http://www.aclanester56.com/</a><br />';
		echo '</p>';
	}

	/**
	 * Donation Metabox
	 * @return unknown_type
	 */
	function metaboxDonations ()
	{
		echo '<p>If you enjoy this plug-in please consider a donation. There are several ways you can show your appreciation</p>';
		echo '<p>';
		echo '<span class="b">Amazon</span><br />';
		echo 'If you decide to buy something from Amazon click the button.<br />';
		echo '<a href="https://www.amazon.com/?&tag=avh-donation-20" target="_blank" title="Amazon Homepage"><img alt="Amazon Button" src="' . $this->core->info['graphics_url'] . '/us_banner_logow_120x60.gif" /></a></p>';
		echo '<p>';
		echo 'You can send me something from my <a href="http://www.amazon.com/gp/registry/wishlist/1U3DTWZ72PI7W?tag=avh-donation-20">Amazon Wish List</a>';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">Through Paypal.</span><br />';
		echo 'Click on the Donate button and you will be directed to Paypal where you can make your donation and you don\'t need to have a Paypal account to make a donation.<br />';
		echo '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=S85FXJ9EBHAF2&lc=US&item_name=AVH%20Plugins&item_number=fdas&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted" target="_blank" title="Donate">';
		echo '<img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="Donate"/></a>';
		echo '</p>';
	}

	/***
	 * F.A.Q Metabox
	 * @return none
	 */
	function metaboxFAQ ()
	{

		echo '<p>';
		echo '<span class="b">What about support?</span><br />';
		echo 'I created a support site at http://forums.avirtualhome.com where you can ask questions or request features.<br />';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">What is depth selection?</span><br />';
		echo 'Starting with version 2.0 and WordPress 2.8 you can select how many levels deep you want to show your categories. This option only works when you select Show Hierarchy as well.<br /><br />';
		echo 'Here is how it works: Say you have 5 top level categories and each top level has a number of children. You could manually select all the Top Level categories you want to show but now you can do the following:<br />';
		echo 'You select to display all categories, select to Show hierarchy and select how many levels you want to show, in this case Toplevel only.<br />';
		echo '</p>';

	}

	function metaboxAnnouncements ()
	{
		$php5 = version_compare( '5.2', phpversion(), '<' );
		echo '<p>';
		echo '<span class="b">PHP4 Support</span><br />';
		echo 'The next major release of the plugin will no longer support PHP4.<br />';
		echo 'It will be written for PHP 5.2 and ';
		if ( $php5 ) {
			echo 'your blog already runs the needed PHP version. When the new release comes out you can safely update.<br />';
		} else {
			echo 'your blog still runs PHP4. When the new release comes out you can not use it.<br />';
			echo 'I don\'t have a timeline for the next version but consider contacting your host if PHP 5.2 is available.<br />';
			echo 'If your hosts doesn\'t offer PHP 5.2 you might want to consider switching hosts.<br />';
			echo 'A host to consider is <a href="http://www.lunarpages.com/id/pdoes" target="_blank">Lunarpages</a>.';
			echo 'I run my personal blog there and I am very happy with their services. You can get an account with unlimited bandwidth, storage and much more for a low price.';
		}
		echo '</p>';

	}

	/**
	 * Sets the amount of columns wanted for a particuler screen
	 *
	 * @WordPress filter screen_meta_screen
	 * @param $screen
	 * @return strings
	 */

	function filterScreenLayoutColumns ( $columns, $screen )
	{
		switch ( $screen )
		{
			case $this->hooks['menu_overview'] :
				$columns[$this->hooks['menu_overview']] = 2;
				break;
			case $this->hooks['menu_general'] :
				$columns[$this->hooks['menu_general']] = 2;
				break;
			case $this->hooks['menu_category_groups'] :
				$columns[$this->hooks['menu_category_groups']] = 2;
				break;
			case $this->hooks['menu_faq'] :
				$columns[$this->hooks['menu_faq']] = 2;
				break;

		}
		return $columns;
	}

	/**
	 * Adds Settings next to the plugin actions
	 *
	 * @WordPress Filter plugin_action_links_avh-amazon/avh-amazon.php
	 *
	 */
	function filterPluginActions ( $links, $file )
	{
		$settings_link = '<a href="admin.php?page=extended-categories-widget">' . __( 'Settings', 'avh-ec' ) . '</a>';
		array_unshift( $links, $settings_link ); // before other links
		return $links;

	}

	/**
	 * Creates a new array for columns headers. Used in print_column_headers. The filter is called from get_column_headers
	 *
	 * @param $columns
	 * @return Array
	 * @see print_column_headers
	 * @see get_column_headers
	 */
	function filterManageCategoriesGroupColumns ( $columns )
	{
		$categories_group_columns = array ('name' => __( 'Name', 'avh-ec' ), 'slug' => 'Slug', 'description' => __( 'Description', 'avh-ec' ), 'cat-in-group' => __( 'Categories in the group', 'avh-ec' ) );
		return $categories_group_columns;
	}

	/**
	 * When not using AJAX, this function is called when the deletion fails.
	 *
	 * @param string $text
	 * @param int $group_id
	 * @return string
	 * @WordPress Filter explain_nonce_$verb-$noun
	 * @see wp_explain_nonce
	 */
	function filterExplainNonceDeleteGroup ( $text, $group_id )
	{
		$group = get_term( $group_id, $this->catgrp->taxonomy_name, OBJECT, 'display' );

		$return = sprintf( __( 'Your attempt to delete this group: &#8220;%s&#8221; has failed.' ), $group->name );
		return ($return);
	}

	############## Admin WP Helper ##############


	/**
	 * Get the backlink for forms
	 *
	 * @return strings
	 */
	function getBackLink ()
	{
		$page = basename( __FILE__ );
		if ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) ) {
			$page = preg_replace( '[^a-zA-Z0-9\.\_\-]', '', $_GET['page'] );
		}

		if ( function_exists( "admin_url" ) )
			return admin_url( basename( $_SERVER["PHP_SELF"] ) ) . "?page=" . $page;
		else
			return $_SERVER['PHP_SELF'] . "?page=" . $page;
	}

	/**
	 * Print all Category Group rows
	 *
	 * @uses printCategoryGroupRow
	 *
	 */
	function printCategoryGroupRows ()
	{
		$cat_groups = get_terms( $this->catgrp->taxonomy_name, array ('hide_empty' => FALSE ) );

		foreach ( $cat_groups as $group ) {
			if ( 'none' != $group->slug ) {
				echo $this->printCategoryGroupRow( $group->term_id, $group->term_taxonomy_id );
			}
		}
	}

	/**
	 * Displays all the information of a group in a row
	 * Adds inline link for delete and/or edit.
	 *
	 * @param int $group_term_id
	 * @param int $group_term_taxonomy_id
	 */
	function printCategoryGroupRow ( $group_term_id, $group_term_taxonomy_id )
	{
		static $row_class = '';

		$group = get_term( $group_term_id, $this->catgrp->taxonomy_name, OBJECT, 'display' );

		$no_edit[$this->catgrp->getTermIDBy( 'slug', 'all' )] = 0;
		$no_delete[$this->catgrp->getTermIDBy( 'slug', 'all' )] = 0;

		if ( current_user_can( 'manage_categories' ) ) {
			$actions = array ();
			if ( ! array_key_exists( $group->term_id, $no_edit ) ) {
				$edit_link = "admin.php?page=avhec-grouped&amp;action=edit&amp;group_ID=$group->term_id";
				$edit = "<a class='row-title' href='$edit_link' title='" . esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $group->name ) ) . "'>" . esc_attr( $group->name ) . '</a><br />';

				$actions['edit'] = '<a href="' . $edit_link . '">' . __( 'Edit' ) . '</a>';
			} else {
				$edit = esc_attr( $group->name );
			}
			if ( ! (array_key_exists( $group->term_id, $no_delete )) ) {
				$actions['delete'] = "<a class='delete:the-list:group-$group->term_id submitdelete' href='" . wp_nonce_url( "admin.php?page=avhec-grouped&amp;action=delete&amp;group_ID=$group->term_id", 'delete-avhecgroup_' . $group->term_id ) . "'>" . __( 'Delete' ) . "</a>";
			}
			$action_count = count( $actions );
			$i = 0;
			$edit .= '<div class="row-actions">';
			foreach ( $actions as $action => $link ) {
				++ $i;
				($i == $action_count) ? $sep = '' : $sep = ' | ';
				$edit .= "<span class='$action'>$link$sep</span>";
			}
			$edit .= '</div>';
		} else {
			$edit = $group->name;
		}

		$row_class = 'alternate' == $row_class ? '' : 'alternate';
		$qe_data = get_term( $group->term_id, $this->catgrp->taxonomy_name, OBJECT, 'edit' );

		$output = "<tr id='group-$group->term_id' class='iedit $row_class'>";

		$columns = get_column_headers( 'categories_group' );
		$hidden = get_hidden_columns( 'categories_group' );
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = 'class="' . $column_name . ' column-' . $column_name . '"';

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = $class . $style;

			switch ( $column_name )
			{
				case 'cb' :
					$output .= '<th scope="row" class="check-column">';
					if ( ! (array_key_exists( $group->term_id, $no_delete )) ) {
						$output .= '<input type="checkbox" name="delete[]" value="' . $group->term_id . '" />';
					} else {
						$output .= "&nbsp;";
					}
					$output .= '</th>';
					break;
				case 'name' :
					$output .= '<td ' . $attributes . '>' . $edit;
					$output .= '<div class="hidden" id="inline_' . $qe_data->term_id . '">';
					$output .= '<div class="name">' . $qe_data->name . '</div>';
					$output .= '<div class="slug">' . apply_filters( 'editable_slug', $qe_data->slug ) . '</div>';
					$output .= '</div></td>';
					break;
				case 'description' :
					$output .= '<td ' . $attributes . '>' . $qe_data->description . '</td>';
					break;
				case 'slug' :
					$output .= "<td $attributes>" . apply_filters( 'editable_slug', $qe_data->slug ) . "</td>";
					break;
				case 'cat-in-group' :
					$cats = $this->catgrp->getCategoriesFromGroup( $group_term_id );
					$catname = array ();
					foreach ( $cats as $cat_id ) {
						$catname[] = get_cat_name( $cat_id );
					}
					natsort( $catname );
					$cat = implode( ', ', $catname );
					$output .= '<td ' . $attributes . '>' . $cat . '</td>';
					break;

			}
		}
		$output .= '</tr>';

		return $output;
	}

	/**
	 * Prints the general nonces, used by the AJAX
	 */
	function printMetaboxGeneralNonces ()
	{
		echo '<form style="display:none" method="get" action="">';
		echo '<p>';
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		echo '</p>';
		echo '</form>';

	}

	/**
	 * Print the Metabox JS for toggling closed and open
	 *
	 * @param $boxid
	 */
	function printMetaboxJS ( $boxid )
	{
		$a = $this->hooks['menu_' . $boxid];
		echo '<script type="text/javascript">' . "\n";
		echo '	//<![CDATA[' . "\n";
		echo '	jQuery(document).ready( function($) {' . "\n";
		echo '		$(\'.if-js-closed\').removeClass(\'if-js-closed\').addClass(\'closed\');' . "\n";
		echo '		// postboxes setup' . "\n";
		echo '		postboxes.add_postbox_toggles(\'' . $a . '\');' . "\n";
		echo '	});' . "\n";
		echo '	//]]>' . "\n";
		echo '</script>';

	}

	/**
	 * Display plugin Copyright
	 *
	 */
	function printAdminFooter ()
	{
		echo '<p class="footer_avhec">';
		printf( '&copy; Copyright 2009 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH Extended Categories Version %s', $this->core->version );
		echo '</p>';
	}

	/**
	 * Display WP alert
	 *
	 */
	function displayMessage ()
	{
		if ( $this->message != '' ) {
			$message = $this->message;
			$status = $this->status;
			$this->message = $this->status = ''; // Reset
		}
		if ( isset( $message ) ) {
			$status = ($status != '') ? $status : 'updated fade';
			echo '<div id="message"	class="' . $status . '">';
			echo '<p><strong>' . $message . '</strong></p></div>';
		}
	}

	/**
	 * Ouput formatted options
	 *
	 * @param array $option_data
	 * @return string
	 */
	function printOptions ( $option_data, $option_actual )
	{
		// Generate output
		$output = '';
		$output .= "\n" . '<table class="form-table avhec-options">' . "\n";
		foreach ( $option_data as $option ) {
			$section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
			$section = substr( $section, 0, strpos( $section, '][' ) );
			$option_key = rtrim( $option[0], ']' );
			$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );
			// Helper
			if ( $option[2] == 'helper' ) {
				$output .= '<tr style="vertical-align: top;"><td class="helper" colspan="2">' . $option[4] . '</td></tr>' . "\n";
				continue;
			}
			switch ( $option[2] )
			{
				case 'checkbox' :
					$input_type = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option[3] ) . '" ' . $this->isChecked( '1', $option_actual[$section][$option_key] ) . ' />' . "\n";
					$explanation = $option[4];
					break;
				case 'dropdown' :
					$selvalue = $option[3];
					$seltext = $option[4];
					$seldata = '';
					foreach ( ( array ) $selvalue as $key => $sel ) {
						$seldata .= '<option value="' . $sel . '" ' . (($option_actual[$section][$option_key] == $sel) ? 'selected="selected"' : '') . ' >' . ucfirst( $seltext[$key] ) . '</option>' . "\n";
					}
					$input_type = '<select id="' . $option[0] . '" name="' . $option[0] . '">' . $seldata . '</select>' . "\n";
					$explanation = $option[5];
					break;
				case 'text-color' :
					$input_type = '<input type="text" ' . (($option[3] > 1) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option_actual[$section][$option_key] ) . '" size="' . $option[3] . '" /><div class="box_color ' . $option[0] . '"></div>' . "\n";
					$explanation = $option[4];
					break;
				case 'textarea' :
					$input_type = '<textarea rows="' . $option[5] . '" ' . (($option[3] > 1) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" size="' . $option[3] . '" />' . attribute_escape( $option_actual[$section][$option_key] ) . '</textarea>';
					$explanation = $option[4];
					break;
				case 'catlist' :
					ob_start();
					echo '<div id="avhec-catlist">';
					echo '<ul>';
					wp_category_checklist( 0, 0, $option_actual[$section][$option_key] );
					echo '</ul>';
					echo '</div>';
					$input_type = ob_get_contents();
					ob_end_clean();
					$explanation = $option[4];
					break;
				case 'text' :
				default :
					$input_type = '<input type="text" ' . (($option[3] > 1) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option_actual[$section][$option_key] ) . '" size="' . $option[3] . '" />' . "\n";
					$explanation = $option[4];
					break;
			}
			// Additional Information
			$extra = '';
			if ( $explanation ) {
				$extra = '<br /><span class="description">' . __( $explanation ) . '</span>' . "\n";
			}
			// Output
			$output .= '<tr style="vertical-align: top;"><th align="left" scope="row"><label for="' . $option[0] . '">' . __( $option[1] ) . '</label></th><td>' . $input_type . '	' . $extra . '</td></tr>' . "\n";
		}
		$output .= '</table>' . "\n";
		return $output;
	}

	/**
	 * Used in forms to set an option checked
	 *
	 * @param mixed $checked
	 * @param mixed $current
	 * @return strings
	 */
	function isChecked ( $checked, $current )
	{
		$return = '';
		if ( $checked == $current ) {
			$return = ' checked="checked"';
		}
		return $return;
	}

	/**
	 * Displays the icon on the menu pages
	 *
	 * @param $icon
	 */
	function displayIcon ( $icon )
	{
		return ('<div class="icon32" id="icon-' . $icon . '"><br/></div>');
	}

	/**
	 * Ajax Helper: inline delete of the groups
	 */
	function ajaxDeleteGroup ()
	{
		$group_id = isset( $_POST['id'] ) ? ( int ) $_POST['id'] : 0;
		check_ajax_referer( 'delete-avhecgroup_' . $group_id );

		if ( ! current_user_can( 'manage_categories' ) ) {
			die( '-1' );
		}
		$check = $this->catgrp->getGroup( $group_id );
		if ( false === $check ) {
			die( '1' );
		}

		if ( $this->catgrp->doDeleteGroup( $group_id ) ) {
			die( '1' );
		} else {
			die( '0' );
		}
	}
}
?>