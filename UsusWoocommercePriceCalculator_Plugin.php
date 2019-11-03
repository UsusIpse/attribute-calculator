<?php


include_once('UsusWoocommercePriceCalculator_LifeCycle.php');

class UsusWoocommercePriceCalculator_Plugin extends UsusWoocommercePriceCalculator_LifeCycle {

    public function getOptionMetaData() {       
        return array(
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            'multiplyBy' => array(__('Slug of the attribute to multiply by. (typically length)', 'uwcpc')),
            'isEnabled' => array(__('Enable calculator globally', 'uwcpc'), 'true', 'false')
        );
    }
    protected function initOptions() {
				$this->addOption('multiplyBy', 'length');
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr > 1)) {
                    $this->addOption($key, $arr[1]);
                }
            }
        }

    }
    public function getPluginDisplayName() {
        return 'Usus WooCommerce Price Calculator';
    }
    protected function getMainPluginFileName() {
        return 'usus-woocommerce-price-calculator.php';
    }

    protected function installDatabaseTables() {
		
	}
		

    protected function unInstallDatabaseTables() {
		
	}

    public function upgrade() {
		
    }

    public function addActionsAndFilters() {
		
        // Add options administration page
        add_action('admin_menu', array(&$this, 'addSettingsSubMenuPage'));
   		
		// Get custom field value, calculate new item price, save it as custom cart item data
		add_filter('woocommerce_add_cart_item_data', array(&$this, 'add_custom_field_data'), 20, 3);
		
		// Set the new calculated cart item price
		add_action('woocommerce_before_calculate_totals', array(&$this, 'extra_price_add_custom_price'), 20, 1);		
		add_filter('woocommerce_cart_item_price', array(&$this, 'display_cart_items_custom_price_details'), 20, 3);
		
		// load calculator Js script if we are on the product page	
		add_action('wp_enqueue_scripts', array(&$this, 'calculate_length_change_script'));
		
		

    }	
	public function calculate_length_change_script(){
		global $post_type;
		wp_register_script('frontcalc', plugin_dir_url(__FILE__) . '/js/calculate.js', array('jquery'), '', true );
		
		if($this->getOption('isEnabled') == 'true' && $post_type == 'product'){
			global $woocommerce;
			$multiplyBy = $this->getOption('multiplyBy');
			$variError = __('There is no variation named: ', 'uwcpc') . $this->getOption('multiplyBy');
			$theCurrency = get_woocommerce_currency_symbol();
			wp_localize_script('frontcalc', 'theVal', array('multiplyBy' =>$multiplyBy, 'theError'=>$variError, 'theCurrency'=>$theCurrency));
			wp_enqueue_script('frontcalc');
		}
		
	}

	function display_custom_item_data($cart_item_data, $cart_item) {
	
		return $cart_item_data;
	}
	function display_cart_items_custom_price_details($product_price, $cart_item, $cart_item_key) {
		
		$theMulti = 'attribute_pa_';
		(""!=($this->getOption('multiplyBy'))?$theMulti.=$this->getOption('multiplyBy'):$theMulti.='length');
		
		$daLen = $cart_item['variation'][$theMulti];
		if (isset($cart_item['custom_data']['base_price']) && isset($cart_item['custom_data']['new_price']) ) {
				$product = $cart_item['data'];
				$base_price = $cart_item['custom_data']['base_price'];
				$product_price = wc_price(wc_get_price_to_display($product, array('price' => $base_price))).
				'<br>';

				if (isset($cart_item['variation'][$theMulti])) {						
					$product_price.= 'x ' . $daLen . ' = ' . wc_price($cart_item['line_subtotal']);
				}
		}
		return $product_price;
	}
	function extra_price_add_custom_price($cart) {
		if (is_admin() && !defined('DOING_AJAX'))
				return;

		foreach($cart-> get_cart() as $cart_item) {
				if (isset($cart_item['custom_data']['new_price']))
						$cart_item['data']->set_price((float) $cart_item['custom_data']['new_price']);
		}
	}
	function add_custom_field_data($cart_item_data, $product_id, $variation_id) {
		$theMulti = 'attribute_pa_';
		(""!=($this->getOption('multiplyBy'))?$theMulti.=$this->getOption('multiplyBy'):$theMulti.='length');
		
		if (isset($_POST[$theMulti]) ) {
		
				$cPrice = substr($_POST[$theMulti], 0, strpos($_POST[$theMulti], '-'));
				
				$_product_id = $variation_id > 0 ? $variation_id : $product_id;

				$product = wc_get_product($_product_id); // The WC_Product Object
				$base_price = (float) $product->get_regular_price(); // Get the product regular price

				$cart_item_data['custom_data']['base_price'] = $base_price;
				$cart_item_data['custom_data']['new_price'] = $base_price * $cPrice;
		}

		// Make each cart item unique
		if (isset($cart_item['variation'][$theMulti]) ) {
				$cart_item_data['custom_data']['unique_key'] = md5(microtime().rand());
		}

		return $cart_item_data;
	}
}