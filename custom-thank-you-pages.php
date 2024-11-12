<?php
/*
Plugin Name: Custom Thank You Pages
Description: Set custom thank-you pages based on products or payment gateway rules with priority.
Version: 1.3
Author: Maxi
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Custom_Thank_You_Pages {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'ctp_add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'ctp_register_settings' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'ctp_custom_thank_you_redirect' ) );
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'ctp_settings_link' ) );

        // Register custom shortcodes
        add_shortcode( 'custom_thank_you_order', array( $this, 'ctp_shortcode_order' ) );
        add_shortcode( 'custom_thank_you_customer_information', array( $this, 'ctp_shortcode_customer_information' ) );
        add_shortcode( 'custom_thank_you_order_details', array( $this, 'ctp_shortcode_order_details' ) );
    }

    public function ctp_add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'Custom Thank You Pages',
            'Custom Thank You Pages',
            'manage_options',
            'custom-thank-you-pages',
            array( $this, 'ctp_settings_page' )
        );
    }

    public function ctp_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=custom-thank-you-pages' ) . '">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function ctp_register_settings() {
        register_setting( 'ctp_settings', 'ctp_rules' );
    }

    public function ctp_settings_page() {
        $rules = get_option( 'ctp_rules', array() );

        // Get active products with ID included in the label
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1
        ));

        // Get enabled payment gateways
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        ?>

        <div class="wrap">
            <h1>Custom Thank You Pages Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ctp_settings' );
                do_settings_sections( 'ctp_settings' );
                ?>

                <table class="form-table" id="ctp-rules-table">
                    <tr>
                        <th>Product</th>
                        <th>Payment Gateway</th>
                        <th>Thank You Page URL</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>

                    <?php foreach ( $rules as $index => $rule ) : ?>
                        <tr>
                            <td>
                                <select name="ctp_rules[<?php echo $index; ?>][product_id]">
                                    <option value="">Any Product</option>
                                    <?php foreach ( $products as $product ) : ?>
                                        <option value="<?php echo $product->get_id(); ?>" <?php selected( $rule['product_id'], $product->get_id() ); ?>>
                                            <?php echo esc_html( $product->get_name() . ' (#' . $product->get_id() . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="ctp_rules[<?php echo $index; ?>][payment_gateway]">
                                    <option value="">Any Gateway</option>
                                    <?php foreach ( $payment_gateways as $gateway_id => $gateway ) : ?>
                                        <option value="<?php echo esc_attr( $gateway_id ); ?>" <?php selected( $rule['payment_gateway'], $gateway_id ); ?>>
                                            <?php echo esc_html( $gateway->get_title() ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="ctp_rules[<?php echo $index; ?>][thank_you_url]" value="<?php echo esc_attr( $rule['thank_you_url'] ); ?>" /></td>
                            <td><input type="number" name="ctp_rules[<?php echo $index; ?>][priority]" value="<?php echo esc_attr( $rule['priority'] ); ?>" /></td>
                            <td><button type="button" class="button remove-row">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <p><button type="button" class="button" id="add-rule">Add Rule</button></p>
                <?php submit_button(); ?>
            </form>
        </div>

        <script type="text/javascript">
            document.getElementById('add-rule').addEventListener('click', function() {
                var table = document.getElementById('ctp-rules-table');
                var index = table.rows.length - 1;
                var row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <select name="ctp_rules[${index}][product_id]">
                            <option value="">Any Product</option>
                            <?php foreach ( $products as $product ) : ?>
                                <option value="<?php echo $product->get_id(); ?>"><?php echo esc_html( $product->get_name() . ' (#' . $product->get_id() . ')' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="ctp_rules[${index}][payment_gateway]">
                            <option value="">Any Gateway</option>
                            <?php foreach ( $payment_gateways as $gateway_id => $gateway ) : ?>
                                <option value="<?php echo esc_attr( $gateway_id ); ?>"><?php echo esc_html( $gateway->get_title() ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="ctp_rules[${index}][thank_you_url]" /></td>
                    <td><input type="number" name="ctp_rules[${index}][priority]" /></td>
                    <td><button type="button" class="button remove-row">Remove</button></td>
                `;
                table.appendChild(row);
            });

            document.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-row')) {
                    e.target.closest('tr').remove();
                }
            });
        </script>
        <?php
    }

    public function ctp_custom_thank_you_redirect( $order_id ) {
        if ( ! $order_id ) return;

        $order = wc_get_order( $order_id );
        $rules = get_option( 'ctp_rules', array() );

        usort( $rules, function( $a, $b ) {
            return $b['priority'] - $a['priority'];
        });

        foreach ( $rules as $rule ) {
            $product_id_match = empty( $rule['product_id'] ) || array_reduce( $order->get_items(), function( $carry, $item ) use ( $rule ) {
                return $carry || ( $item->get_product_id() == $rule['product_id'] );
            }, false );

            $gateway_match = empty( $rule['payment_gateway'] ) || $order->get_payment_method() == $rule['payment_gateway'];

            if ( $product_id_match && $gateway_match ) {
                wp_redirect( esc_url( $rule['thank_you_url'] ) );
                exit;
            }
        }
    }

    // Shortcode for order ID
    public function ctp_shortcode_order( $atts ) {
        $order_id = get_query_var('order-received');
        return $order_id ? 'Order ID: ' . esc_html( $order_id ) : '';
    }

    // Shortcode for customer information
    public function ctp_shortcode_customer_information( $atts ) {
        $order_id = get_query_var('order-received');
        if ( ! $order_id ) return '';

        $order = wc_get_order( $order_id );
        $customer_info = 'Name: ' . esc_html( $order->get_billing_first_name() ) . ' ' . esc_html( $order->get_billing_last_name() ) . '<br>';
        $customer_info .= 'Email: ' . esc_html( $order->get_billing_email() );

        return $customer_info;
    }

    // Shortcode for order details
    public function ctp_shortcode_order_details( $atts ) {
        $order_id = get_query_var('order-received');
        if ( ! $order_id ) return '';

        $order = wc_get_order( $order_id );
        $items = $order->get_items();
        $details = '<ul>';
        foreach ( $items as $item ) {
            $details .= '<li>' . esc_html( $item->get_name() ) . ' x ' . $item->get_quantity() . '</li>';
        }
        $details .= '</ul>';

        return $details;
    }
}

new Custom_Thank_You_Pages();
?>
