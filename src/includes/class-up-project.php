<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UpStream_Project Class
 *
 * @since 1.0.0
 */
class UpStream_Project {

    /**
     * The project ID
     *
     * @since 1.0.0
     */
    public $ID = 0;

    /**
     * Meta key prefix
     *
     * @since 1.0.0
     */
    public $meta_prefix = '_upstream_project_';

    /**
     * Project Meta keys
     *
     * @since 1.0.0
     */
    public $meta = array(
        'milestones',
        'tasks',
        'bugs',
        'status',
        'owner',
        'client',
        'client_users',
        'start',
        'end',
        'files',
        'progress',
        'members',
        'comments',
        'activity',
    );

    /**
     * Declare the default properties in WP_Post as we can't extend it
     * Anything we've declared above has been removed.
     */
    public $post_author = 0;
    public $post_date = '0000-00-00 00:00:00';
    public $post_date_gmt = '0000-00-00 00:00:00';
    public $post_content = '';
    public $post_title = '';
    public $post_excerpt = '';
    public $post_status = 'publish';
    public $comment_status = 'open';
    public $ping_status = 'open';
    public $post_password = '';
    public $post_name = '';
    public $to_ping = '';
    public $pinged = '';
    public $post_modified = '0000-00-00 00:00:00';
    public $post_modified_gmt = '0000-00-00 00:00:00';
    public $post_content_filtered = '';
    public $post_parent = 0;
    public $guid = '';
    public $menu_order = 0;
    public $post_mime_type = '';
    public $comment_count = 0;
    public $filter;

    /**
     * Get things going
     *
     * @since 1.0.0
     */
    public function __construct( $_id = false, $_args = array() ) {

        // if no id is sent, then go through the varous ways of getting the id
        // may need to check the order more closely to ensure we get it right
        if( ! $_id )
            $_id = get_the_ID();
        if( ! $_id )
            $_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : false;
        if( ! $_id )
            $_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : false;
        if( ! $_id )
            $_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : false;
        if( ! $_id )
            $_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : false;

        $project = WP_Post::get_instance( $_id );

        return $this->setup_project( $project );

    }

    /**
     * Given the project data, let's set the variables
     *
     * @since  1.0.0
     * @param  object $project The Project Object
     * @return bool             If the setup was successful or not
     */
    public function setup_project( $project ) {

        if( ! is_object( $project ) )
            return false;

        if( ! is_a( $project, 'WP_Post' ) )
            return false;

        if( 'project' !== $project->post_type )
            return false;

        // sets the value of each key
        foreach ( $project as $key => $value ) {
            switch ( $key ) {
                default:
                    $this->$key = $value;
                    break;
            }
        }

        $this->init();

        return true;

    }

    public function init() {
        add_action( 'init', array( $this, 'hooks') );
    }

    public function hooks() {
        add_action( 'wp_insert_post', array( $this, 'update_project_meta_admin' ), 1, 3 );
    }


    /**
     * Get a meta value
     * @since 1.0.0
     * @param string $meta the meta field (without prefix)
     * @return mixed
     */
    public function get_meta( $meta ) {
        $result = get_post_meta( $this->ID, $this->meta_prefix . $meta, true );
        if( ! $result )
            $result = null;
        return $result;
    }

    /**
     * Get the clients name
     * @since 1.0.0
     * @return string|null
     */
    public function get_client_name() {
        if( ! $this->get_meta( 'client' ) )
            return;
        $client = get_post( (int) $this->get_meta( 'client' ) );
        if( $client->ID === $this->ID )
            return;
        return $client->post_title;
    }

    /**
     * Get an item (milestone, task or bug) by it's id
     * @since 1.0.0
     * @param string $id the id of the milestone
     * @return array|null
     */
    public function get_item_by_id( $item_id, $type ) {
        if( ! $item_id )
            return;
        $data = $this->get_meta( $type );
        if( ! $data )
            return;
        foreach ($data as $key => $item) {
            if( $item_id == $item['id'] )
                return $item;
        }
    }

    public function get_item_colors( $item_id, $type, $field ) {

        if( ! $item_id || ! $type || ! $field )
            return;

        $data = $this->get_meta( $type );
        if( ! $data )
            return;

        $option_name = $field == 'status' ? $field . 'es' : $field;
        $option = get_option( "upstream_{$type}" );
        $colors = wp_list_pluck( $option[$option_name], 'color', 'name' );

        foreach ($data as $key => $item) {
            if( $item_id == $item['id'] ) {
                if (isset($item[$field])) {
                    $field_name = $item[$field];
                    if( isset( $field_name ) && ! empty( $field_name ) ) {
                        return $colors[$field_name];
                    }
                }
            }
        }

        return;
    }

    /**
     * Get the current statuses used in the project for a particular item type
     * @since 1.0.0
     * @param string $type the type of item (milestone, task or bug)
     * @return array|null
     */
    public function get_statuses( $type ) {

        $found = false;

        $meta = $this->get_meta( $type );

        if ( ! $meta )
            return;

        $statuses = array();
        foreach ( $meta as $key => $value ) {
            if( array_key_exists( 'status', $value ) ) {
                $statuses[] = $value['status'];
            }
        }

        return $statuses;
    }

    /**
     * Get the current count of statuses for a particular item type
     * @since 1.0.0
     * @param string $type the type of item (milestone, task or bug)
     * @return array|null
     */
    public function get_statuses_counts( $type ) {
        if( ! $this->get_statuses( $type ) )
            return;
        $counts = array_filter( $this->get_statuses( $type ) ); // remove entries with blank statuses
        $counts = array_count_values( $counts );
        return $counts;
    }

    public function get_project_status_type() {

        if( ! $this->get_meta( 'status' ) )
            return;
        $result     = null;
        $option     = get_option( 'upstream_projects' );
        $statuses   = isset( $option['statuses'] ) ? $option['statuses'] : '';

        if( ! $statuses )
            return null;

        $types = wp_list_pluck( $statuses, 'type', 'name' );

        foreach ( $types as $key => $value ) {
            if( $key == $this->get_meta( 'status' )) {
                $result = $value;
            }
        }

        return $result;

    }

    /**
     * Update a project with various missing meta values (this runs from admin only via wp_insert_post action)
     * @since 1.0.0
     * @return null
     */
    public function update_project_meta_admin( $post_id, $post, $update ) {

        // If this is an auto draft
        if ( $post->post_status == 'auto-draft' )
            return;

        // If this is a revision
        if ( wp_is_post_revision( $post_id ) )
            return;

        $this->update_project_meta();

    }


    /**
     * Loop through the meta keys and update with missing meta values
     * This runs from admin and is also called directly if updating via frontend
     * @since 1.0.0
     * @param $posted_data array the posted data from the front end
     * @return null
     */
    public function update_project_meta( $frontend = null ) {

        $meta_keys = array(
            'milestones',
            'tasks',
            'bugs',
            'files',
            'discussion',
        );

        foreach ($meta_keys as $meta_key) {
            $this->update_missing_meta( $meta_key, $frontend );
        }

        $this->update_tasks_milestones();

        $this->update_project_members();

    }

    /**
     * Update our missing meta data
     * Ran on every project update & when items are added/edited
     * @since 1.0.0
     * @param string|array $data either a meta_key or an array of POSTed data (from frontend)
     * @return array|null
     */
    public function update_missing_meta( $meta_key, $frontend = null ) {

        // ignore quick edit
        if( isset( $_POST['action'] ) && $_POST['action'] == 'inline-save' )
            return;

        // if( [_wp_http_referer] => /upstreamplugin/wp-admin/post-new.php?post_type=project)
        //[original_post_status] => auto-draft
        // if no posted_data from frontend, set it as $_POST
        if( ! $frontend ) {
            //$data = isset( $_POST ) && ! empty( $_POST ) ? $_POST[$this->meta_prefix . $meta_key] : $this->get_meta( $meta_key );
            $data = $this->get_meta( $meta_key );
        } else {
            $data = $this->get_meta( $meta_key );
        }

        $meta_key = $this->meta_prefix . $meta_key;

        // if we have data
        if( $data ) {
            foreach ($data as $i => $value) {
                // add unique id
                if( ! isset( $data[$i]['id'] ) || empty( $data[$i]['id'] ) )
                    $data[$i]['id'] = upstream_admin_set_unique_id();

                // add the user id who created this
                if( ! isset( $data[$i]['created_by'] ) || empty( $data[$i]['created_by'] ) )
                    $data[$i]['created_by'] = upstream_current_user_id();

                // add the created date
                if( ! isset( $data[$i]['created_time'] ) || empty( $data[$i]['created_time'] ) )
                    $data[$i]['created_time'] = current_time( 'timestamp', false ); // true to get GMT time

                // convert all date fields to a timestamp
                // TODO: need to make this more bulletproof
                if( $value ) :
                    foreach ( $value as $key => $v ) {
                        if ( strpos($key, 'date') !== false ) {
                            if ( isset( $v ) && ! empty( $v ) ) {
                                $data[$i][$key] = upstream_timestamp_from_date( $v );
                            }
                        }
                    }
                endif;
            }
        }

        // removes the last empty item. So that it can be hidden
        // essentially deletes id, created by and created time if they are the only keys
        if( $meta_key != '_upstream_project_milestones' ) {
            $data = $this->remove_empty_items( $data );
        }

        $updated = update_post_meta( $this->ID, $meta_key, $data );

    }

    public function remove_empty_items( $data ) {

        if( isset( $data[0]['file_id'] ) && $data[0]['file_id'] == '0' )
            unset( $data[0]['file_id'] );

        // removes all NULL, FALSE and Empty Strings but leaves 0 (zero) values
        if( isset( $data[0] ) ) {
            $first_item = is_array( $data[0] ) ? array_filter( $data[0], 'strlen' ) : $data[0];
            if( count( $first_item ) === 3 ) {
                $data = null;
            }
        }

        return $data;

    }


    /*
     *
     */
    public function update_tasks_milestones() {

        $tasks      = $this->get_meta( 'tasks' );
        $milestones = $this->get_meta( 'milestones' );

        $i      = 0;
        $totals = array();

        if( ! $milestones )
            return;

        // loop through each milestone
        foreach( $milestones as &$m ) {
                        //     ^ add reference to make changes

            $sum    = 0;
            $count  = 0;
            $open   = 0;

            if( $tasks ) {
                // loop through each task
                foreach( $tasks as $task ) {
                    // if a milestone has a task assigned to it
                    if( isset( $task['milestone'] ) && $task['milestone'] === $m['id'] ) { // if it matches
                        $sum += isset( $task['progress'] ) ? (int)$task['progress'] : 0; // add task progress to get the sum progress of all tasks
                        $count++; // count

                        // add open tasks count to the milestone
                        if( isset( $task['status'] ) && $this->is_open_tasks( $task['status'] ) )
                            $open++;

                    }
                }
            }

            // maths to work out total percentage of this milestone
            $percentage         = $count > 0 ? $sum / ( $count * 100 ) * 100 : 0;
            $m['progress']      = round( $percentage, 1 ); // add the percentage into our new progress key
            $m['task_count']    = $count; // add the number of tasks in this milestone

            if( isset( $open ) )
                $m['task_open'] = $open++; // add the number of open tasks in this milestone

            // make sure the milestone has at least 1 task assigned otherwise it doesn't count
            if( $count > 0 ) {
                $totals[$m['milestone']]['count'] = $count;
                $totals[$m['milestone']]['progress'] = $percentage;
            }

        $i++;
        }

        update_post_meta( $this->ID, '_upstream_project_milestones', $milestones );
        update_post_meta( $this->ID, '_upstream_project_tasks', $tasks );

        // maths for the total project progress
        // do it down here out of the way
        $project_progress = 0;
        if( $totals ) {
            foreach ( $totals as $milestone ) {
                $project_progress += $milestone['progress'] / ( count( $totals ) * 100 ) * 100;
            }
        }
        update_post_meta( $this->ID, '_upstream_project_progress', round( $project_progress, 1 ) );


    }


    /*
     * Create/update list of registered project users for easy retrieval later and easy permission checking
     * Includes WP and client users
     */
    public function update_project_members() {

        $owner          = $this->get_meta( 'owner' );
        $milestones     = $this->get_meta( 'milestones' );
        $tasks          = $this->get_meta( 'tasks' );
        $bugs           = $this->get_meta( 'bugs' );
        $files          = $this->get_meta( 'files' );

        $users = array(); // start with fresh array

        if( $owner )
            $users[] = $owner;

        $current_user = get_current_user_id();

        if( $this->post_author == $current_user )
            $users[] = $current_user;


        if( $tasks ) :
            foreach( $tasks as $task ) {
                if( isset( $task['created_by'] ) )
                    $users[] = $task['created_by'];
                if( isset( $task['assigned_to'] ) )
                    $users[] = $task['assigned_to'];
            }
        endif;

        if( $milestones ) :
            foreach( $milestones as $milestone ) {
                if( isset( $milestone['created_by'] ) )
                    $users[] = $milestone['created_by'];
                if( isset( $milestone['assigned_to'] ) )
                    $users[] = $milestone['assigned_to'];
            }
        endif;

        if( $bugs ) :
            foreach( $bugs as $bug ) {
                if( isset( $bug['created_by'] ) )
                    $users[] = $bug['created_by'];
                if( isset( $bug['assigned_to'] ) )
                    $users[] = $bug['assigned_to'];
            }
        endif;

        if( $files ) :
            foreach( $files as $file ) {
                if( isset( $file['created_by'] ) )
                    $users[] = $file['created_by'];
                if( isset( $file['assigned_to'] ) )
                    $users[] = $file['assigned_to'];
            }
        endif;

        // some tidying up
        $users = array_unique( $users );
        $users = array_values( array_filter( $users ) );

        // do the updating
        update_post_meta( $this->ID, '_upstream_project_members', $users );

    }


    /**
     * Returns the count of open tasks
     *
     * @return null|int
     */
    public function is_open_tasks( $task_status ) {

        if( ! $task_status )
            return;

        $option     = get_option( 'upstream_tasks' );
        $statuses   = isset( $option['statuses'] ) ? $option['statuses'] : '';

        if( ! $statuses )
            return;

        $types = wp_list_pluck( $statuses, 'type', 'name' );

        foreach ( $types as $name => $type ) {
            if( $type == 'open' && $task_status == $name )
                return true;
        }

        return false;

    }



}
