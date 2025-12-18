<?php
/*
Plugin Name: WPEmail Tester
Plugin URI: https://github.com/GitStudying/WPmail-tester
Description: Sends a scheduled test email to a specified address with customizable frequency.
Version: 0.0.6
Author: GitStudying
Text Domain: wpemail-tester
Author URI: 
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// 1. ACTIVATION
register_activation_hook( __FILE__, 'tester_activate' );
function tester_activate() {
    tester_reschedule_cron();
}

// 2. DEACTIVATION
register_deactivation_hook( __FILE__, 'tester_deactivate' );
function tester_deactivate() {
    wp_clear_scheduled_hook( 'tester_send_frequent_email' );
}

// 3. SELF-HEALING CHECK (NIEUW)
// Dit lost je probleem op: Als de planning mist maar wel zou moeten bestaan, maken we hem aan.
add_action( 'admin_init', 'tester_ensure_schedule_integrity' );
function tester_ensure_schedule_integrity() {
    // Als er een email is opgeslagen...
    if ( get_option( 'tester_email_address' ) ) {
        // ...maar de taak staat NIET in de agenda
        if ( ! wp_next_scheduled( 'tester_send_frequent_email' ) ) {
            // Forceer een reschedule
            tester_reschedule_cron();
        }
    }
}

// 4. HELPER: Get seconds based on frequency setting
function tester_get_seconds() {
    $freq = get_option( 'tester_frequency', 'daily' );
    
    switch ( $freq ) {
        case 'weekly':
            return 604800; // 7 dagen
        case 'monthly':
            return 2592000; // 30 dagen (gemiddeld)
        case 'tester_custom':
            $days = get_option( 'tester_custom_days', 3 );
            return $days * 86400;
        case 'daily':
        default:
            return 86400; // 1 dag
    }
}

// 5. ADD CUSTOM CRON SCHEDULES
add_filter( 'cron_schedules', 'tester_add_cron_intervals' );
function tester_add_cron_intervals( $schedules ) {
    
    $schedules['weekly'] = array(
        'interval' => 604800,
        'display'  => __( 'Once Weekly' )
    );
    
    $schedules['monthly'] = array(
        'interval' => 2592000,
        'display'  => __( 'Once Monthly' )
    );

    // Custom Days
    $custom_days = get_option( 'tester_custom_days', 3 );
    if ( $custom_days > 0 ) {
        $schedules['tester_custom'] = array(
            'interval' => $custom_days * 86400,
            'display'  => __( 'Every ' . $custom_days . ' days' )
        );
    }

    return $schedules;
}

// 6. ACTION HOOK
add_action( 'tester_send_frequent_email', 'tester_send_email' );

// 7. ADMIN MENU
function tester_add_options_page() {
    add_submenu_page(
        'tools.php',
        'Email Tester Settings',
        'Email Tester',
        'manage_options',
        'tester_options',
        'tester_render_options_page'
    );
}
add_action( 'admin_menu', 'tester_add_options_page' );

// 8. RENDER OPTIONS PAGE
function tester_render_options_page() {    
    ?>    
    <div class="wrap">    
        <h2>Email Tester Settings</h2>

        <?php 
        // --- LOGICA VOOR HANDMATIGE TEST MAIL ---
        if ( isset( $_POST['tester_send_test_email'] ) ) {    
            if ( check_admin_referer( 'tester_send_test_email', 'tester_send_test_email_nonce' ) ) {    
                
                $target_email = get_option( 'tester_email_address' );

                if ( empty( $target_email ) ) {
                    ?>
                    <div class="notice notice-warning is-dismissible">
                        <p><strong>Warning:</strong> No email address set. Please save an address first.</p>
                    </div>
                    <?php
                } else {
                    $sent = tester_send_email( true );        
                    if ( $sent ){
                        ?>
                        <div class="notice notice-success is-dismissible">
                            <p>Manual test email sent successfully to <?php echo esc_html($target_email); ?>!</p>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="notice notice-error is-dismissible">
                            <p>Error sending test email. Check server logs.</p>
                        </div>
                        <?php
                    }
                }
            }
        }
        
        // --- CHECK SCHEDULER & DISPLAY INFO ---
        $next_run = wp_next_scheduled( 'tester_send_frequent_email' );
        $freq_setting = get_option( 'tester_frequency', 'daily' );
        
        if ( $next_run ) {
            $time_string = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run );
            $time_diff = human_time_diff( time(), $next_run );
            
            ?>
            <div class="notice notice-info inline">
                <p>
                    <strong>Status:</strong> Active<br>
                    <strong>Frequency:</strong> <?php echo esc_html($freq_setting); ?><br>
                    <strong>Next automatic email:</strong> <?php echo $time_string; ?> (in <?php echo $time_diff; ?>)
                </p>
            </div>
            <?php
        } else {
             ?>
            <div class="notice notice-warning inline">
                <p>No active schedule found. The system will attempt to self-heal on next refresh.</p>
            </div>
            <?php
        }
        ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'tester_options' ); ?>    
            <?php do_settings_sections( 'tester_options' ); ?>    
            
            <table class="form-table">    
                <tr>    
                    <th scope="row"><label for="tester_email_address">Email Address</label></th>    
                    <td>
                        <input type="email" id="tester_email_address" name="tester_email_address" value="<?php echo esc_attr( get_option( 'tester_email_address' ) ); ?>" class="regular-text" placeholder="e.g. info@example.com" required />
                    </td>    
                </tr>

                <?php $current_freq = get_option( 'tester_frequency', 'daily' ); ?>
                <tr>
                    <th scope="row"><label for="tester_frequency">Frequency</label></th>
                    <td>
                        <select name="tester_frequency" id="tester_frequency">
                            <option value="daily" <?php selected( $current_freq, 'daily' ); ?>>Daily (24 hours)</option>
                            <option value="weekly" <?php selected( $current_freq, 'weekly' ); ?>>Weekly (7 days)</option>
                            <option value="monthly" <?php selected( $current_freq, 'monthly' ); ?>>Monthly (30 days)</option>
                            <option value="tester_custom" <?php selected( $current_freq, 'tester_custom' ); ?>>Custom Days...</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tester_custom_days">Custom Interval (Days)</label></th>
                    <td>
                        <input type="number" min="1" step="1" id="tester_custom_days" name="tester_custom_days" value="<?php echo esc_attr( get_option( 'tester_custom_days', 3 ) ); ?>" class="small-text" />
                        <p class="description">Only used if "Custom Days" is selected above.</p>
                    </td>
                </tr>
            </table>    
            <?php submit_button( 'Save Settings & Update Schedule' ); ?>    
        </form>

        <hr />    

        <form method="post">
            <h3>Test Email</h3>    
            <p>Send a manual test immediately (does not affect the schedule):</p>    
            <?php wp_nonce_field( 'tester_send_test_email', 'tester_send_test_email_nonce' ); ?>    
            <?php submit_button( 'Send Test Email Now', 'secondary', 'tester_send_test_email', false ); ?>    
        </form>    
    </div>    
    <?php    
}

// 9. REGISTER SETTINGS
add_action( 'admin_init', 'tester_register_settings' );    
function tester_register_settings() {    
    register_setting( 'tester_options', 'tester_email_address', 'sanitize_email' );
    
    register_setting( 'tester_options', 'tester_frequency', array(
        'sanitize_callback' => 'sanitize_text_field',
    ));

    register_setting( 'tester_options', 'tester_custom_days', array(
        'sanitize_callback' => 'absint'
    ));
}   

// 10. HOOK INTO OPTION UPDATE TO RESCHEDULE
add_action( 'update_option_tester_frequency', 'tester_reschedule_cron', 10, 0 );
add_action( 'update_option_tester_custom_days', 'tester_reschedule_cron', 10, 0 );
add_action( 'update_option_tester_email_address', 'tester_reschedule_cron', 10, 0 );

function tester_reschedule_cron() {
    // 1. Verwijder altijd eerst de oude taak
    wp_clear_scheduled_hook( 'tester_send_frequent_email' );

    // 2. Als er geen email is, stoppen we
    $email = get_option( 'tester_email_address' );
    if ( empty( $email ) ) return;

    // 3. Haal frequentie instelling op
    $freq = get_option( 'tester_frequency', 'daily' );
    
    // 4. Haal exacte seconden op
    $seconds = tester_get_seconds();

    // 5. Plan de taak
    // We plannen de EERSTE run pas over X tijd (dus niet direct, om spam bij opslaan te voorkomen)
    wp_schedule_event( time() + $seconds, $freq, 'tester_send_frequent_email' );
}

// 11. SEND EMAIL FUNCTION
function tester_send_email( $interactive = false ) {
  
    $to = get_option( 'tester_email_address' );
    if ( empty( $to ) ) return false;
    
    $freq = get_option( 'tester_frequency', 'daily' );
    
    if( $interactive ){
        $subject = 'Test message from '. get_bloginfo( 'name' ) . ' (manual)';
        $message = 'This is a manually-initiated test email.';
    } else {
        $subject = 'Scheduled test message from '. get_bloginfo( 'name' ) . ' (' . $freq . ')';
        $message = 'This is a scheduled email test. Current frequency setting: ' . $freq;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Email Tester] Sending scheduled email to ' . $to );
        }
    }
  
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    return wp_mail( $to, $subject, $message, $headers );
}