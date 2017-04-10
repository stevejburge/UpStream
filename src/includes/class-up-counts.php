<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;



class Upstream_Counts {

    // private $columns = array();
    public $projects = null;
    public $user = null;


    /** Class constructor */
    public function __construct( $id ) {
        $this->projects = $this->get_projects( $id );
        $this->user = upstream_user_data();
    }

    /**
     * Retrieve all tasks from projects.
     *
     * @return array
     */
    public function get_projects( $id ) {
        $args = array( 
            'post_type'         => 'project', 
            'post_status'       => 'publish',
            'posts_per_page'    => -1,
            'include'           => $id,
        ); 
        $projects = get_posts( $args );
        if ( $projects )
            return $projects;
    }

    /**
     * Retrieve all items from projects.
     *
     * @return array
     */
    public function get_items( $type ) {
        if( ! $this->projects )
            return;
        $items = array();
        foreach ( $this->projects as $i => $project ) {
            $meta = get_post_meta( $project->ID, '_upstream_project_' . $type, true );
            if( $meta && is_array( $meta ) ) {
                foreach ($meta as $key => $value) {
                    array_push($items, $value);
                }
            }
            
        };    
        return $items;
    }

    /**
     * Get the count of items.
     *
     */
    public function total( $type ) {
        return count( $this->get_items( $type ) );
    }

    /**
     * Returns the total count of open items
     *
     * @return null|int
     */
    public function total_open( $type ) {
        $items = $this->get_items( $type );
        if( ! $items )
            return '0';

        $option     = get_option( 'upstream_' . $type );
        $statuses   = isset( $option['statuses'] ) ? $option['statuses'] : '';
        
        if( ! $statuses ) {
            if( $type == 'milestones' ) {
                return $this->total( $type );
            } else {
                return null;
            }
        }

        $types = wp_list_pluck( $statuses, 'type', 'name' );

        $count = 0;
        foreach ($items as $key => $item) {
            if( !isset( $item['status'] ) )
                continue;
            $item_status = $item['status'];
            if( isset( $types[$item_status] ) && $types[$item_status] == 'open' )
                $count += 1;
        }

        return $count;
    }

    /**
     * Get the count of items assigned to current user.
     *
     */
    public function assigned_to( $type ) {
        $items = $this->get_items( $type );
        if( ! $items )
            return '0';

        $assigned = array();
        foreach ($items as $key => $value) {
            $assigned[] = isset( $value['assigned_to'] ) ? $value['assigned_to'] : null;
        }
        if( $assigned ) {
            foreach ($assigned as $key => $value) {
                $array[$key] = ! empty( $value ) ? $value : 0;
            }
        }
        $assigned = array_count_values( $array );
        $mine = array_filter( $assigned );

        if( ! isset( $mine[ $this->user['id'] ] ) )
            return '0';

        return $mine[ $this->user['id'] ];
    }


    /**
     * Returns the count of OPEN tasks for the current user
     *
     * @return array
     */
    public function assigned_to_open( $type ) {
        $items = $this->get_items( $type );
        if( ! $items )
            return '0';

        $option     = get_option( 'upstream_' . $type );
        $statuses   = isset( $option['statuses'] ) ? $option['statuses'] : '';
        
        if( ! $statuses ) {
            if( $type == 'milestones' ) {
                return $this->total( $type );
            } else {
                return null;
            }
        }

        $types = wp_list_pluck( $statuses, 'type', 'name' );

        $count = 0;
        foreach ($items as $key => $item) {
            if( ! isset( $item['assigned_to'] ) )
                continue;
            if( $item['assigned_to'] != $this->user['id'] )
                continue;
            $item_status = isset( $item['status'] ) ? $item['status'] : '';
            if( isset( $types[$item_status] ) && $types[$item_status] == 'open' )
                $count += 1;
        }

        return $count;
    }


}


// new Upstream_Counts();