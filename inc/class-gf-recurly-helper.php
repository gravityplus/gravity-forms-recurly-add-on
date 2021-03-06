<?php

// No direct accesss
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'GF_Recurly_Helper' ) ) {
    /**
     * Recurly Helper Class
     *
     * @since      1.0.0
     */
    class GF_Recurly_Helper {

        function __construct() {

            // Get current options
            $sub_domain = self::get_gf_option('recurly_subdomain');
            $api_key = self::get_gf_option('recurly_private_key');

            // Configure Reculy API
            Recurly_Client::$subdomain = $sub_domain;
            Recurly_Client::$apiKey = $api_key;

            // Instantiate Recurly Client
            new Recurly_Client();
        }

        /**
         * Initializes Recurly API
         *
         * @since  1.0.0
         * @access public
         *
         * @return void
         */
        public function recurly_client() {

            // Get current options
            $sub_domain = self::get_gf_option('recurly_subdomain');
            $api_key = self::get_gf_option('recurly_private_key');

            // Configure Reculy API
            Recurly_Client::$subdomain = $sub_domain;
            Recurly_Client::$apiKey = $api_key;

            $recurly_client = new Recurly_Client();
            return $recurly_client;

        }

        /**
         * Send response status with error
         *
         * @since  1.0.0
         * @access public
         *
         * @return array
         */
        public function send_response( $is_success = true, $message = '', $meta = array() ) {
            $response = array(
                'is_success'    => $is_success,
                'message'       => $message,
                'meta'          => $meta
            );
            return $response;
        }

        /**
         * XML decode
         *
         * @since  1.0.0
         * @access public
         *
         * @return array
         */
        public function xml_decode( $xml ) {

            // TODO: Improve
            return json_decode( json_encode( (array) simplexml_load_string( $xml ) ), 1);
        }

        /**
         * Convert cents to dollars
         *
         * @since  1.0.0
         * @access public
         *
         * @return string
         */
        public function cents_to_dollars( $cents, $prefix = false ) {
            if ( ! $prefix ) {
                return number_format( ($cents / 100) , 2, '.', ',');
            } else {
                setlocale(LC_MONETARY, 'en_US');
                return money_format('%.2n', ($cents / 100));
            }
        }

        /**
         * Add hyphens to credit card number for Recurly
         *
         * @since  1.0.0
         * @access public
         *
         * @return string
         */
        public function format_cc_number($cc) {

            // Get the credit card length
            $cc_length = strlen($cc);
            $newCreditCard = substr($cc, -4);
            for ($i = $cc_length - 5; $i >= 0; $i--) {

                // Add hyphens
                if ((($i + 1) - $cc_length) % 4 == 0) {
                    $newCreditCard = '-' . $newCreditCard;
                }
                $newCreditCard = $cc[$i] . $newCreditCard;
            }

            return $newCreditCard;
        }

        /**
         * Retrieves Gravity Form Recurly options
         *
         * @since  1.0.0
         * @access public
         *
         * @param string $option_name   Name of the option
         *
         * @return void
         */
        public static function get_gf_option( $option_name ) {

            // Get all options
            $options = get_option( 'gravityformsaddon_gfrecurly_settings' );
            return isset( $options[ $option_name ] ) ? $options[ $option_name ] : null;
        }

        /**
         * Validate Recurly Info
         *
         * @since  1.0.0
         * @access public
         *
         * @param string $private_key   Recurly private Key
         * @param string $sub_domain    Recurly Subdomain
         * @param bool $cache           Validates recurly info again if false
         *
         * @return bool
         */
        public function validate_info( $private_key = null, $sub_domain = null, $cache = true ) {

            $recurly_key_info = get_transient( 'gf_recurly_key_info' );

            if ( ! $cache ) {
                $recurly_key_info = null;

                // Flush cache
                delete_transient( 'gf_recurly_key_info' );
            }

            // Check if already validated
            if ( $recurly_key_info ) {
                return true;
            }

            // Recurly_Client config
            Recurly_Client::$subdomain = $sub_domain;
            Recurly_Client::$apiKey = $private_key;

            // Instantiate recurly client
            $recurly_client = new Recurly_Client();

            // Try to get accounts
            try {
                $get = $recurly_client->request('GET', '/accounts');
                if ( $get->statusCode == '200' ) {

                    $recurly_key_info = array( 'is_valid_key' => '1' );

                    // Caching response
                    set_transient( 'gf_recurly_key_info', $recurly_key_info, 86400 ); //caching for 24 hours

                    return true;
                } else {

                    return false;
                }
            } catch (Exception $e) {
                // print_r($e);
                // die();
                // Get rid of error for now
                unset($e);

                return false;
            }
        }

        /**
         * Get Recurly plans from API
         *
         * @since  1.0.0
         * @access public
         *
         * @return array    All recurly plans
         */
        public function get_plans() {
            $plans = array();
            try {
                $_plans = Recurly_PlanList::get();
                foreach ($_plans as $plan) {
                    array_push($plans, $plan);
                }
                return self::send_response(true, '', $plans );
            } catch (Recurly_ValidationError $e) {
                print "No coupons found: $e";
            }
        }
        // public function get_plans() {

        //     try {
        //         // Instantiate recurly client
        //         $recurly_client = self::recurly_client();

        //         // Get plans
        //         $plans = $recurly_client->request('GET', '/plans');

        //         // Test successful connection
        //         if ($plans->statusCode == '200') {

        //             $plans_xml = $plans->body;

        //             // Quicky dirty way to convert XML to php array
        //             // TODO: Improve
        //             $plans = json_decode( json_encode( (array) simplexml_load_string( $plans_xml ) ), 1);

        //             return $plans['plan'];

        //         }
        //     } catch (Recurly_ValidationError $e) {
        //         print "Plans not found: $e";
        //     }
        // }

        /**
         * Get Recurly Coupons
         *
         * @since  1.0.0
         * @access public
         *
         * @return array    All recurly coupons
         */
        public function get_coupons() {
            $data = array();
            try {
                $coupons = Recurly_CouponList::get();
                foreach ($coupons as $coupon) {
                    array_push($data, $coupon);
                }
                return $data;
            } catch (Recurly_ValidationError $e) {
                print "No coupons found: $e";
            }
        }

        /**
         * Get Recurly plan price
         *
         * @since  1.0.0
         * @access public
         *
         * @param string $plan_code   Recurly Plan Code
         *
         * @return int    The plan price
         */
        public function get_plan_price($plan_code) {
            if ($plan_code) {
                try {
                    // Get plan object
                    $plan = Recurly_Plan::get($plan_code);
                    $unit_amount_in_cents = $plan->unit_amount_in_cents['USD'];
                    return $unit_amount_in_cents->amount_in_cents;

                } catch (Recurly_ValidationError $e) {
                    print "Plan amount not found: $e";
                }
            }
        }

        /**
         * Get Recurly plan name
         *
         * @since  1.0.0
         * @access public
         *
         * @param string $plan_code   Recurly Plan Code
         *
         * @return string    The plan name
         */
        public function get_plan_name($plan_code) {
            if ($plan_code) {
                try {
                    // Get plan object
                    $plan = Recurly_Plan::get($plan_code);
                    $plan_name = $plan->name;
                    return $plan_name;

                } catch (Recurly_ValidationError $e) {
                    print "Plan name not found: $e";
                }
            }
        }

        /**
         * Maybe create recurly account
         *
         * Checks if a user account exists on recurly, creates one if it doesn't exist.
         *
         * @since  1.0.0
         * @access public
         *
         * @param string $email         Treating Email as Recurly Account code
         * @param array $account_data   Data provided by user
         *
         * @return void
         */
        public function maybe_create_account( $account_code, $account_info = array('name' => '', 'value' => '') ) {

            try {

                // Instantiate recurly client
                $recurly_client = self::recurly_client();

                // Get user account
                $account = $recurly_client->request('GET', "/accounts/$account_code");

                // Create account if no account
                if ( $account->statusCode != '200' ) {
                        $account = new Recurly_Account($account_code);
                        foreach( $account_info as $name => $value ) {
                            $account->$name = $value;
                        }
                        $account->create();
                        // return true;
                        return self::send_response();

                }

                // Update the account with new info
                elseif ( $account->statusCode == '200' ) {

                    // Update info
                    foreach( $account_info as $name => $value ) {
                        $account->$name = $value;
                    }

                    // return true
                    return self::send_response();
                }

            } catch (Recurly_ValidationError $e) {
                // return false and error
                return self::send_response(false, $e);
            }
        }

        /**
         * Update billing info
         *
         * @since  1.0.0
         * @access public
         *
         * @param string $option_name   Name of the option
         *
         * @return bool
         */
        public function update_billing_info( $info ) {
            try {

                $billing_info = new Recurly_BillingInfo();

                foreach ($info as $name => $value) {
                    $billing_info->$name = $value;
                }

                $billing_info->create();

                // return true
                return self::send_response();
            } catch (Recurly_ValidationError $e) {

                // The data or card are invalid
                return self::send_response(false, "Invalid data or card: " . $e->getMessage());
            } catch (Recurly_NotFoundError $e) {

                // Could not find account
                return self::send_response(false, "Not Found: " . $e->getMessage());
            }
        }

        /**
         * Create subscription
         *
         * @since  1.0.0
         * @access public
         *
         * @param string $account_code  Recurly Account code
         * @param string $plan_code     Recurly Plan code
         * @param bool $currency        Currency
         *
         * @return bool
         */
        public function create_subscription( $account_code, $plan_code, $coupon_code = '', $currency = 'USD' ) {
            try {
                $subscription = new Recurly_Subscription();
                $subscription->plan_code = $plan_code;
                $subscription->coupon_code = $coupon_code;
                $subscription->currency = $currency;
                $account = Recurly_Account::get($account_code);
                $subscription->account = $account;
                $subscription->create();

                // Success
                return self::send_response(true, '', $subscription );
            } catch (Recurly_ValidationError $e) {

                // Failed
                return self::send_response(false, $e->getMessage());
            } catch (Recurly_NotFoundError $e) {

                // Failed
                return self::send_response(false, "Account not found.\n");
            }
        }

        /**
         * Calculate Coupon Discount
         *
         * @since  1.0.0
         * @access public
         *
         * @param int       $total      The current total
         * @param string    $coupon     Recurly coupon code
         *
         * @return array      The updated price
         */
        public function apply_coupon($total, $coupon) {
            try {
                // Hold our new price
                $response = array(
                    'new_total'         => '',
                    'discount_value'    => ''
                );

                // Get coupon object
                $coupon         = Recurly_Coupon::get( $coupon );
                $discount_type  = $coupon->discount_type;

                if ($discount_type == 'percent') {
                    $discount_percent = (int) $coupon->discount_percent;

                    // Total provided minus discount %
                    $response['new_total']      = (int) $total * ((100-$discount_percent) / 100);
                    $response['discount_value'] = '-' . $discount_percent . '%';
                } elseif ($discount_type == 'dollars') {
                    $d = $coupon->discount_in_cents['USD'];
                    $discount_cents = $d->amount_in_cents;
                    $discount_dollars = $discount_cents / 100.0;
                    $response['new_total']      = $total - $discount_dollars;
                    $response['discount_value'] = '- $' . $discount_dollars;
                }
                return self::send_response(true, '', $response );
            } catch (Recurly_NotFoundError $e) {
                return self::send_response(false, "Coupon does not exist: " . $e->getMessage());
            }
        }

        /**
         * Extract plan code from $entry
         */
        public function extract_plan_code_from_entry($entry) {
            foreach($entry as $e) {
                if (strpos($e, 'gfRecurlySelectedPlan_x_') !== false) {
                    $plan_code = explode('gfRecurlySelectedPlan_x_', $e);
                    return $plan_code[1];
                }
            }
        }
    }
}
new GF_Recurly_Helper();
