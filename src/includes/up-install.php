<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Install
 *
 * Runs on plugin install by setting up the post types, custom taxonomies,
 * flushing rewrite rules to initiate the new 'downloads' slug and also
 * creates the plugin and populates the settings fields for those plugin
 * pages. After successful install, the user is redirected to the UpStream Welcome
 * screen.
 *
 * @since 1.0
 * @global $wpdb
 * @global $upstream_options
 * @global $wp_version
 * @param  bool $network_side If the plugin is being network-activated
 * @return void
 */

/**
 * Check UpStream minimum requirements: PHP and WordPress versions.
 * This function calls wp_die() if any of the minimum requirements is not satisfied.
 *
 * @since   1.10.1
 *
 * @uses    wp_die()
 */
function upstream_check_min_requirements()
{
    global $wp_version;

    $minWPVersionRequired = "4.5";
    $minPHPVersionRequired = "5.4";

    // Check PHP version.
    if (version_compare(PHP_VERSION, $minPHPVersionRequired, '<')) {
        $errorMessage = sprintf(
            '<p>' . __('It seems you are running an outdated version of PHP: <code>%s</code>.', 'upstream') . '</p>' .
            '<p>' . __('For security reasons, UpStream requires at least PHP version <code>%s</code> to run.', 'upstream') . '</p>' .
            '<p>' . __('Please, consider upgrading your PHP version.', 'upstream') . '</p><br /><br />',
            PHP_VERSION,
            $minPHPVersionRequired
        );
    }
    // Check WordPress version.
    else if (version_compare($wp_version, $minWPVersionRequired, '<')) {
        $errorMessage = sprintf(
            '<p>' . __('It seems you are running an outdated version of WordPress: <code>%s</code>.', 'upstream') . '</p>' .
            '<p>' . __('For security reasons, UpStream requires at least version <code>%s</code> to run.', 'upstream') . '</p>' .
            '<p>' . __('Please, consider upgrading your WordPress.', 'upstream') . '</p><br /><br />',
            $wp_version,
            $minWPVersionRequired
        );
    }

    if (isset($errorMessage)) {
        $errorMessage .= '<a class="button" href="javascript:history.back();">' . __('Go Back', 'upstream') . '</a>';

        wp_die($errorMessage);
    }
}

function upstream_install( $network_wide = false ) {
    global $wpdb;

    upstream_check_min_requirements();

    if ( is_multisite() && $network_wide ) {

        foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs LIMIT 100" ) as $blog_id ) {
            switch_to_blog( $blog_id );
            upstream_run_install();
            restore_current_blog();
        }

    } else {
        upstream_run_install();
    }

}
register_activation_hook( UPSTREAM_PLUGIN_FILE, 'upstream_install' );

/**
 * Run the UpStream Instsall process
 *
 * @since  2.5
 * @return void
 */
function upstream_run_install() {

    global $wpdb, $wp_version;

    // Setup the Downloads Custom Post Type
    upstream_setup_post_types();

    // Setup the Download Taxonomies
    upstream_setup_taxonomies();

    // add the default options
    upstream_add_default_options();

    // Clear the permalinks
    flush_rewrite_rules( false );

    // Add Upgraded From Option
    $current_version = get_option( 'upstream_version' );
    if ( $current_version ) {
        update_option( 'upstream_version_upgraded_from', $current_version );
    }

    update_option( 'upstream_version', UPSTREAM_VERSION );

    // Create UpStream roles
    $roles = new UpStream_Roles;
    $roles->add_roles();
    $roles->add_caps();

    // when upgrading
    // if ( ! $current_version ) {}

    // Bail if activating from network, or bulk
    if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
        return;
    }

    // Add the transient to redirect
    set_transient( '_upstream_activation_redirect', true, 30 );

}


function upstream_add_default_options() {

    // general options
    $general = get_option( 'upstream_general' );
    if ( ! $general || empty( $general ) ) {

        $general['project']['single']   = 'Project';
        $general['project']['plural']   = 'Projects';
        $general['client']['single']    = 'Client';
        $general['client']['plural']    = 'Clients';
        $general['milestone']['single'] = 'Milestone';
        $general['milestone']['plural'] = 'Milestones';
        $general['task']['single']      = 'Task';
        $general['task']['plural']      = 'Tasks';
        $general['bug']['single']       = 'Bug';
        $general['bug']['plural']       = 'Bugs';
        $general['file']['single']      = 'File';
        $general['file']['plural']      = 'Files';

        $general['login_heading']       = 'Project Login';
        $general['admin_email']         = get_bloginfo( 'admin_email' );

        update_option( 'upstream_general', $general );
    }

    // project options
    $projects = get_option( 'upstream_projects' );
    if ( ! $projects || empty( $projects ) ) {

        $projects['statuses'][0]['name']    = 'In Progress';
        $projects['statuses'][0]['color']   = '#5cbfd1';
        $projects['statuses'][0]['type']    = 'open';

        $projects['statuses'][1]['name']    = 'Overdue';
        $projects['statuses'][1]['color']   = '#d15d5c';
        $projects['statuses'][1]['type']    = 'open';

        $projects['statuses'][2]['name']    = 'Closed';
        $projects['statuses'][2]['color']   = '#6b6b6b';
        $projects['statuses'][2]['type']    = 'closed';

        update_option( 'upstream_projects', $projects );
    }

    // milestone options
    $milestones = get_option( 'upstream_milestones' );
    if ( ! $milestones || empty( $milestones ) ) {

        $milestones['milestones'][0]['title'] = 'Wireframe';
        $milestones['milestones'][0]['color'] = '#3ca9c4';

        $milestones['milestones'][1]['title'] = 'Development';
        $milestones['milestones'][1]['color'] = '#1e73be';

        $milestones['milestones'][2]['title'] = 'Design';
        $milestones['milestones'][2]['color'] = '#21c6e0';

        $milestones['milestones'][3]['title'] = 'Testing';
        $milestones['milestones'][3]['color'] = '#146791';

        $milestones['milestones'][4]['title'] = 'Launch';
        $milestones['milestones'][4]['color'] = '#1fc1b1';

        update_option( 'upstream_milestones', $milestones );
    }

    // task options
    $tasks = get_option( 'upstream_tasks' );
    if ( ! $tasks || empty( $tasks ) ) {

        $tasks['statuses'][0]['name']   = 'In Progress';
        $tasks['statuses'][0]['color']  = '#5cbfd1';
        $tasks['statuses'][0]['type']   = 'open';

        $tasks['statuses'][1]['name']   = 'Overdue';
        $tasks['statuses'][1]['color']  = '#d15d5c';
        $tasks['statuses'][1]['type']   = 'open';

        $tasks['statuses'][2]['name']   = 'Completed';
        $tasks['statuses'][2]['color']  = '#5cd165';
        $tasks['statuses'][2]['type']   = 'closed';

        $tasks['statuses'][3]['name']   = 'Closed';
        $tasks['statuses'][3]['color']  = '#6b6b6b';
        $tasks['statuses'][3]['type']   = 'closed';

        update_option( 'upstream_tasks', $tasks );
    }

    // bug options
    $bugs = get_option( 'upstream_bugs' );
    if ( ! $bugs || empty( $bugs ) ) {

        $bugs['statuses'][0]['name']    = 'In Progress';
        $bugs['statuses'][0]['color']   = '#5cbfd1';
        $bugs['statuses'][0]['type']    = 'open';

        $bugs['statuses'][1]['name']    = 'Overdue';
        $bugs['statuses'][1]['color']   = '#d15d5c';
        $bugs['statuses'][1]['type']    = 'open';

        $bugs['statuses'][2]['name']    = 'Completed';
        $bugs['statuses'][2]['color']   = '#5cd165';
        $bugs['statuses'][2]['type']    = 'closed';

        $bugs['statuses'][3]['name']    = 'Closed';
        $bugs['statuses'][3]['color']   = '#6b6b6b';
        $bugs['statuses'][3]['type']    = 'closed';

        $bugs['severities'][0]['name']  = 'Critical';
        $bugs['severities'][0]['color'] = '#d15d5c';

        $bugs['severities'][1]['name']  = 'Standard';
        $bugs['severities'][1]['color'] = '#d17f5c';

        $bugs['severities'][2]['name']  = 'Minor';
        $bugs['severities'][2]['color'] = '#d1a65c';

        update_option( 'upstream_bugs', $bugs );
    }

}



/**
 * When a new Blog is created in multisite, see if UpStream is network activated, and run the installer
 *
 * @since  1.0.0
 * @param  int    $blog_id The Blog ID created
 * @param  int    $user_id The User ID set as the admin
 * @param  string $domain  The URL
 * @param  string $path    Site Path
 * @param  int    $site_id The Site ID
 * @param  array  $meta    Blog Meta
 * @return void
 */
function upstream_new_blog_created( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {

    if ( is_plugin_active_for_network( plugin_basename( UPSTREAM_PLUGIN_FILE ) ) ) {
        switch_to_blog( $blog_id );
        upstream_install();
        restore_current_blog();
    }

}
add_action( 'wpmu_new_blog', 'upstream_new_blog_created', 10, 6 );



/**
 * Post-installation
 *
 * Runs just after plugin installation and exposes the
 * upstream_after_install hook.
 *
 * @since 1.0.0
 * @return void
 */
function upstream_after_install() {

    if ( ! is_admin() ) {
        return;
    }

    $activated = get_transient( '_upstream_activation_redirect' );

    if ( false !== $activated ) {

        // add the default options
        //upstream_add_default_project();
        delete_transient( '_upstream_activation_redirect' );

        if( ! isset( $_GET['activate-multi'] ) ) {
            set_transient( '_upstream_redirected', true, 360 );
            wp_redirect(admin_url('post-new.php?post_type=project'));
            exit;
        }

    }

}
add_action( 'admin_init', 'upstream_after_install' );


function upstream_install_success_notice() {

    $redirected = get_transient( '_upstream_redirected' );

    if ( false !== $redirected && isset( $_GET['page'] ) && $_GET['page'] == 'upstream_general' ) {
        // Delete the transient
        //delete_transient( '_upstream_redirected' );

        $class = 'notice notice-info is-dismissible';
        $message = '<strong>' . __( 'Success! UpStream is up and running.', 'upstream' ) . '</strong><br>';
        $message .= __( 'Step 1. Please go through each settings tab below and configure the options.', 'upstream' ) . '<br>';
        $message .= __( 'Step 2. Add a new Client by navigating to <strong>Projects > New Client</strong>', 'upstream' ) . '<br>';
        $message .= __( 'Step 3. Add your first Project by navigating to <strong>Projects > New Project</strong>', 'upstream' ) . '<br>';

        printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
    }
}
add_action( 'admin_notices', 'upstream_install_success_notice' );

// function upstream_add_default_project() {

//  // Create project object
//  $project = array(
//    'post_title'      => 'Default UpStream Project',
//    'post_content'    => '',
//    'post_status'     => 'publish',
//    'post_author'     => get_current_user_id(),
//    'post_type'       => 'project',
//  );

//  // Insert the post into the database
//  $id = wp_insert_post( $project );

//  if( $id ) {

//      $milestones[0] = array(
//          'milestone'     => 'Wireframe',
//          'start_date'    => current_time( 'timestamp', false ),
//          'end_date'      => current_time( 'timestamp' ) + ( 60 * 60 * 24 * 7 ),
//      );
//      update_post_meta( $id, '_upstream_project_milestones', $milestones );

//      $tasks[0] = array(
//          'title'         => 'Test Task number 1',
//          'start_date'    => current_time( 'timestamp', false ),
//          'end_date'      => current_time( 'timestamp' ) + ( 60 * 60 * 24 * 5 ),
//          'status'        => 'In Progress',
//          'progress'      => '50',
//      );
//      update_post_meta( $id, '_upstream_project_tasks', $tasks );

//      update_post_meta( $id, '_upstream_project_start', current_time( 'timestamp', false ) );
//      update_post_meta( $id, '_upstream_project_end', current_time( 'timestamp' ) + ( 60 * 60 * 24 * 7 ) );
//      update_post_meta( $id, '_upstream_project_owner', get_current_user_id() );
//      update_post_meta( $id, '_upstream_project_status', 'In Progress' );

//      $project = new UpStream_Project( $id );
//      $project->update_project_meta();

//  }

// }
