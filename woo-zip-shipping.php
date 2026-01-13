<?php
/*
 * Plugin Name: WooCommerce Custom Zip Shipping
 * Description: Adds a custom shipping method restricted by ZIP codes.
 * Version: 1.0
 * Author: Marián Rehák
 * Dependencies: WooCommerce
 * Text domain: woo-zip-shipping
 * Domain Path: /languages
 */


if (! defined('ABSPATH')) exit;

/* 
 * Load textdomain
 */
function woo_zip_shipping_load_textdomain()
{
    load_plugin_textdomain(
        'woo-zip-shipping',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'woo_zip_shipping_load_textdomain');

/**
 * Custom Shipping Method: ZIP Restricted
 */
function woo_zip_shipping_init()
{
    if (! class_exists('WC_Shipping_Method')) return;

    class WC_CZ_Zip_Shipping extends WC_Shipping_Method
    {

        // Explicit property declaration for PHP 8+
        public $cost;
        public $allowed_zips;

        /**
         * Constructor
         */
        public function __construct($instance_id = 0)
        {
            $this->id                 = 'cz_zip_shipping'; // New ID to force fresh DB settings
            $this->instance_id        = absint($instance_id);
            $this->method_title       = __('Doprava omezená na PSČ', 'woo-zip-shipping');
            $this->method_description = __('Způsob dopravy dostupný pouze pro konkrétní PSČ.', 'woo-zip-shipping');

            // Supports specific to Zones
            $this->supports = array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            );

            $this->init();
        }

        /**
         * Initialize settings
         */
        public function init()
        {
            // 1. Load the form fields definition
            $this->init_form_fields();

            // 2. CRITICAL FIX: Map form_fields to instance_form_fields
            // This ensures the Zone "Edit" modal knows these are the fields to save.
            $this->instance_form_fields = $this->form_fields;

            // 3. Load values from the database
            $this->init_settings();

            // 4. Assign variables from settings
            $this->enabled      = $this->get_option('enabled');
            $this->title        = $this->get_option('title');
            $this->cost         = $this->get_option('cost');
            $this->allowed_zips = $this->get_option('allowed_zips');
        }

        /**
         * Define the Admin Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Povolit', 'woo-zip-shipping'),
                    'type'        => 'checkbox',
                    'label'       => __('Povolit tento způsob dopravy', 'woo-zip-shipping'),
                    'default'     => 'yes',
                ),
                'title' => array(
                    'title'       => __('Název', 'woo-zip-shipping'),
                    'type'        => 'text',
                    'description' => __('Toto určuje název, který uživatel uvidí při pokladně.', 'woo-zip-shipping'),
                    'default'     => __('Místní doprava', 'woo-zip-shipping'),
                    'desc_tip'    => true,
                ),
                'cost' => array(
                    'title'       => __('Cena', 'woo-zip-shipping'),
                    'type'        => 'text',
                    'placeholder' => '0',
                    'description' => __('Zadejte cenu pro tento způsob dopravy.', 'woo-zip-shipping'),
                    'default'     => '0',
                    'desc_tip'    => true,
                ),
                'allowed_zips' => array(
                    'title'       => __('Povolená PSČ', 'woo-zip-shipping'),
                    'type'        => 'textarea',
                    'description' => __('Zadejte PSČ (jedno na řádek). Můžete použít hvězdičku (*) jako zástupný znak. Například "1*" povolí všechna PSČ začínající jedničkou.', 'woo-zip-shipping'),
                    'default'     => '',
                    'placeholder' => "110 00\n2*\n350*",
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Calculate Shipping Logic
         */
        public function calculate_shipping($package = array())
        {

            // If the method is disabled in settings, do nothing
            if ($this->enabled !== 'yes') return;

            $destination_postcode = $package['destination']['postcode'];

            // Normalize user input (remove spaces)
            $clean_dest_zip = str_replace(' ', '', trim($destination_postcode));

            // Get configured ZIPs
            $allowed_zips_raw = $this->allowed_zips;
            $allowed_zips_array = preg_split('/[\s,]+/', $allowed_zips_raw);
            $allowed_zips_array = array_filter(array_map('trim', $allowed_zips_array));

            $match_found = false;

            if (! empty($allowed_zips_array)) {
                foreach ($allowed_zips_array as $allowed_zip) {
                    $clean_allowed_zip = str_replace(' ', '', $allowed_zip);

                    // Wildcard Check
                    if (strpos($clean_allowed_zip, '*') !== false) {
                        $prefix = str_replace('*', '', $clean_allowed_zip);
                        if (strpos($clean_dest_zip, $prefix) === 0) {
                            $match_found = true;
                            break;
                        }
                    } else {
                        // Exact Match Check
                        if ($clean_dest_zip === $clean_allowed_zip) {
                            $match_found = true;
                            break;
                        }
                    }
                }
            }

            if ($match_found) {
                $rate = array(
                    'id'       => $this->id,
                    'label'    => $this->title,
                    'cost'     => $this->cost,
                    'calc_tax' => 'per_item'
                );
                $this->add_rate($rate);
            }
        }
    }
}
add_action('woocommerce_shipping_init', 'woo_zip_shipping_init');

function add_zip_shipping($methods)
{
    $methods['cz_zip_shipping'] = 'WC_CZ_Zip_Shipping';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'add_zip_shipping');
