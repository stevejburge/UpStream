<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'UpStream_Admin_Client_Columns' ) ) :

/**
 * Admin columns
 * @version 0.1.0
 */
class UpStream_Admin_Client_Columns {

    private $label;
    private $label_plural;

    /**
     * Constructor
     * @since 0.1.0
     */
    public function __construct() {
        $this->label = upstream_client_label();
        $this->label_plural = upstream_client_label_plural();
        return $this->hooks();
    }


    public function hooks() {
        add_filter( 'manage_client_posts_columns', array( $this, 'client_columns' ) );
        add_action( 'manage_client_posts_custom_column', array( $this, 'client_data' ), 10, 2 );
    }

    /**
     * Set columns for client
     */
    public function client_columns( $defaults ) {

        $post_type  = $_GET['post_type'];

        $columns    = array();
        $taxonomies = array();

        /* Get taxonomies that should appear in the manage posts table. */
        $taxonomies = get_object_taxonomies( $post_type, 'objects');
        $taxonomies = wp_filter_object_list( $taxonomies, array( 'show_admin_column' => true ), 'and', 'name');

        /* Allow devs to filter the taxonomy columns. */
        $taxonomies = apply_filters("manage_taxonomies_for_upstream_{$post_type}_columns", $taxonomies, $post_type);
        $taxonomies = array_filter($taxonomies, 'taxonomy_exists');

        /* Loop through each taxonomy and add it as a column. */
        foreach ( $taxonomies as $taxonomy ) {
            $columns[ 'taxonomy-' . $taxonomy ] = get_taxonomy($taxonomy)->labels->name;
        }
        $defaults['title']      = $this->label;
        $defaults['logo']       = __( 'Logo', 'upstream' );
        $defaults['website']    = __( 'Website', 'upstream' );
        $defaults['phone']      = __( 'Phone', 'upstream' );
        $defaults['address']    = __( 'Address', 'upstream' );
        $defaults['users']      = __( 'Users', 'upstream' );
        return $defaults;
    }

    public function client_data($column_name, $post_id)
    {
        $client = new UpStream_Client($post_id);

        $columnValue = null;

        if ($column_name === 'logo') {
            $logoID = $client->get_meta('logo_id');
            if (!empty($logoID)) {
                $logoImgURL = wp_get_attachment_image_src($logoID);

                $columnValue = '<img height="50" src="' . $logoImgURL[0] . '" />';
            }
        } else if ($column_name === 'website') {
            $website = $client->get_meta('website');
            if (!empty($website)) {
                $columnValue = '<a href="' . esc_url($website) . '" target="_blank" rel="noopener noreferer">' . esc_html($website) . '</a>';
            }
        } else if ($column_name === 'phone') {
            $phone = $client->get_meta('phone');
            if (!empty($phone)) {
                $columnValue = $phone;
            }
        } else if ($column_name === 'address') {
            $address = $client->get_meta('address');
            if (!empty($address)) {
                $columnValue = wp_kses_post(wpautop($address));
            }
        } else if ($column_name === 'users') {
            upstream_client_render_users_column(upstream_get_client_users($post_id));
            return;
        }

        echo !empty($columnValue) ? $columnValue : '<i>none</i>';
    }

}

new UpStream_Admin_Client_Columns;

endif;


/**
 * Renders the client users list column value.
 *
 * @since   1.0.0
 *
 * @param   array   $usersList  Array of client users.
 */
function upstream_client_render_users_column($usersList)
{
    $usersList = (array)$usersList;
    $usersListCount = count($usersList);

    if ($usersListCount === 0) {
        echo '<i>' . __('none', 'upstream') . '</i>';
    } else {
        $userIndex = 0;
        foreach ($usersList as $user) {
            echo $user['name'] . '<br/>';

            if ($userIndex === 2) {
                echo sprintf('<i>' . __('+%s more %s', 'upstream') . '</i>', $usersListCount, ($usersListCount > 1 ? __('users', 'upstream') : __('user', 'upstream')));
                break;
            }
            $userIndex++;
        }
    }
}
