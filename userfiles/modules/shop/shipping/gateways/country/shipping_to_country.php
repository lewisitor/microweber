<?php
api_hook('shop/shipping/gateways/country/shipping_to_country/test', 'shop/shipping/gateways/country/shipping_to_country/test2');

// print('shop/shipping/gateways/country/shipping_to_country/test'. 'shop/shipping/gateways/country/shipping_to_country/test2');
api_expose('shop/shipping/gateways/country/shipping_to_country/save');
api_expose('shop/shipping/gateways/country/shipping_to_country/set');
api_expose('shop/shipping/gateways/country/shipping_to_country/get');
api_expose('shop/shipping/gateways/country/shipping_to_country/delete');
api_expose('shop/shipping/gateways/country/shipping_to_country/reorder');

class shipping_to_country
{

    // singleton instance
    public $table;
    public $app;

    // private constructor function
    // to prevent external instantiation
    function __construct($app = false)
    {
        $this->table = MW_TABLE_PREFIX . 'cart_shipping';
        if (!is_object($this->app)) {

            if (is_object($app)) {
                $this->app = $app;
            } else {
                $this->app = mw('application');
            }

        }

    }

    function get_cost()
    {

        // $defined_cost = $this->app->user->session_get('shipping_cost');
        $shipping_country = $this->app->user->session_get('shipping_country');
        $defined_cost = 0;
        $shipping_country = $this->get('one=1&is_active=y&shipping_country=' . $shipping_country);


        if ($shipping_country == false) {
            return false;
        }

        if (isset($shipping_country['id'])) {
            if (isset($shipping_country['shipping_type']) and $shipping_country['shipping_type'] == 'fixed') {
                $defined_cost = floatval($shipping_country['shipping_cost']);

            }


        } else {
            return false;
        }

        if (isset($shipping_country['shipping_type']) and $shipping_country['shipping_type'] == 'dimensions') {
            $total_shipping_weight = 0;
            $total_shipping_volume = 0;

            $items_cart_count = $this->app->shop->cart_sum(false);
            if ($items_cart_count > 0) {
                $items_items = $this->app->shop->get_cart();
                if (!empty($items_items)) {
                    foreach ($items_items as $item) {

                        $content_data = $item['content_data'];
                        if (isset($content_data['shipping_weight']) and $content_data['shipping_weight'] != '') {
                            $weight = floatval($content_data['shipping_weight']);
                            $weight = $weight * intval($item['qty']);
                            $total_shipping_weight = $total_shipping_weight + $weight;

                        }


                        if (isset($content_data['shipping_width']) and $content_data['shipping_width'] != ''
                            and  isset($content_data['shipping_height']) and $content_data['shipping_height'] != ''
                                and  isset($content_data['shipping_depth']) and $content_data['shipping_depth'] != ''
                        ) {
                            $volume = floatval($content_data['shipping_width']) * floatval($content_data['shipping_height']) * floatval($content_data['shipping_depth']);
                            $volume = $volume * intval($item['qty']);
                            $total_shipping_volume = $total_shipping_volume + $volume;

                        }

                    }
                }
            }


            if (isset($shipping_country['shipping_price_per_weight']) and trim($shipping_country['shipping_price_per_weight']) != '') {
                $calc = floatval($shipping_country['shipping_price_per_weight']);
                $calc2 = $calc * $total_shipping_weight;
                $defined_cost = $defined_cost + $calc2;
            }
            if (isset($shipping_country['shipping_price_per_size']) and trim($shipping_country['shipping_price_per_size']) != '') {
                $calc = floatval($shipping_country['shipping_price_per_size']);
                $calc2 = $calc * $total_shipping_weight;
                $defined_cost = $defined_cost + $calc2;
            }

        }

        $items_cart_amount = $this->app->shop->cart_sum();

        if (isset($shipping_country['shipping_cost_above']) and intval($shipping_country['shipping_cost_above']) > 0) {
            $shipping_cost_above = floatval($shipping_country['shipping_cost_above']);
            if (intval($shipping_cost_above) > 0 and intval($shipping_country['shipping_cost_max']) > 0) {
                if ($items_cart_amount > $shipping_cost_above) {
                    $defined_cost = floatval($shipping_country['shipping_cost_max']);
                }
            }
        }
        if (isset($shipping_country['shipping_cost']) and intval($shipping_country['shipping_cost']) > 0) {
            $defined_cost = $defined_cost+floatval($shipping_country['shipping_cost']);
        }

          $this->app->user->session_set('shipping_cost',$defined_cost);

        return $defined_cost;
    }

    function test()
    {
        return 'ping ';
    }

    function test2()
    {
        return 'pong ';
    }

    // getInstance method
    function save($data)
    {
        if (is_admin() == false) {
            error('Must be admin');

        }

        if (isset($data['shipping_country'])) {
            if ($data['shipping_country'] == 'none') {
                error('Please choose country');
            }
            if (isset($data['id']) and intval($data['id']) > 0) {

            } else {
                $check = $this->get('shipping_country=' . $data['shipping_country']);
                if ($check != false and is_array($check[0]) and isset($check[0]['id'])) {
                    $data['id'] = $check[0]['id'];
                }
            }
        }


        $data = mw('db')->save($this->table, $data);
        return ($data);
    }

    function get($params = false)
    {

        $params = parse_params($params);

        $params['table'] = $this->table;

        if (!isset($params['order_by'])) {
            $params['order_by'] = 'position ASC';
        }
        $params['limit'] = 1000;
        // d($params);
        return mw('db')->get($params);

    }

    function delete($data)
    {

        $adm = is_admin();
        if ($adm == false) {
            error('Error: not logged in as admin.' . __FILE__ . __LINE__);
        }

        if (isset($data['id'])) {
            $c_id = intval($data['id']);
            mw('db')->delete_by_id($this->table, $c_id);

            //d($c_id);
        }
        return true;
    }

    function set($params = false)
    {

        if (isset($params['country'])) {

            $active = $this->get('one=1&is_active=y&shipping_country=' . $params['country']);
            if (is_array($active)) {
                foreach ($active as $name => $val) {
                    if ($name != 'id' and $name != 'created_on' and $name != 'updated_on') {
                        $this->app->user->session_set($name, $val);
                    }
                }
            } else {


                $active = $this->get('one=1&is_active=y&shipping_country=Worldwide');
                if (is_array($active)) {
                    $active_ww = $active;
                    $active_ww['shipping_country'] = $params['country'];
                    foreach ($active_ww as $name => $val) {
                        if ($name != 'id' and $name != 'created_on' and $name != 'updated_on') {

                            $this->app->user->session_set($name, $val);
                        }
                    }


                }

            }


            return $active;
        }


    }

    function reorder($data)
    {

        $adm = is_admin();
        if ($adm == false) {
            mw_error('Error: not logged in as admin.' . __FILE__ . __LINE__);
        }

        $table = $this->table;


        foreach ($data as $value) {
            if (is_array($value)) {
                $indx = array();
                $i = 0;
                foreach ($value as $value2) {
                    $indx[$i] = $value2;
                    $i++;
                }

                db_update_position($table, $indx);
                return true;
                // d($indx);
            }
        }
    }


}
