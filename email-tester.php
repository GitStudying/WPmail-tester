<?php
/*
Plugin Name: Daily Email Tester
Plugin URI: https://github.com/nanopost/daily-email-tester.php
Description: Sends a daily test email to a specified address.
Version: 0.0.2
Author: nanoPost
Text Domain: daily-email-tester
Author URI: https://nanopo.st/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Schedule daily email on plugin activation
register_activation_hook( __FILE__, 'dailytester_activate' );
function dailytester_activate() {
  if ( ! wp_next_scheduled( 'dailytester_send_daily_email' ) ) {
    wp_schedule_event( time(), 'daily', 'dailytester_send_daily_email' );
  }
}
add_action( 'dailytester_send_daily_email', 'dailytester_send_email' );

// Remove daily email schedule on plugin deactivation
register_deactivation_hook( __FILE__, 'dailytester_deactivate' );
function dailytester_deactivate() {
  wp_clear_scheduled_hook( 'dailytester_send_daily_email' );
}

// Add plugin options page
function dailytester_add_options_page() {
  add_submenu_page(
    'tools.php', // Parent slug
    'Daily Email Tester Settings',
    'Daily Email Tester',
    'manage_options',
    'dailytester_options',
    'dailytester_render_options_page'
  );
}
add_action( 'admin_menu', 'dailytester_add_options_page' );

// Render plugin options page	
function dailytester_render_options_page() {	
    ?>	
    <div class="wrap">	
      <h2>Daily Email Tester Settings</h2>

      <?php 
      // 1. LOGICA VOOR HET VERSTUREN VAN TEST MAIL
      if ( isset( $_POST['dailytester_send_test_email'] ) ) {	
        if ( check_admin_referer( 'dailytester_send_test_email', 'dailytester_send_test_email_nonce' ) ) {	
            
            // Haal eerst het adres op om te checken of het bestaat
            $target_email = get_option( 'dailytester_email_address' );

            if ( empty( $target_email ) ) {
                // VALIDATIE: E-mail is leeg
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong>Let op:</strong> Er is geen e-mailadres ingesteld. Vul hieronder een adres in en klik op "Save Changes" voordat je een test verstuurt.</p>
                </div>
                <?php
            } else {
                // VALIDATIE: E-mail aanwezig, probeer te versturen
                $sent = dailytester_send_email( true );		
                if ( $sent ){
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p>Test email sent successfully to <?php echo esc_html($target_email); ?>!</p>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p>Error sending test email. Please check your server email settings.</p>
                    </div>
                    <?php
                }
            }
        }
      }

      // 2. CHECK VOOR CRON JOB
      if ( !wp_next_scheduled( 'dailytester_send_daily_email' ) ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>Daily job scheduler is missing. Deactivate, then reactive the Daily Email Tester plugin.</p>
            </div>
            <?php 
      }
      ?>

      <form method="post" action="options.php">
        <?php settings_fields( 'dailytester_options' ); ?>	
        <?php do_settings_sections( 'dailytester_options' ); ?>	
        
        <table class="form-table">	
          <tr>	
            <th scope="row"><label for="dailytester_email_address">Email Address</label></th>	
            <td>
                <input type="email" id="dailytester_email_address" name="dailytester_email_address" value="<?php echo esc_attr( get_option( 'dailytester_email_address' ) ); ?>" class="regular-text" placeholder="bijv. info@example.com" required />
            </td>	
          </tr>	
        </table>	
        <?php submit_button( 'Save Changes' ); ?>	
      </form>

      <hr />	

      <form method="post">
        <h3>Test Email</h3>	
        <p>Use the button below to send a test email to the specified address:</p>	
        <?php wp_nonce_field( 'dailytester_send_test_email', 'dailytester_send_test_email_nonce' ); ?>	
        <?php submit_button( 'Send Test Email Now', 'secondary', 'dailytester_send_test_email', false ); ?>	
      </form>	

    </div>	
    <?php	
}

// Register plugin settings	
add_action( 'admin_init', 'dailytester_register_settings' );	
function dailytester_register_settings() {	
  register_setting( 'dailytester_options', 'dailytester_email_address', 'sanitize_email' );	
}	

// Send email function
function dailytester_send_email( $interactive = false ) {
  
  $to = get_option( 'dailytester_email_address' );
  
  if( $interactive ){
        $subject = 'Daily test message from '. get_bloginfo( 'name' ) . ' (manual)';
        $message = 'This is a manually-initiated test email from the Daily Email Tester.';
    } else {
        $subject = 'Daily test message from '. get_bloginfo( 'name' ) . ' (automatic)';
        $message = 'This is a daily test email from the Daily Email Tester.';
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Daily Email Tester] Sending daily test email to ' . $to );
        }
    }
  
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    // Send email
    $send = wp_mail( $to, $subject, $message, $headers );

    if ( $interactive ) {
            if ( $send ) {
            // Output success message
            add_action( 'admin_notices', 'dailytester_output_success_message' );
        } else {
            // Output error message
            add_action( 'admin_notices', 'dailytester_output_error_message' );
        }
    }
    return $send;
}
