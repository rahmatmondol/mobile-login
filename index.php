<?php
/**
 * Plugin Name: WooCommerce Mobile OTP Login Only
 * Description: WooCommerce login and registration forms with OTP-based login using mobile numbers only.
 * Version: 1.4
 * Author: Rahmat Mondol
 * Author URI: https://rahmatmondol.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WooCommerce_Mobile_OTP_Login_Only {

    // Store OTP in the session temporarily
    private $otp;

    public function __construct() {
        // Remove default WooCommerce login and registration fields
        add_filter( 'woocommerce_enable_myaccount_registration', '__return_false' );
        add_action( 'woocommerce_before_customer_login_form', [ $this, 'replace_login_form' ] );

        // Enqueue inline scripts for OTP functionality
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_inline_scripts' ] );

        // Handle AJAX requests
        add_action( 'wp_ajax_send_otp', [ $this, 'send_otp' ] );
        add_action( 'wp_ajax_nopriv_send_otp', [ $this, 'send_otp' ] );
        add_action( 'wp_ajax_verify_otp', [ $this, 'verify_otp' ] );
        add_action( 'wp_ajax_nopriv_verify_otp', [ $this, 'verify_otp' ] );
    }

    // Display the custom login form with mobile number field
    public function replace_login_form() {
        ?>
        <div id="otp-login-container">
            <div class="lh1">Login / Register</div>
            <div id="send-otp-container">
                <p>
                    <label for="mobile_number">Phone number</label>
                    <img class="flag-img" src="<?php echo plugins_url( 'ar.webp', __FILE__ ); ?>" alt="">
                    <input type="text" name="mobile_number" id="mobile_number" placeholder="Enter your mobile number" required>
                </p>
                <button type="button" id="send_otp">submit</button>
            </div>
            <div id="otp-message" ></div>
            <div id="otp-verification" style="display:none;">
                <p>
                    <label for="otp_code">OTP Code</label>
                    <input type="text" name="otp_code" id="otp_code" placeholder="Enter the OTP code">
                </p>
                <button type="button" id="verify_otp">submit</button>
            </div>
        </div>
        <style>
            .woocommerce-form.woocommerce-form-login.login {
                display: none;
            }
            .woocommerce h2 {
                display: none;
            }
            
            #otp-login-container .flag-img {
            width: 50px;
            position: absolute;
            left: 4px;
            top: 93px;
            }
            #otp-login-container {
            position: relative;
            }
            .woocommerce {
            width: 350px !important;
            padding: 50px 25px;
            margin: 0 auto;
            box-shadow: 0 0 10px 5px #ddd;
            border-radius: 20px;
            text-align: center;
            }
            #otp-login-container .lh1 {
            font-size: 26px;
            padding-bottom: 22px;
            }
            #otp-login-container label {
            float: left;
            }
            #otp-login-container #mobile_number {
            padding-left: 56px;
            margin-top: 10px;
            }
            #send_otp,#verify_otp {
            background: #134348;
            color: #fff;
            border: none;
            border-radius: 10px;
            }
        </style>
<?php
    }

    // Enqueue the required JavaScript
    public function enqueue_inline_scripts() {
        if ( is_account_page() ) {
            ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#send_otp').on('click', function () {
                const mobile = $('#mobile_number').val();
                const nonce = '<?php echo wp_create_nonce( 'otp_nonce' ); ?>';

                if (!mobile) {
                    $('#otp-message').text('Please enter your mobile number.');
                    return;
                }

                $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    action: 'send_otp',
                    mobile: mobile,
                    nonce: nonce
                },function (response) {
                    console.log(response);
                    if (response.success) {
                        $('#otp-message').text(response.data);
                        $('#send-otp-container').hide();
                        $('#otp-verification').show();
                    } else {
                        $('#otp-message').text(response.data);
                    }
                });
            });

            $('#verify_otp').on('click', function () {
                const mobile = $('#mobile_number').val();
                const otp = $('#otp_code').val();
                const nonce = '<?php echo wp_create_nonce( 'otp_nonce' ); ?>';

                if (!otp) {
                    $('#otp-message').text('Please enter the OTP.');
                    return;
                }

                $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    action: 'verify_otp',
                    mobile: mobile,
                    otp: otp,
                    nonce: nonce
                }, function (response) {
                    if (response.success) {
                        $('#otp-message').text(response.data);
                        window.location.reload(); // Reload to reflect login
                    } else {
                        $('#otp-message').text(response.data);
                    }
                });
            });
        });
    </script>
<?php
        }
    }

    // Handle sending OTP
    public function send_otp() {
        check_ajax_referer( 'otp_nonce', 'nonce' );

        $mobile = sanitize_text_field( $_POST['mobile'] );
        if ( empty( $mobile ) ) {
            wp_send_json_error( 'Mobile number is required.' );
        }

        // Generate a 6-digit OTP
        $otp = rand( 100000, 999999 );
        $this->otp = $otp; // Store OTP in the object for verification

        // Check if the mobile number already exists
        $user = get_user_by( 'login', $mobile );
        if ( !$user ) {
            // register the user by mobile number only 
            $user_id = wp_create_user( $mobile, wp_generate_password());
            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( 'Failed to create user. Please try again.' );
            }
        }

    
        // Store OTP in a session or temporary database for verification
        set_transient( 'otp_' . $mobile, $otp, 300 ); // Expiry time of 5 minutes

        // Send OTP via SMS API (replace with actual API integration)
        $otp_message = urlencode( "Your OTP code is: {$otp}" );
        $otp_api_url = "https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx?"
            . "UserId=medex_ewbs"
            . "&Password=MED@1342!exo"
            . "&MobileNo=+968".$mobile
            . "&Lang=0"
            . "&FLashSMS=y"
            . "&Message={$otp_message}";

        // Call the API to send OTP
        $response = wp_remote_get( $otp_api_url );
        $response = json_decode( $response['body'], true );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Failed to send OTP. Please try again.' );
        }

        // Confirm OTP sent successfully
        if( $response == 1 ) {
            wp_send_json_success( 'OTP sent successfully to ' . $mobile  );
        }elseif( $response == 2 ) {
            wp_send_json_error( 'Company Not Exits. Please check the company' );
        }elseif( $response == 3 ) {
            wp_send_json_error( 'User or Password is wrong' );
        }elseif( $response == 4 ) {
            wp_send_json_error( 'Credit is Low' );
        }elseif( $response == 5 ) {
            wp_send_json_error( 'Invalid Mobile Number. Please check the mobile number' );
        }elseif( $response == 9 ) {
            wp_send_json_error( 'One or more mobile numbers are of invalid length' );
        }elseif( $response == 11 ) {
            wp_send_json_error( 'Un Known Error' );
        }else{
            wp_send_json_error( 'Failed to send OTP. Please try again.' );
        }
    }

    // Handle OTP verification and login
    public function verify_otp() {
        check_ajax_referer( 'otp_nonce', 'nonce' );

        $mobile = sanitize_text_field( $_POST['mobile'] );
        $otp = sanitize_text_field( $_POST['otp'] );

        if ( empty( $mobile ) || empty( $otp ) ) {
            wp_send_json_error( 'Mobile and OTP are required.' );
        }

        // Retrieve the OTP stored for the mobile number
        $stored_otp = get_transient( 'otp_' . $mobile );

        // Check if OTP is valid
        if ( ! $stored_otp || $stored_otp != $otp ) {
            wp_send_json_error( 'Invalid OTP.' );
        }

        // Check if user exists, if not create a new user
        $user = get_user_by( 'login', $mobile );
        if ( ! $user ) {
            // Create new user
            $user_id = wp_create_user( $mobile, wp_generate_password() );
            wp_update_user( [
                'ID' => $user_id,
                'role' => 'customer',
            ]);
            $user = get_user_by( 'id', $user_id );
        }

        // Log the user in
        wp_set_auth_cookie( $user->ID, true );

        // Remove OTP from transient storage after successful verification
        delete_transient( 'otp_' . $mobile );

        wp_send_json_success( 'User logged in successfully.' );
    }
}

// Initialize the OTP Login system
new WooCommerce_Mobile_OTP_Login_Only();