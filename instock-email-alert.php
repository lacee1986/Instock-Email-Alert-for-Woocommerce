<?php
/**
* Plugin Name: Instock Email Alert for Woocommerce
* Plugin URI: https://wordpress.org/plugins/instock-email-alert-for-woocommerce/
* Description: Sends an email alert for the subscribed users when the product is in stock.
* Version: 1.1.2
* Author: Laszlo Kruchio
*/
 
 defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
 
// Include CSS - Front End
add_action( 'init','instock_email_alert_include_css');
function instock_email_alert_include_css() {
    wp_register_style('instock_email_alert_css', plugins_url('css/instock-email-alert.css',__FILE__ ));
    if ( get_option('instock_email_option_css') != 'on' ) {
        wp_enqueue_style('instock_email_alert_css');
    }
}

// Include CSS / JS - Admin
add_action( 'admin_enqueue_scripts', 'instock_email_alert_include_admin_css' );
function instock_email_alert_include_admin_css() {
    wp_register_style('instock_email_alert_admin_css', plugins_url('css/instock-email-alert-admin.css',__FILE__ ), false, '1.0');
    wp_enqueue_style( 'instock_email_alert_admin_css');
    wp_enqueue_script('jquery');
    wp_enqueue_script( 'instock_email_alert_admin_js', plugins_url('js/instock-email-alert-admin.js',__FILE__ ), false, '1.0');
}
 
// DB - Create table on first activation 
register_activation_hook( __FILE__, 'instock_email_alert_install' );

// DB - Versioning
global $instock_email_alert_db_version;
$instock_email_alert_db_version = '2.0';

function instock_email_alert_install () {
    global $wpdb;
    global $instock_email_alert_db_version;
    $table_name = $wpdb->prefix . "instock_email_alert";
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        date MEDIUMTEXT NOT NULL,
        user_email MEDIUMTEXT NOT NULL,
        product_id MEDIUMINT(9) NOT NULL,
        status TINYINT(1) DEFAULT NULL,
        UNIQUE KEY id (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    add_option( 'instock_email_alert_db_version', $instock_email_alert_db_version );
}

// DB - Update
add_action( 'plugins_loaded', 'instock_email_alert_update_db_check' );
function instock_email_alert_update_db_check() {
    global $instock_email_alert_db_version;
    if ( get_site_option( 'instock_email_alert_db_version' ) != $instock_email_alert_db_version ) {
        instock_email_alert_install();
    }
}

// Email Notifications - Send Email
add_action('woocommerce_product_set_stock_status', 'instock_email_alert_check_status', 10, 2);
function instock_email_alert_check_status($productId, $status) {
    if ($status == "instock"){	
        // Product details
        $prod_title = get_the_title($productId);
        $prod_link = get_the_permalink($productId);
        // Options - Sender
        if ( get_option('instock_email_option_sender') ) {
            $options_sender = get_option('instock_email_option_sender');
        } else {
            $options_sender = get_option('blogname');
        }
        // Options - From
        if ( get_option('instock_email_option_from') ) {
            $options_from = get_option('instock_email_option_from');
        } else {
            $options_from = get_option('admin_email');
        }
        // Options - Subject
        if ( get_option('instock_email_option_subject') ) {
            $options_subject = get_option('instock_email_option_subject');
        } else {
            $options_subject = 'Your product is on stock now!';
        }
		$options_subject = str_replace('%product_name%', $prod_title, $options_subject);
		$options_subject = str_replace('%product_link%', $prod_link, $options_subject);
        // Options - Message
        if ( get_option('instock_email_option_message') ) {
            $options_message = get_option('instock_email_option_message');
        } else {
            $options_message = 'Hello, The product %product_name% is on stock. You can purchase it here: %product_link%';
        }
        $options_message = str_replace('%product_name%', $prod_title, $options_message);
        $options_message = str_replace('%product_link%', $prod_link, $options_message);
        // If out of stock
        $users = array();
        global $wpdb;
        $table_name = $wpdb->prefix . "instock_email_alert";
        // Grab all the user emails for this product
        $emails = $wpdb->get_results("SELECT * FROM `".$table_name."` WHERE product_id = '$productId' AND status = 0");
        foreach ( $emails as $email ) {
            $user_email = $email->user_email;
            $headers = 'From: '.$options_sender.' <'.$options_from.'>' . "\r\n";
            wp_mail( $user_email, $options_subject, $options_message, $headers);
            // Set status
            $status = $wpdb->get_results("UPDATE `".$table_name."` SET status = 1 WHERE product_id = '$productId' AND status = 0 AND user_email = '$user_email'");
        }
    }
}

// Email Notifications - Save to DB
function instock_email_alert_save_email($email, $productid){
    global $wpdb;
    $table_name = $wpdb->prefix . "instock_email_alert";
    $date = date('d-m-Y h:i:s');
    $wpdb->insert( $table_name, array( 'date' => $date, 'user_email' => $email, 'product_id' => $productid, 'status' => 0), array( '%s', '%s', '%d', '%d' ) );
}

if ( isset($_POST['alert_email']) && !empty($_POST['alert_email']) ) {
    $the_email = $_POST['alert_email'];
    $id = $_POST['alert_id'];
    if ( filter_var($the_email, FILTER_VALIDATE_EMAIL) && is_numeric($id) ) {
        instock_email_alert_save_email($the_email, $id);
        add_filter( 'woocommerce_single_product_summary', 'instock_email_alert_save_sent', 80 );
    } else {
        add_filter( 'woocommerce_single_product_summary', 'instock_email_alert_save_error', 80 );
    }
}

function instock_email_alert_save_error(){
	$options_error = get_option('instock_email_option_error');
	echo '<div class="instock_message error">' . $options_error . '</div>';
}

function instock_email_alert_save_sent(){
	$options_success = get_option('instock_email_option_success');
	echo '<div class="instock_message sent">' . $options_success . '</div>';
}

// Email Notifications - Remove from DB
if ( !empty($_POST) && isset($_POST['remove_date']) && isset($_POST['remove_email']) && isset($_POST['remove_product'])) {
    $date = $_POST['remove_date'];
    $email = $_POST['remove_email'];
    $productid = $_POST['remove_product'];
    global $wpdb;
    $table_name = $wpdb->prefix . "instock_email_alert";
    $wpdb->delete ( $table_name, array('date' => $date, 'user_email' => $email, 'product_id' => $productid), array( '%s', '%s', '%d' ) );
}

// Add notification form
add_filter( 'woocommerce_single_product_summary', 'instock_email_alert_form', 70 );
function instock_email_alert_form($type = NULL){
    global $product;
    $stock = $product->get_total_stock();
    if ( !$stock > 0  && !$product->is_in_stock() ) {
        if ( get_option('instock_email_option_placeholder') ) {
            $placeholder = get_option('instock_email_option_placeholder');
        } else {
            $placeholder = 'Email address';
        }
        if ( get_option('instock_email_option_submit') ) {
            $submit_value = get_option('instock_email_option_submit');
        } else {
            $submit_value = 'Notify me when in stock';
        }
        $form = '
            <form action="" method="post" class="alert_wrapper">
                <input type="email" name="alert_email" id="alert_email" placeholder="' . $placeholder . '" />
                <input type="hidden" name="alert_id" id="alert_id" value="' . get_the_ID() . '"/>
                <input type="submit" value="' . $submit_value . '" class="" />
            </form> 
        ';
        if ($type == 'get') {
            return $form;
        } else {
            if ( get_option('instock_email_option_shortcode') != 'on' ) {
                echo $form;
            }
        }
    }
}

// Add Options Page
add_action('admin_menu', 'instock_email_alert_create_menu');
function instock_email_alert_create_menu() {
    add_options_page(__('Instock Alert','menu-instock'), __('Instock Alert','menu-instock'), 'manage_options', 'instocksettings', 'instock_email_alert_options');
}

// Options Page
function instock_email_alert_options() {
    ?>
    <div id="instock_alert_options">
        <form method="post" action="options.php">
            <?php settings_fields('instock_email_option_settings'); ?>
            <?php do_settings_sections('instock_email_option_settings'); ?>
            <h1>Instock Email Alert Settings</h1>
            <table class="form-table">
                <tr valign="top" class="title"><th colspan="2"><h2>Email settings</h2></th></tr>
                <tr valign="top">
                    <th scope="row">Email Sender:</th>
                    <td><input type="text" name="instock_email_option_sender" id="instock_email_option_sender" value="<?php if (get_option('instock_email_option_sender')) {echo get_option('instock_email_option_sender'); } else { echo get_option('blogname'); } ?>"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Email From:</th>
                    <td><input type="text" name="instock_email_option_from" id="instock_email_option_from" value="<?php if (get_option('instock_email_option_from')) { echo get_option('instock_email_option_from'); } else {  echo get_option('admin_email'); } ?>"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Email Subject:</th>
                    <td><input type="text" name="instock_email_option_subject" id="instock_email_option_subject" value="<?php if (get_option('instock_email_option_subject')) { echo get_option('instock_email_option_subject'); } else { echo 'Your product is on stock now!'; } ?>"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Email Message:</th>
                    <td><textarea name="instock_email_option_message" id="instock_email_option_message"><?php if (get_option('instock_email_option_message')) {echo get_option('instock_email_option_message'); } else { echo 'Hello, The product %product_name% is on stock. You can purchase it here: %product_link%'; } ?></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Tags to use (email only):</th>
                    <td><ul><li>Get the product title: <strong>%product_name%</strong></li><li>Show a link to the product: <strong>%product_link%</strong></li></ul></td>
                </tr>
                <tr valign="top" class="title"><th colspan="2"><h2>Form settings</h2></th></tr>
                <tr valign="top">
                    <th scope="row">Input placeholder:</th>
                    <td><input type="text" name="instock_email_option_placeholder" id="instock_email_option_placeholder" value="<?php if (get_option('instock_email_option_placeholder')) { echo get_option('instock_email_option_placeholder'); } else { echo 'Email address'; } ?>"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Submit value:</th>
                    <td><input type="text" name="instock_email_option_submit" id="instock_email_option_submit" value="<?php if (get_option('instock_email_option_submit')) { echo get_option('instock_email_option_submit');  } else { echo 'Notify me when in stock'; } ?>"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Error Message:</th>
                    <td><textarea name="instock_email_option_error" id="instock_email_option_error"><?php if (get_option('instock_email_option_error')) { echo get_option('instock_email_option_error'); } else {  echo 'Invalid email address.'; } ?></textarea></td>    					</tr>
                <tr valign="top">
                    <th scope="row">Success Message:</th>
                    <td><textarea name="instock_email_option_success" id="instock_email_option_success"><?php if (get_option('instock_email_option_success')) { echo get_option('instock_email_option_success'); } else { echo 'Thank you. We will notify you when the product is in stock.'; } ?></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Disable CSS</th>
                    <td><input type="checkbox" name="instock_email_option_css" id="instock_email_option_css" <?php if (get_option('instock_email_option_css')) { echo 'checked'; } ?> /></td>    					
                </tr>
                <tr valign="top" class="title"><th colspan="2"><h2>Misc settings</h2></th></tr>
                <tr valign="top">
                    <th scope="row">Disable Form (use shortcode instead)</th>
                    <td><input type="checkbox" name="instock_email_option_shortcode" id="instock_email_option_shortcode" <?php if (get_option('instock_email_option_shortcode')) {  echo 'checked'; } ?> /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Shortcode</th>
                    <td><ul><li>Use the shortcode <strong>[instock]</strong> to display the form. It must be on the single woocommerce template.</li></ul></td>
                </tr>
                <tr valign="top" class="title"><th colspan="2"><h2>Subscribed Emails</h2></th></tr>
                <tr valign="top">
                    <td colspan="2">
                        <div class="filters">
                            <span>Filters</span>
                            <input type="radio" name="filter" id="filter_all" class="filter" checked /><label for="filter_all">Show All</label>
                            <input type="radio" name="filter" id="filter_waiting" class="filter" /><label for="filter_waiting">Waiting</label>
                            <input type="radio" name="filter" id="filter_sent" class="filter" /><label for="filter_sent">Sent</label>
                        </div>
                        <ul id="subscribed_list">
                             <li class="header">
                                <div class="date">Date</div>
                                <div class="email">Email Address</div>
                                <div class="product">Product</div>
                                <div class="status">Status</div>
                                <div class="remove">Remove</div>
                            </li>
                            <?php
                            global $wpdb;
                            $table_name = $wpdb->prefix . "instock_email_alert";
                            $users = $wpdb->get_results("SELECT * FROM `".$table_name."`");
                            foreach ($users as $user) {
                                $prod_title = get_the_title($user->product_id);
                                $prod_link = get_the_permalink($user->product_id);
                            ?>
                                <li class="user <?php echo $user->status == 1 ? 'sent' : 'waiting'; ?>">
                                    <div class="date"><?php echo $user->date; ?></div>
                                    <div class="email"><?php echo $user->user_email; ?></div>
                                    <div class="product"><a href="<?php echo $prod_link; ?>" title="<?php echo $prod_title; ?>" target="_blank"><?php echo $prod_title; ?></a></div>
                                    <div class="status">    <?php echo $user->status == 1 ? 'Sent' : 'Waiting'; ?></div>
                                    <div class="remove">
                                        <form action="" method="POST">
                                            <input type="hidden" name="remove_date" value="<?php echo $user->date; ?>" />
                                            <input type="hidden" name="remove_email" value="<?php echo $user->user_email; ?>" />
                                            <input type="hidden" name="remove_product" value="<?php echo $user->product_id; ?>" />
                                            <input type="submit" name="remove_entry" value="remove" />
                                        </form> 
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                        <div class="expand"><span>Show More</span></div>
                    </td> 
                </tr>
                <tr valign="top">
                    <th scope="row"></th>
                    <td><?php submit_button(); ?></td>
                </tr>
            </table>
        </form>
    </div>
    <div style="clear:both;"></div>
    <a id="coffee" href='http://ko-fi.com?i=2415Z6M8BI3VE' target='_blank'><img style='border:0px;width:180px;' src='https://az743702.vo.msecnd.net/cdn/btn5.png' border='0' alt='Buy me a coffee at ko-fi.com' /></a> 
    <?php
}

// Register Settings
add_action( 'admin_init', 'update_instock_email_options' );
function update_instock_email_options() {
    register_setting( 'instock_email_option_settings', 'instock_email_option_sender' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_from' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_subject' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_message' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_placeholder' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_submit' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_error' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_success' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_css' );
    register_setting( 'instock_email_option_settings', 'instock_email_option_shortcode' );
}

// Shortcode
add_shortcode( 'instock', 'instock_email_alert_shortcode' );
function instock_email_alert_shortcode() {
    $form = instock_email_alert_form('get');
    echo $form;
}

// Add settings link on plugin page
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'instock_email_alert_settings_link' );
function instock_email_alert_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=instocksettings.php">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 

