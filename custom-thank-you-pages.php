<?php
/*
Plugin Name: Custom Thank You Pages
Plugin URI: https://github.com/maxitromer/custom-thank-you-pages
Description: Set custom thank-you pages based on products or payment gateway rules with priority.
Version: 0.1.3
Author: Maxi Tromer
Author URI: https://github.com/maxitromer
Developer: Maxi Tromer
Developer URI: https://github.com/maxitromer
GitHub Plugin URI: https://github.com/maxitromer/custom-thank-you-pages
Primary Branch: main
WC requires at least: 9.0
WC tested up to: 9.4.1
Text Domain: custom-thank-you-pages
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
        add_shortcode( 'custom_thank_you_order_details', array( $this, 'ctp_shortcode_order_details' ) );
        add_shortcode('custom_thank_you_customer_first_name', array($this, 'ctp_shortcode_customer_first_name'));
        add_shortcode('custom_thank_you_customer_last_name', array($this, 'ctp_shortcode_customer_last_name'));
        add_shortcode('custom_thank_you_customer_email', array($this, 'ctp_shortcode_customer_email'));

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
        $rules = get_option('ctp_rules', array());
        $products = wc_get_products(array('limit' => -1));
        $pages = get_pages();
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        ?>
        <div class="wrap">
            <h1>Custom Thank You Pages Settings</h1>
            <div class="shortcodes-reference" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4;">
                <h3>Available Shortcodes</h3>
                <p>Use these shortcodes in your custom thank you pages:</p>
                <ul>
                    <li><code>[custom_thank_you_order]</code> - Displays the order ID</li>
                    <li><code>[custom_thank_you_customer_first_name]</code> - Shows customer's first name</li>
                    <li><code>[custom_thank_you_customer_last_name]</code> - Shows customer's last name</li>
                    <li><code>[custom_thank_you_customer_email]</code> - Shows customer's email</li>
                    <li><code>[custom_thank_you_order_details]</code> - Lists ordered products and quantities</li>
                </ul>
            </div>
            <h1>Rules</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ctp_settings'); ?>

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
                            <td>
                              <select name="ctp_rules[<?php echo $index; ?>][thank_you_url]">
                                  <option value="">Select a Page</option>
                                  <?php foreach ( $pages as $page ) : ?>
                                      <option value="<?php echo get_permalink($page->ID); ?>" <?php selected( $rule['thank_you_url'], get_permalink($page->ID) ); ?>>
                                          <?php echo esc_html( $page->post_title . ' (#' . $page->ID . ')' ); ?>
                                      </option>
                                  <?php endforeach; ?>
                              </select>
                          </td>
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
                    <td>
                        <select name="ctp_rules[${index}][thank_you_url]">
                            <option value="">Select a Page</option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo get_permalink($page->ID); ?>"><?php echo esc_html( $page->post_title . ' (#' . $page->ID . ')' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
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

    // Shortcode for order ID
    public function ctp_shortcode_order($atts) {
        $order_id = WC()->session->get('last_order_id');
        if (!$order_id && isset($_GET['order'])) {
            $order_id = absint($_GET['order']);
        }
        return esc_html($order_id);
    }

    public function ctp_shortcode_customer_first_name($atts) {
        $order_id = WC()->session->get('last_order_id');
        if (!$order_id && isset($_GET['order'])) {
            $order_id = absint($_GET['order']);
        }
        if (!$order_id) return '';
        
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) return '';
        
        return esc_html($order->get_billing_first_name());
    }

    public function ctp_shortcode_customer_last_name($atts) {
        $order_id = WC()->session->get('last_order_id');
        if (!$order_id && isset($_GET['order'])) {
            $order_id = absint($_GET['order']);
        }
        if (!$order_id) return '';
        
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) return '';
        
        return esc_html($order->get_billing_last_name());
    }

    public function ctp_shortcode_customer_email($atts) {
        $order_id = WC()->session->get('last_order_id');
        if (!$order_id && isset($_GET['order'])) {
            $order_id = absint($_GET['order']);
        }
        if (!$order_id) return '';
        
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) return '';
        
        return esc_html($order->get_billing_email());
    }

    // Shortcode for order details
    public function ctp_shortcode_order_details($atts) {
        $order_id = WC()->session->get('last_order_id');
        if (!$order_id && isset($_GET['order'])) {
            $order_id = absint($_GET['order']);
        }
        if (!$order_id) return '';
        
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) return '';
        
        $items = $order->get_items();
        $details = '<ul>';
        foreach ($items as $item) {
            $details .= '<li>' . esc_html($item->get_name()) . ' x ' . $item->get_quantity() . '</li>';
        }
        $details .= '</ul>';
        
        return $details;
    }
    // Modify your redirect function to include order info
    public function ctp_custom_thank_you_redirect( $order_id ) {
        if ( ! $order_id ) return;
    
        $order = wc_get_order( $order_id );
        $rules = get_option( 'ctp_rules', array() );
    
        // Store order ID in session
        WC()->session->set('last_order_id', $order_id);
    
        usort( $rules, function( $a, $b ) {
            return $b['priority'] - $a['priority'];
        });
    
        foreach ( $rules as $rule ) {
            $product_id_match = empty( $rule['product_id'] ) || array_reduce( $order->get_items(), function( $carry, $item ) use ( $rule ) {
                return $carry || ( $item->get_product_id() == $rule['product_id'] );
            }, false );
    
            $gateway_match = empty( $rule['payment_gateway'] ) || $order->get_payment_method() == $rule['payment_gateway'];
    
            if ( $product_id_match && $gateway_match ) {
                wp_redirect( add_query_arg('order', $order_id, esc_url($rule['thank_you_url'])) );
                exit;
            }
        }
    }
    
}

new Custom_Thank_You_Pages();
?>
