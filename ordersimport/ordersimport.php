<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
require('classes/Encoding.php');
require('vendor/autoload.php');

class Ordersimport extends Module
{
    protected $skip_lines = 3;
    public $address_alias = 'Delivery address';
    public $id_address_country = 8;
    public $id_currency = 1;
    public $id_lang = 1;
    public $id_carrier = 14;
    public $payment = 'import';
    public $module_name = 'bankwire';
    public $token = '';
    public $orders_token = '';
    public $customer_token = '';
    public $address_token = '';

    public function __construct()
    {
        $this->name = 'ordersimport';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'k0nsul';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ordersimport');
        $this->description = $this->l('Import orders from csv');

        $this->confirmUninstall = $this->l('Realy?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->token = Tools::getAdminTokenLite('AdminModules');
        $this->orders_token = Tools::getAdminTokenLite('AdminOrders');
        $this->customer_token = Tools::getAdminTokenLite('AdminCustomers');
        $this->address_token = Tools::getAdminTokenLite('AdminAddresses');
    }

    public function install()
    {
        //Configuration::updateValue('ORDERSIMPORT_LIVE_MODE', false);
        #$this->installSql();
        return parent::install() &&
            $this->registerHook('backOfficeHeader');
    }


    public function installSql () {
      $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ordersimport` (
          `id_ordersimport` int(11) NOT NULL AUTO_INCREMENT,
          PRIMARY KEY  (`id_ordersimport`)
      ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
      foreach ( $sql as $query ) {
        if ( Db::getInstance()->execute( $query ) == FALSE ) {
          return FALSE;
        }
      }

      return TRUE;
    }

    public function uninstall()
    {
        //Configuration::deleteByName('ORDERSIMPORT_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (!empty($_FILES['file']['name'])) {
            $this->postProcess();
        }
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('action_url', $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.$this->token);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output;
    }
    private function checkExist($ref = '')
    {
        $result = false;
        $q = 'SELECT * FROM ps_orders WHERE reference = "'.$ref.'_VP"';
        $order = Db::getInstance()->getRow($q);
        if(!empty($order))
        {
            $result = true;
        }
        return $result;
    }
    protected function postProcess()
    {
        $errors = [];
        $data = $this->_xlsxToArr();
        //$data = $this->_csvToArr();
        $i = 0;
        $out = '';
        $skipped = [];
        $customer_err = [];
        $address_err = [];
        $products_err = [];
        foreach($data as $k => $order_item)
        {
            $order_data = current($order_item);
            if($this->checkExist($order_data['id_order']))
            {
                $out .= "---------------------------------<br />";
                $out .= 'Skipped order: '.$order_data['id_order'].'<br />';
                $out .= "---------------------------------<br />";
                $skipped[] = $order_data['id_order'];
                continue;
            }
            $out .= "---------------------------------<br />";

            $customer = new Customer();
            $customer = $customer->getByEmail( $order_data['email'] );
            if(!$customer->id)
            {
                $customer = new Customer();
                $customer->firstname = $order_data['f_name'];
                $customer->lastname = $order_data['l_name'];
                $customer->passwd = Tools::encrypt(Tools::passwdGen());
                $customer->email = $order_data['email'];
                try
                {
                    $customer->add();
                    $out .= 'Customer <a href="/admin777/index.php?controller=AdminCustomers&id_customer='.$customer->id.'&viewcustomer&token='.$this->customer_token.'" target="_blank">'.$customer->id." </a>was created<br />";
                } catch (Exception $e)
                {
                    echo 'Customer create error skip '.$order_data['f_name'].' '.$order_data['l_name'].' '.$e->getMessage()."\n";
                    $customer_err[] = $order_data['f_name'].' '.$order_data['l_name'];
                    continue;
                }

            } else {
                $out .= 'Customer found '.$customer->email.'<br />';
            }

            //create address
            $address = new Address();
            $address->alias = $this->address_alias;
            $address->id_customer = $customer->id;
            $address->firstname = $order_data['f_name'];
            $address->lastname = $order_data['l_name'];
            $address->company = '';
            $address->address1 = $order_data['address'];
            $address->address2 = '';
            $address->postcode = $order_data['zip'];
            $address->phone_mobile = $order_data['mobile'];
            $address->phone = $order_data['phone'];
            $address->other = '';
            $address->city = $order_data['city'];
            $address->id_state = 0;
            $address->id_country = $this->id_address_country;
            try {
                $address->add();
                $out .= 'Address <a href="/admin777/index.php?controller=AdminAddresses&id_address='.$address->id.'&updateaddress&token='.$this->address_token.'" target="_blank">'.$address->id."</a> was created<br />";

            } catch (Exception $e) {
                echo 'Address create error '.$order_data['address'].' '.$e->getMessage()."\n";
                $address_err[] = $order_data['address'];
            }

            //$address->save();
            if(!$address->id)
            {
                $errors[] = 'Address create error '.$order_data['address'];
                continue;
            }

            //create cart
            $cart = new Cart();
            $cart->id_customer = $customer->id;
            $cart->id_carrier = NULL;
            $cart->id_currency = Configuration::get( 'PS_CURRENCY_DEFAULT' );
            $cart->id_address_invoice = $address->id;
            $cart->id_address_delivery = $address->id;
            $cart->add();
            if ( !$cart->id) {
                $errors[] = 'cart create error'.$order_data['ref'];
                continue;
            }
            //add products to cart
            $shipping = 0;
            $order_total = 0;
            foreach($order_item as $data_item)
            {
                $product = $this->_findProductByRefEan($data_item['ref'],$data_item['ean']);
                if (Validate::isLoadedObject($product)) {
                    if ( !$cart->updateQty( $data_item['qty'], $product->id, 0 ) ) {
                        $errors[] = 'product to cart error'.$data_item['ref'];
                        continue;
                    }
                    $shipping += $data_item['qty']*$data_item['shipping'];
                    $order_total += $data_item['qty']*$data_item['price'];
                } else {
                    $out .= 'Product '.$data_item['ref']." not found<br />";
                    $products_err[] = $data_item['ref'];
                }
            }
            if($order_total == 0)
            {
                $skipped[] = $order_data['id_order'];
                continue;
            }

            //create order
            $cart_data = $cart->getSummaryDetails();
            $order = new Order();
            $order->id_shop = 1;
            $order->id_shop_group = 1;
            $order->id_address_delivery = $address->id;
            $order->id_address_invoice = $address->id;
            $order->id_cart = $cart->id;
            $order->id_currency = $this->id_currency;
            $order->id_lang = $this->id_lang;
            $order->id_customer = $customer->id;
            $order->payment = $this->payment;
            $order->id_carrier = $this->id_carrier;
            $order->module = $this->module_name;
            $order->payment = "Mirakl";
            $order->total_profit = 0;
            $order->total_profit_percent = 0;
            $order->reference = $order_data['id_order'].'_VP';
            $order->mp_order_id = $order->reference.'|brico';
            $order->total_products = round($order_total,2);
            $order->total_products_wt = round($order_total,2);
            $order->conversion_rate = 1;
            $order->current_state = 2;
            $order->total_shipping = round($shipping,2);
            $order->total_shipping_tax_incl = round($shipping,2);
            $order->total_paid = round($order->total_products+$shipping,2);
            $order->total_paid_tax_incl = round($order->total_products+$shipping,2);
            $order->total_paid_real = round($order->total_products+$shipping,2);
            //$order->total_shipping_tax_incl = $cart->getOrderShippingCost();
            try {
                $order->save();
                $out .= 'Order <a href="/admin777/index.php?controller=AdminOrders&id_order='.$order->id.'&vieworder&token='.$this->orders_token.'" target="_blank">'.$order->id."</a> was created<br />";
                $i++;
                } catch (Exception $e) {
                    echo 'Orders create error'.$e->getMessage()."\n";
                    continue;
                }
            //order details
            foreach($order_item as $data_item)
            {
                $product = $this->_findProductByRefEan($data_item['ref'],$data_item['ean']);
                if (Validate::isLoadedObject($product)) {
                    $order_d = new OrderDetail();
                    $order_d->id_order = $order->id;
                    $order_d->product_id = $product->id;
                    $order_d->id_warehouse = 0;
                    $order_d->id_shop = 1;
                    $order_d->product_name = $product->name[$this->id_lang];
                    $order_d->product_quantity = $data_item['qty'];
                    $order_d->product_ean13 = $product->ean13;
                    $order_d->product_reference = $product->reference;
                    if(!empty($data_item['price']))
                    {
                        $order_d->total_price_tax_excl = round($data_item['price']*$data_item['qty'],2);
                        $order_d->total_price_tax_excl = round($data_item['price']*$data_item['qty'],2);
                        $order_d->unit_price_tax_excl = round($data_item['price'],2);
                        $order_d->unit_price_tax_incl = round($data_item['price'],2);
                        $order_d->product_price = round($data_item['price'],2);
                    } else {
                        $order_d->total_price_tax_excl = $product->price*$data_item['qty'];
                        $order_d->total_price_tax_incl = Product::getPriceStatic($product->id)*$data_item['qty'];
                        $order_d->unit_price_tax_excl = $product->price;
                        $order_d->unit_price_tax_incl = Product::getPriceStatic($product->id);
                        $order_d->product_price = $product->price;

                    }
                    $order_d->save();
                }
            }
            $order_h = new OrderHistory();
            $order_h->id_order = $order->id;
            $order_h->id_order_state = 2;
            $order_h->add();
        }
        $log_filename = date('Y-m-d_H:i:s').'.txt';
        file_put_contents($this->local_path.'logs/'.$log_filename, $out);
        echo '<h1>'.$i.' orders imported</h1><br />';
        echo $out;
        echo '--------------------------------<br />';
        echo 'Skipped:<br />';
        echo implode('<br />',$skipped);
        echo '<br />';

        echo '--------------------------------<br />';
        echo 'Not found products:<br />';
        echo implode('<br />',$products_err);
        echo '<br />';

        echo '--------------------------------<br />';
        echo 'Customer errors:<br />';
        echo implode('<br />',$customer_err);
        echo '<br />';

        echo '--------------------------------<br />';
        echo 'Address errors:<br />';
        echo implode('<br />',$address_err);
        $this->_saveFile();
    }

    private function _saveFile()
    {
        $filename = date('Y-m-d_H:i:s').'.xlsx';
        $path = $this->local_path.'upload/'.$filename;
        if(move_uploaded_file($_FILES['file']['tmp_name'], $path))
        {
            return true;
        } else {
            die('Upload file error');
        }
    }


    private function _xlsxToArr()
    {
        $out = [];
        if ( $xlsx = SimpleXLSX::parse($_FILES['file']['tmp_name'],false,true) ) {
            foreach($xlsx->rows() as $k => $data)
            {
                if($k >= 3)
                {
                    if(!empty($data[0]) && !empty($data[1]) && !empty($data[2]))
                    {
                        $tmp = [];
                        $tmp['ean'] = Encoding::toUTF8($data[0]);
                        $tmp['ref'] = Encoding::toUTF8($data[1]);
                        $tmp['qty'] = Encoding::toUTF8($data[2]);
                        $tmp['id_order'] = Encoding::toUTF8($data[4]);
                        $tmp['f_name'] = Encoding::toUTF8($data[5]);
                        $tmp['l_name'] = Encoding::toUTF8($data[6]);
                        $tmp['address'] = Encoding::toUTF8(str_replace('_',' ',$data[7].' '.$data[8]));
                        $tmp['zip'] = Encoding::toUTF8($data[9]);
                        $tmp['city'] = Encoding::toUTF8(str_replace('_',' ',$data[10]));
                        if(strlen(Encoding::toUTF8(strval($data[11]))) == 9)
                        {
                            $tmp['mobile'] = '0'.Encoding::toUTF8(strval($data[11]));
                        } else {
                            $tmp['mobile'] = Encoding::toUTF8(strval($data[11]));
                        }
                        if(strlen(Encoding::toUTF8(strval($data[12]))) == 9)
                        {
                            $tmp['phone'] = '0'.Encoding::toUTF8(strval($data[12]));
                        } else {
                            $tmp['phone'] = Encoding::toUTF8(strval($data[12]));
                        }
                        $tmp['price'] = Encoding::toUTF8(str_replace(',','.',$data[15]));
                        $tmp['shipping'] = Encoding::toUTF8(str_replace(',','.',$data[16]));
                        $tmp['email'] = $data[17];
                        $out[$tmp['id_order']][] = $tmp;
                    }
                }
            }
        } else {
            echo SimpleXLSX::parseError();
        }
        return $out;
    }
    //для удобства
    private function _csvToArr()
    {
        $out = [];
        $f = fopen($_FILES['file']['tmp_name'], "rt") or die('File open error');
        for ($i=0; ($data=fgetcsv($f,1000,";"))!==false; $i++) {
            if($i > ($this->skip_lines-1))
            {
                if(!empty($data[0]) && !empty($data[1]) && !empty($data[2]))
                {
                    $tmp = [];
                    $tmp['ean'] = Encoding::toUTF8($data[0]);
                    $tmp['ref'] = Encoding::toUTF8($data[1]);
                    $tmp['qty'] = Encoding::toUTF8($data[2]);
                    $tmp['id_order'] = Encoding::toUTF8($data[3]);
                    $tmp['f_name'] = Encoding::toUTF8($data[5]);
                    $tmp['l_name'] = Encoding::toUTF8($data[4]);
                    $tmp['address'] = Encoding::toUTF8(str_replace('_',' ',$data[6].' '.$data[7]));
                    $tmp['zip'] = Encoding::toUTF8($data[8]);
                    $tmp['city'] = Encoding::toUTF8(str_replace('_',' ',$data[9]));
                    $tmp['mobile'] = Encoding::toUTF8($data[10]);
                    $tmp['phone'] = Encoding::toUTF8($data[11]);
                    $tmp['price'] = Encoding::toUTF8(str_replace(',','.',$data[12]));
                    $tmp['shipping'] = Encoding::toUTF8(str_replace(',','.',$data[13]));
                    $tmp['email'] = $data[14];
                    $out[] = $tmp;    
                }
                
            }
        }
        fclose($f);
        return $out;
    }

    private function _findProductByRefEan($ref = '',$ean = '')
    {
        $q = 'SELECT id_product FROM ps_product WHERE reference = "'.$ref.'" AND ean13 = "'.$ean.'"';
        $r =  Db::getInstance()->getRow($q);
        $product = array();
        if(!empty($r))
        {
            $product = new Product($r['id_product'],true);
        }
        return $product;
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJquery();
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

}
