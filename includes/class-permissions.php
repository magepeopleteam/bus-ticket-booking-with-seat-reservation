<?php
if (!defined('ABSPATH')) {
    die;
}

if(!class_exists('WBTMPermission')) {
    class WBTMPermission
    {
        protected $post_type = '';
        protected $post_slug = '';
        protected $text_domain = '';


        public function __construct($post_type, $post_slug, $text_domain)
        {

            
            $this->post_type    = $post_type;
            $this->post_slug    = $post_slug;
            $this->text_domain  = $text_domain;

            // Call hooks
            // add_action('admin_menu', array($this, 'wbtm_permission_page'), 90);
        }

        public function wbtm_permission_page()
        {
            add_submenu_page('edit.php?post_type=' . $this->post_type, $this->lang('Permission'), $this->lang('Permission'), 'wbtm_permission_page', $this->post_slug, array($this, 'wbtm_permission_page_entry_point'));
        }

        public function lang($text): string
        {
            $text = __($text, $this->text_domain);

            return $text;
        }

        public function wbtm_permission_page_entry_point()
        {
            global $wp_roles;
            $all_roles = $wp_roles->roles;
            $role_count = count($all_roles);

            // Bus Capabilities
            // $wbtm_post_args = get_post_type_object('wbtm_bus');
            $wbtm_post_args = array(
                'extra_service_wbtm_bus',
                'sales_agent_wbtm_bus',
                'my_sell_wbtm_bus',
            );

            if(isset($_POST['wbtm_permission'])) {
                $prefix = 'wbtm_permission_';
                $prefix_length = strlen($prefix);
                foreach($all_roles as $key => $role) {
                    foreach($wbtm_post_args as $cap) {
                        $role = get_role($key);
                        if(isset($_POST[$prefix.$key][$cap])) {
                            if($_POST[$prefix.$key][$cap] == 'on') {
                                $role->add_cap($cap);
                            } else {
                                $role->remove_cap($cap);
                            }
                        } else {
                            $role->remove_cap($cap);
                        }
                    }
                }

                $all_roles = $wp_roles->roles;
                $role_count = count($all_roles);
                $_SESSION['wbtm_permission_notification'] = $this->lang('Settings saved');
            }            

            ?>

        <div class="wbtm_page_wrap">
            <div class="wbtm_page_innter">
                <div class="wbtm_page-top">
                    <div class="wbtm-page-heading">
                    <?php 
                        echo '<h1>'.$this->bus_get_option('bus_menu_label', 'label_setting_sec','Bus').' '.$this->lang('Permissions').'</h1>';
                    ?>
                    </div>
                    <?php if(isset($_SESSION['wbtm_permission_notification'])) : ?>
                        <div id="wbtm-perm-notification">
                            <?php 
                                echo $_SESSION['wbtm_permission_notification'];
                                unset($_SESSION['wbtm_permission_notification']);
                                echo '<script>setTimeout(function(){ document.getElementById("wbtm-perm-notification").remove() }, 5000)</script>';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="post_type" value="<?php echo $this->post_type ?>">
                    <input type="hidden" name="page" value="<?php echo $this->post_slug ?>">
                    <table class="wbtm-table-style-one wbtm_permission_table">
                        <thead>
                            <tr>
                                <?php
                                echo '<th></th>';
                                foreach( $all_roles as $role ) :
                                    // echo '<th>'.($role['name'] == 'Administrator' ? "" : $role['name']).'</th>';
                                    echo '<th>'.($role['name']).'</th>';
                                endforeach; 
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                echo '<tr class="wbtm-checkbox-bulk">';
                                echo '<td></td>';
                                $j = 1;
                                foreach( $all_roles as $key => $role ) :
                                    // if($role['name'] != 'Administrator') {
                                    //     echo '<td>';
                                    //     echo '<input type="checkbox" class="wbtm_bulkcheck_hit" data-col-no="'.$j.'"/>';
                                    //     echo '</td>';
                                    // }
                                    echo '<td>';
                                    echo '<input type="checkbox" class="wbtm_bulkcheck_hit" data-col-no="'.$j.'"/>';
                                    echo '</td>';
                                    $j++;
                                endforeach;
                                echo '</tr>';

                                foreach( $wbtm_post_args as $cap ) :
                                    $name = str_replace('_', ' ', $cap);
                                    echo '<tr>';
                                    echo '<td>'.ucwords($name).'</td>';
                                    foreach( $all_roles as $key => $role ) :
                                        echo '<td>';
                                        if(isset($role['capabilities'][$cap])) {
                                            echo '<input class="wbtm_perm_checkbox" type="checkbox" checked name="wbtm_permission_'.$key.'['.$cap.']" />';
                                        } else {
                                            echo '<input class="wbtm_perm_checkbox" type="checkbox" name="wbtm_permission_'.$key.'['.$cap.']"/>';
                                        }
                                        echo '</td>';
                                    endforeach;
                                    echo '</tr>';
                                endforeach;
                                ?>
                        </tbody>
                    </table>
                    <input type="submit" value="Save" name="wbtm_permission" class="wbtm-permission-btn">
                </form>
            </div>
        </div>

        <?php

        }

        public function bus_get_option($meta_key, $setting_name = '', $default = null)
        {
            $get_settings = get_option('wbtm_bus_settings');
            $get_val = isset($get_settings[$meta_key]) ? $get_settings[$meta_key] : '';
            $output = $get_val ? $get_val : $default;
            return $output;
        }

        public function __destruct()
        {
            if( session_id() )
                session_write_close();
        }
    }
    

    new WBTMPermission('wbtm_bus', 'wbtm_permission', 'bus-ticket-booking-with-seat-reservation');
}