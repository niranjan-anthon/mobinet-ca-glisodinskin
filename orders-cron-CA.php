<?php

//Prevent Direct access
//defined('_JKOEXEC') or die("Restricted Access");
	
	define('__ROOT__', dirname(dirname(__FILE__)));
	require_once(__ROOT__.'/omanage/PHPMailer-master/class.phpmailer.php'); 
	die;
	$mysql_host = "";
	$mysql_user = "";
	$mysql_password = "";
	$mysql_db = "";
	
	$mysql_host = "localhost";
	$mysql_user = "glisodinskin-oma";
	$mysql_password = "Omanager123#";
	$mysql_db = "glisodinskin_wp_ca";
	
	// FTP authentication
	$ftp_host = "";
	$ftp_usr = "";
	$ftp_pw = "";

	$ftp_host = "host.ware-pak.com";
	$ftp_usr = "isocell@ware-pak.com";
	$ftp_pw = "ic46dJG0j";
	
	$email_to = "";
	$email_to = "kimberlee@glisodinskin.com";
	//$email_to = "sean.ogrady@mobinet.ca";
	$email_cc = "sean.ogrady@mobinet.ca";
	
	$email_from = "";
	$email_from = "orders@glisodinskin.com";
	
	$ok_process = false;
	
	// file system paths
	$base_path = "/home/glisodinskin/omanage";
	$payments_file = "GSN" . date ('ymd-H-i-s') . "-CA-payments.csv";
	$orders_warepak_file = "GSN" . date ('ymd-H-i-s') . "-CA-warepak-orders.csv";
	$orders_gsn_file = "GSN" . date ('ymd-H-i-s') . "-CA-gsn-orders.csv";
	$orders_setdata_file = "GSN" . date ('ymd-H-i-s') . "-CA-setdata.csv";
	
	$orders = buildOrders($mysql_host,  $mysql_user, $mysql_password, $mysql_db);
	
	if (is_array($orders) && $ok_process == true) {
		
		if (count($orders) > 1) {
			
			ftpFiles($orders,$base_path,$payments_file,$orders_warepak_file,$orders_gsn_file,$ftp_host,$ftp_usr,$ftp_pw,$mysql_host,$mysql_user,$mysql_password,$mysql_db);
					
			sendEmail($email_from, $email_to, $email_cc, $base_path, $payments_file, $orders_gsn_file, $orders_warepak_file);
					
			updateOrders($orders,$mysql_host,$mysql_user,$mysql_password,$mysql_db);
		}
	}
	
	function updateOrders($orders,$mysql_host,$mysql_user,$mysql_password,$mysql_db) {
		
		if ( count($orders) >= 1 ) {
			
			if (is_array($orders)) {
				
				foreach ( $orders as $order ) {
					
					$invoice_no = $order['invoice_no'];
					$order_id = $order['order_id'];
					$variation_id = $order['variation_id'];
					$order_item_type = $order['order_item_type'];

					$item_sku = $order['_sku'];
					
					$order_type = "";
					
					if ($order_item_type == "shipping") {
						$order_type = "shipping";
					} elseif(preg_match('/-sub/', $item_sku)) {
						$order_type = "subscription";
					} else {
						$order_type = "regular";
					}				
										
					$now_date = date('Y-m-d H:i:s');
					
					if ($order_id != "0" && $order_id != "" && $invoice_no != "") {

						$ok_process = true;

						if ($order_type == "regular") {
							
							// Connect to MySQL and DB
							$sql3 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
							$sql_query3 = "insert into gliso_orders (order_id,invoice_no,processed,processed_date,order_type) VALUES ('".$order_id."','".$invoice_no."','1','".$now_date."','".$order_type."')";
							$sql3->query($sql_query3);

						} else if ($order_type == "shipping") {
							
							// Connect to MySQL and DB
							$sql3 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
							$sql_query3 = "insert into gliso_orders (order_id,invoice_no,processed,processed_date,order_type) VALUES ('".$order_id."','".$invoice_no."','1','".$now_date."','".$order_type."')";
							$sql3->query($sql_query3);
							
						} else if ($order_type == "subscription") {
							
							// Connect to MySQL and DB
							$sql3 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
							$sql_query3 = "select meta_value from wpxa_postmeta where post_id = '" . $variation_id . "' AND meta_key = '_sku'";
							$result3  = $sql3->query($sql_query3);
							
							$subscription_sku = "";
							
							if ( $result3->num_rows > 0 ) {
								// put results into an array
								while ($results3 = mysqli_fetch_array($result3, MYSQLI_ASSOC) ) {
									$subscription_results[] = $results3;
								}

								foreach($subscription_results as $subscription_result){
									$subscription_sku =  $subscription_result['meta_value'];
								}					
							}

							$sql3->close();
							
							if ($subscription_sku != "" && $order_type == "subscription") {
								
								$split_values = explode("/", $subscription_sku);
								
								$length_values = explode("m+", $split_values[1]);
								
								$month_length = $length_values[0];
								$bonus_amount = $length_values[1];
								$new_order_qty = $month_length + $bonus_amount;
								$new_remaining_qty = $new_order_qty - 1;
								
								$sql3 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
								$sql_query3 = "select order_qty,processed_qty,remaining_qty from gliso_orders where order_id = '" . $order_id . "' and order_type = 'subscription'";	
								$result3  = $sql3->query($sql_query3);

								if ( $result3->num_rows > 0 ) {
									// put results into an array
									while ($results3 = mysqli_fetch_array($result3, MYSQLI_ASSOC) ) {
										$qty_results[] = $results3;
									}

									foreach($qty_results as $qty_result){
										$order_qty =  $qty_result['order_qty'];
										$processed_qty =  $qty_result['processed_qty'];
										$remaining_qty =  $qty_result['remaining_qty'];
									}	

									$new_processed_qty = $processed_qty + 1;
									$new_remaining_qty = $remaining_qty - 1;
									
									if ($new_processed_qty == $month_length) {
											$new_processed_qty = $new_processed_qty + $bonus_amount;
											$new_remaining_qty = 0;
									}
									
									$sql4 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
									$sql_query4 = "update gliso_orders set processed_qty = '".$new_processed_qty."', remaining_qty = '".$new_remaining_qty."' where order_id = '".$order_id."' AND invoice_no = '".$invoice_no."'";
									$sql4->query($sql_query3);
									
								} else {
									$sql4 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
									$sql_query4 = "insert into gliso_orders (order_id,invoice_no,processed,processed_date,order_qty,processed_qty,remaining_qty,order_type) VALUES ('".$order_id."','".$invoice_no."','1','".$now_date."','" . $new_order_qty . "','1','" . $new_remaining_qty . "','".$order_type."')";
									$sql4->query($sql_query3);
								}
								$sql3->close();
							}							
						}
					}
				}
			}
		}
	}
	
	function buildOrders($mysql_host,  $mysql_user, $mysql_password, $mysql_db) {
	
		global $ok_process;
		
		$orders = "";
		$order_status_processing = 2;
		$order_status_shipped = 3;
		
		// Connect to MySQL and DB
		$sql = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
		$query_sql = "";
		
		$query_sql = "select
					p.id AS order_id, p.post_date, p.post_status AS comment_status, oi.order_item_name, oi.order_item_type,
					pm1.meta_value as billing_email,
					pm2.meta_value as _billing_first_name,
					pm3.meta_value as _billing_last_name,
					pm4.meta_value as _billing_address_1,
					pm5.meta_value as _billing_address_2,
					pm6.meta_value as _billing_city,
					pm7.meta_value as _billing_state,
					pm8.meta_value as _billing_postcode,
					pm9.meta_value as _billing_country,
					pm10.meta_value as _billing_company,
					pm11.meta_value as _shipping_first_name,
					pm12.meta_value as _shipping_last_name,
					pm13.meta_value as _shipping_address_1,
					pm14.meta_value as _shipping_address_2,
					pm15.meta_value as _shipping_city,
					pm16.meta_value as _shipping_state,
					pm17.meta_value as _shipping_postcode,
					pm18.meta_value as _shipping_country,
					pm19.meta_value as _shipping_company,
					pm20.meta_value as _price,
					pm21.meta_value as _wholesale_price,
					pm22.meta_value as _order_total,
					pm23.meta_value as _order_tax,
					pm24.meta_value as _paid_date,
					pm25.meta_value as _order_shipping,
					pm26.meta_value as _order_currency,
					pm27.meta_value as _payment_method,
					pm28.meta_value as _customer_user,
					pm29.meta_value as _billing_phone,
					pm30.meta_value as _wc_moneris_card_type,
					pm31.meta_value as _wc_moneris_card_expiry_date,
					pm32.meta_value as _wc_moneris_receipt_id,
					pm33.meta_value as _wc_moneris_account_four,
					pm34.meta_value as _sku,
					pm35.meta_value as _wc_moneris_reference_num,
					pm36.meta_value as _wc_moneris_receipt_id,

					oim1.meta_value as _product_id,
					oim2.meta_value as _variation_id,
					oim3.meta_value as _line_subtotal,
					oim4.meta_value as _line_subtotal_tax,
					oim5.meta_value as _line_tax,
					oim6.meta_value as _line_total,
					oim7.meta_value as cost,
					oim8.meta_value as _qty,
					
					oi1.order_item_name as _tax_code,
					
					um1.meta_value as _user_capabilities

			from
					wpxa_woocommerce_order_items oi
					LEFT JOIN wpxa_posts p on p.ID = oi.order_id and post_type = 'shop_order'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim1 ON oi.order_item_id = oim1.order_item_id and oim1.meta_key = '_product_id'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id and oim2.meta_key = '_variation_id'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim3 ON oi.order_item_id = oim3.order_item_id and oim3.meta_key = '_line_subtotal'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim4 ON oi.order_item_id = oim4.order_item_id and oim4.meta_key = '_line_subtotal_tax'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim5 ON oi.order_item_id = oim5.order_item_id and oim5.meta_key = '_line_tax'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim6 ON oi.order_item_id = oim6.order_item_id and oim6.meta_key = '_line_total'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim7 ON oi.order_item_id = oim7.order_item_id and oim7.meta_key = 'cost'
					LEFT JOIN wpxa_woocommerce_order_itemmeta oim8 ON oi.order_item_id = oim8.order_item_id and oim8.meta_key = '_qty'

					LEFT join wpxa_postmeta pm1 on p.ID = pm1.post_id and pm1.meta_key = '_billing_email' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm2 on p.ID = pm2.post_id and pm2.meta_key = '_billing_first_name' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm3 on p.ID = pm3.post_id and pm3.meta_key = '_billing_last_name' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm4 on p.ID = pm4.post_id and pm4.meta_key = '_billing_address_1' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm5 on p.ID = pm5.post_id and pm5.meta_key = '_billing_address_2' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm6 on p.ID = pm6.post_id and pm6.meta_key = '_billing_city' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm7 on p.ID = pm7.post_id and pm7.meta_key = '_billing_state' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm8 on p.ID = pm8.post_id and pm8.meta_key = '_billing_postcode' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm9 on p.ID = pm9.post_id and pm9.meta_key = '_billing_country' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm10 on p.ID = pm10.post_id and pm10.meta_key = '_billing_company' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm11 on p.ID = pm11.post_id and pm11.meta_key = '_shipping_first_name' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm12 on p.ID = pm12.post_id and pm12.meta_key = '_shipping_last_name' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm13 on p.ID = pm13.post_id and pm13.meta_key = '_shipping_address_1' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm14 on p.ID = pm14.post_id and pm14.meta_key = '_shipping_address_2' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm15 on p.ID = pm15.post_id and pm15.meta_key = '_shipping_city' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm16 on p.ID = pm16.post_id and pm16.meta_key = '_shipping_state' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm17 on p.ID = pm17.post_id and pm17.meta_key = '_shipping_postcode' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm18 on p.ID = pm18.post_id and pm18.meta_key = '_shipping_country' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm19 on p.ID = pm19.post_id and pm19.meta_key = '_shipping_company' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm20 on p.ID = pm20.post_id and pm20.meta_key = '_price' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm21 on p.ID = pm21.post_id and pm21.meta_key = '_wholesale_price' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm22 on p.ID = pm22.post_id and pm22.meta_key = '_order_total' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm23 on p.ID = pm23.post_id and pm23.meta_key = '_order_tax' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm24 on p.ID = pm24.post_id and pm24.meta_key = '_paid_date' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm25 on p.ID = pm25.post_id and pm25.meta_key = '_order_shipping' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm26 on p.ID = pm26.post_id and pm26.meta_key = '_order_currency' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm27 on p.ID = pm27.post_id and pm27.meta_key = '_payment_method' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm28 on p.ID = pm28.post_id and pm28.meta_key = '_customer_user' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm29 on p.ID = pm29.post_id and pm29.meta_key = '_billing_phone' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm30 on p.ID = pm30.post_id and pm30.meta_key = '_wc_moneris_card_type' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm31 on p.ID = pm31.post_id and pm31.meta_key = '_wc_moneris_card_expiry_date' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm32 on p.ID = pm32.post_id and pm32.meta_key = '_wc_moneris_receipt_id' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm33 on p.ID = pm33.post_id and pm33.meta_key = '_wc_moneris_account_four' AND p.post_type = 'shop_order'

					LEFT JOIN wpxa_postmeta pm34 on pm34.post_id = oim1.meta_value and pm34.meta_key = '_sku' AND p.post_type = 'shop_order'

					LEFT join wpxa_postmeta pm35 on p.ID = pm35.post_id and pm35.meta_key = '_wc_moneris_reference_num' AND p.post_type = 'shop_order'
					LEFT join wpxa_postmeta pm36 on p.ID = pm36.post_id and pm36.meta_key = '_wc_moneris_receipt_id' AND p.post_type = 'shop_order'
					
					LEFT join wpxa_woocommerce_order_items oi1 on p.ID = oi1.order_id and oi1.order_item_type = 'tax'
					
					LEFT join wpxa_usermeta um1 on pm28.meta_value = um1.user_id and um1.meta_key = 'wpxa_capabilities'

			WHERE
					oi.order_item_type = 'line_item' or oi.order_item_type = 'shipping'
			GROUP BY oi.order_item_id
				";
		
		//print $query_sql . "\n";
		$result = $sql->query($query_sql);
		
		$row_ct = $result->num_rows;

		print "Number of Order Records Found: " . $row_ct . "\n";
		
		if ( $row_ct > 0 ) {
		
			// put results into an array
			while ($results = mysqli_fetch_array($result, MYSQLI_ASSOC) ) {
				$order_set[] = $results;
			}

			$i = 0;
			$j = 0;
			$order_id_test = 0;
			
			foreach ($order_set as $row) {
					
				$order_id = "";
				$order_id = $row['order_id'];
				$order_item_type = $row['order_item_type'];
				
				$item_sku = $row['_sku'];
				
				$order_type = "";
				
				if ($order_item_type == "shipping") {
					$order_type = "shipping";
				} elseif(preg_match('/-sub/', $item_sku)) {
					$order_type = "subscription";
				} else {
					$order_type = "regular";
				}
					
				// Connect to MySQL and DB
				$sql2 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );

				$sql_query2 = "";
				
				if ($order_type == "regular" && $order_id != "") {
					$sql_query2 = "SELECT order_id FROM gliso_orders WHERE order_id = '" . $order_id . "' AND processed = '1' AND order_type = 'regular'";	
				} else if ($order_type == "shipping" && $order_id != "") {
					$sql_query2 = "SELECT order_id FROM gliso_orders WHERE order_id = '" . $order_id . "' AND processed = '1' AND order_type = 'shipping'";
				} else if ($order_type == "subscription" && $order_id != "") {
					$sql_query2 = "SELECT order_id FROM gliso_orders WHERE order_id = '" . $order_id . "' AND processed = '1' AND order_type = 'subscription'";
				}
				
				if ($sql_query2 != "") {
					$result2 = $sql2->query($sql_query2);
					
					if ($result2) {
						$numRows = $result2->num_rows;
					}
				} else {
					$numRows = 0;
				}
				
				if ($numRows == 0 && $order_id != "") {
					
					$ok_process = true;
					
					// set date to date wirh no time 
					$post_date = $row['post_date'];
					$date_added = $row['post_date'];
					$orders[$i]['order_id'] = $row['order_id'];
					$orders[$i]['_sku'] = $row['_sku'];		
					$orders[$i]['date_added'] = $date_added;
					$orders[$i]['post_date'] = $post_date;
					$orders[$i]['order_date'] = $row['post_date'];
					$orders[$i]['date_modified'] = $row['post_date'];
					$orders[$i]['email'] = $row['billing_email'];
					$orders[$i]['firstname'] = $row['_billing_first_name'];
					$orders[$i]['lastname'] = $row['_billing_last_name'];
					$orders[$i]['payment_firstname'] = $row['_billing_first_name'];
					$orders[$i]['payment_lastname'] = $row['_billing_last_name'];
					$orders[$i]['payment_address_1'] = $row['_billing_address_1'];
					$orders[$i]['payment_address_2'] = $row['_billing_address_2'];
					$orders[$i]['payment_city'] = $row['_billing_city'];
					$orders[$i]['payment_state'] = $row['_billing_state'];
					$orders[$i]['payment_postcode'] = $row['_billing_postcode'];
					$orders[$i]['payment_country'] = $row['_billing_country'];
					$orders[$i]['payment_company'] = $row['_billing_company'];
					$orders[$i]['payment_method'] = $row['_payment_method'];
					$orders[$i]['shipping_firstname'] = $row['_shipping_first_name'];
					$orders[$i]['shipping_lastname'] = $row['_shipping_last_name'];
					$orders[$i]['shipping_company'] = $row['_shipping_company'];
					$orders[$i]['shipping_address_1'] = $row['_shipping_address_1'];
					$orders[$i]['shipping_address_2'] = $row['_shipping_address_2'];
					$orders[$i]['shipping_city'] = $row['_shipping_city'];
					$orders[$i]['shipping_postcode'] = $row['_shipping_postcode'];
					$orders[$i]['shipping_country'] = $row['_shipping_country'];				
					$orders[$i]['shipping_company'] = $row['_shipping_company'];				
					$orders[$i]['order_tax'] = $row['_order_tax'] ;	
					$orders[$i]['item_tax'] = $row['_line_tax'] ;		
					$orders[$i]['total'] = $row['_order_total'];
					$orders[$i]['currency_code'] = $row['_order_currency'];				
					$orders[$i]['shipping_fee'] = $row['_order_shipping'];
					$orders[$i]['customer_id'] = $row['_customer_user'];
					$orders[$i]['comment'] = $row['comment_status'];
					
					$user_capabilities_hold = $row['_user_capabilities'];
					if ($user_capabilities_hold != "") {
						$user_capabilities_values = explode(":", $user_capabilities_hold);
						$user_capabilities_hold = $user_capabilities_values[4];
						$user_capabilities_values = explode(";", $user_capabilities_hold);
						$user_capabilities_hold = $user_capabilities_values[0];
						$user_role = str_replace('"', "", $user_capabilities_hold);
						$orders[$i]['user_role'] = $user_role;
						$orders[$i]['user_capabilities'] = $user_role;
					} else {
						$orders[$i]['user_role'] = "";
						$orders[$i]['user_capabilities'] = "";
					}
					
					$orders[$i]['order_id'] = $order_id;
					$orders[$i]['invoice_no'] = "WCA-" . $order_id;				
					$orders[$i]['invoice_prefix'] = "WCA-";
					
					if ($order_id != "0" && $order_id != "") {
						$invoice_no = $orders[$i]['invoice_no'];
					}
				
					$orders[$i]['telephone'] = $row['_billing_phone'];
					$orders[$i]['fax'] = "";
					$orders[$i]['shipping_zone'] = $row['_shipping_state'];
					$orders[$i]['payment_zone'] = $row['_billing_state'];
					$orders[$i]['customer_group'] = "";
					$orders[$i]['sub_total'] = $row['_line_subtotal'];
					
					$items = $row['order_item_name'];
					$item_description = $items;

					$orders[$i]['order_type'] = $order_type;
					
					$item_sku = str_replace("-reg", "", $item_sku);
					$item_sku = str_replace("-sub", "", $item_sku);
					
					$orders[$i]['model'] = $item_sku;
					$orders[$i]['warepak_model'] = str_replace(":","-",$item_sku);
					$orders[$i]['description'] = $item_description;
					$orders[$i]['name'] = $item_description;			
					$orders[$i]['line_total'] = $row['_line_total'];
					$orders[$i]['quantity'] = $row['_qty'];					
					
					$tax_code_hold = $row['_tax_code'];
					if ($tax_code_hold != "") {
						$tax_code_values = explode("-", $tax_code_hold);
						$tax_code = $tax_code_values[2];
						$orders[$i]['tax_code'] = $tax_code;
					} else {
						$orders[$i]['tax_code'] = "";
					}
					
					$line_total = $orders[$i]['line_total'];
					$quantity = $orders[$i]['quantity'];
					
					if ($line_total > 0) {
						$purchase_price = $line_total / $quantity;
					} else {
						$purchase_price = "0";
					}
					
					$orders[$i]['purchase_price'] = $purchase_price;
					$orders[$i]['retail_price'] = $row['_price'];
					$orders[$i]['wholesale_price'] = $row['_wholesale_price'];
					$orders[$i]['cardtype'] = $row['_wc_moneris_card_type'];
					$orders[$i]['cardnumber'] = $row['_wc_moneris_account_four'];
					$orders[$i]['cardexpiry'] = $row['_wc_moneris_card_expiry_date'];
					$orders[$i]['cardtransamt'] = $row['_order_total'];
					$orders[$i]['product_id'] = $row['_product_id'];
					$orders[$i]['variation_id'] = $row['_variation_id'];
					$orders[$i]['shipping_method'] = "upsground";
					$orders[$i]['coupon_code'] = "";
					$orders[$i]['affiliate_amount'] = "";
					$orders[$i]['order_item_type'] = $row['order_item_type'];
											
					if ($order_item_type == "shipping") {
						
						$order_item_id = "";
						$item_sku = "SHIPPING";
						$orders[$i]['_sku'] = "SHIPPING";
						$orders[$i]['quantity'] = "1";
						$orders[$i]['model'] = "SHIPPING";
						$orders[$i]['line_total'] = "0";
						$orders[$i]['item_tax'] = "0";
						$orders[$i]['warepak_model'] = "SHIPPING";
							
						// Connect to MySQL and DB
						$sql3 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );

						$sql_query3 = "";
						$sql_query3 = "SELECT order_item_name,order_item_id FROM wpxa_woocommerce_order_items WHERE order_id = " . $order_id . " AND order_item_type = 'shipping'";
						
						$result3  = $sql3->query($sql_query3);

						if ( $result3->num_rows > 0 ) {
							// put results into an array
							while ($results3 = mysqli_fetch_array($result3, MYSQLI_ASSOC) ) {
								$shipping_results[] = $results3;
							}

							foreach($shipping_results as $shipping_result){
								$orders[$i]['shipping_method'] = $shipping_result['order_item_name'];
								$orders[$i]['shipping_method'] = "upsground";
								$orders[$i]['shipping_code'] = $shipping_result['order_item_name'];
								$order_item_id = $shipping_result['order_item_id'];
							}					
						} else {
							$orders[$i]['shipping_method'] = "upsground";
							$orders[$i]['shipping_code'] = "";
						}							
						$sql3->close();
						
						if ($orders[$i]['description'] == "Flat Rate" || $orders[$i]['description'] = "Wholesale Flat Rate") {
							
							$item_sku = "SHIPPING - FLAT";
							$orders[$i]['_sku'] = "SHIPPING - FLAT";
							$orders[$i]['quantity'] = "1";
							$orders[$i]['model'] = "SHIPPING - FLAT";
							$orders[$i]['line_total'] = "0";
							$orders[$i]['item_tax'] = "0";
							$orders[$i]['warepak_model'] = "SHIPPING - FLAT";
						
							// Connect to MySQL and DB
							$sql4 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );

							$sql_query4 = "";
							$sql_query4 = "select meta_value FROM wpxa_woocommerce_order_itemmeta where order_item_id = " . $order_item_id . " and meta_key = 'cost'";

							$result4  = $sql4->query($sql_query4);

							if ( $result4->num_rows > 0 ) {
								// put results into an array
								while ($results4 = mysqli_fetch_array($result4, MYSQLI_ASSOC) ) {
									$shipping_results2[] = $results4;
								}

								foreach($shipping_results2 as $shipping_result2){
									$orders[$i]['line_total'] = $shipping_result2['meta_value'];
									$orders[$i]['purchase_price'] = $orders[$i]['line_total'];
								}					
							}
							$sql4->close();
						}							
					}

					$sql3 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );
					
					$sql_query3 = "";
					$sql_query3 = "SELECT order_item_name FROM wpxa_woocommerce_order_items WHERE order_id = " . $order_id . " AND order_item_type = 'coupon'";
					
					$result3  = $sql3->query($sql_query3);					
					
					if ( $result3->num_rows > 0 ) {
						// put results into an array
						while ($results3 = mysqli_fetch_array($result3, MYSQLI_ASSOC) ) {
							$coupon_results[] = $results3;
						}

						foreach($coupon_results as $coupon_result){
							$orders[$i]['coupon_code'] = $coupon_result['order_item_name'];
						}					
					} else {
						$orders[$i]['coupon_code'] = "";
					}

					$sql3->close();
					
					//wpxa_affiliate_wp_referrals
					$sql3 = new mysqli($mysql_host,  $mysql_user, $mysql_password, $mysql_db) or die("Could not connect to database: " . mysql_error() );

					$sql_query3 = "";
					$sql_query3 = "SELECT amount FROM wpxa_affiliate_wp_referrals WHERE reference = " . $order_id . " AND context = 'woocommerce'";
					
					$result3  = $sql3->query($sql_query3);

					if ( $result3->num_rows > 0 ) {
						// put results into an array
						while ($results3 = mysqli_fetch_array($result3, MYSQLI_ASSOC) ) {
							$affiliate_results[] = $results3;
						}

						foreach($affiliate_results as $affiliate_result){
							$orders[$i]['affiliate_amount'] = $affiliate_result['amount'];
						}					
					} else {
						$orders[$i]['affiliate_amount'] = "";
					}

					$sql3->close();
			
					// setting payment entry
					if ( $order_id_test != $order_id) {				
						// flag record as payment record
						$orders[$i]['payment_record'] = "P";
						$order_id_test = $order_id;
					} else {				
						$orders[$i]['payment_record'] = "NP";
					}	
				}
				
				$sql2->close();		
				
				$i += 1;
				$j = 0;		
			} 
		}

		$sql->close();
		
		return $orders;
	} 

	function ftpFiles ($orders, $base_path, $payments_file, $orders_warepak_file, $orders_gsn_file, $ftp_host, $ftp_usr, $ftp_pw, $mysql_host, $mysql_user, $mysql_password, $mysql_db) {
	
		// FTP location
		$destination_path = "";
		$destination_file = "";
		$destination_path = "incoming/";
		$destination_file = $destination_path . $orders_warepak_file;
				
		// Trap empty order array with no data 
		if ( count($orders) < 1 ) {
			
			print "No orders to FTP\n";
			exit;
			
		} else {
			
			if (is_array($orders)) {
				
				// Create file var to write data to.
				print "Creating: " . $base_path."/orders/".$orders_warepak_file . "\n";
				print "Creating: " . $base_path."/orders/".$orders_gsn_file . "\n";
				
				$d_fh_warepak = fopen($base_path."/orders/".$orders_warepak_file, 'w+') or die($php_errormsg);
				$d_fh_gsn = fopen($base_path."/orders/".$orders_gsn_file, 'w+') or die($php_errormsg);
				
				// Write data to variable			
				$file_text_warepak = '"Date","Invoice Number","Invoice to Name","Invoice To Address 1","Invoice to Address 2","Invoice To Address 3","Invoice To City","Invoice To State","Invoice To PostalCode","Invoice To Country","Ship To Name","Ship To Address1","Ship To Address2","Ship To Address 3","Ship To City","Ship To State","Ship To PostalCode","Ship To Country","Email","Phone","PO Number","Ship Date","Ship Via","Qty","Item","Description","Price Each","Amount","Tax"';
				
				// copy the header values and append the additional column for GSN
				$file_text_gsn = $file_text_warepak;
				$file_text_gsn .= ',"Coupon","TaxCode","Affiliate Amount"';
				
				// end the header rows
				$file_text_warepak .= "\015\012";
				$file_text_gsn .= "\015\012";
				
				// place holders for the loop rows
				$hold_line = "";
				$hold_line_gsn = "";
				$invoice_no_test = 0;
				$hold_order_id = "";
				
				$user_role = "";
				
				foreach ( $orders as $order ) {
					
					$user_role = $order['user_role'];

					if (($order['comment'] == "wc-processing") && $order['invoice_no'] != "" && $order['order_id'] != "") {						
						
						// change to if wholesale price doesn't exist
						// add in postcard line item when retail box exists, COL-4000
						if ($user_role != "wholesale_subscriber" && $order['order_id'] != $hold_order_id) {
						
							$hold_line = '"'.$order['date_added']
							.'","'.$order['invoice_no']
							.'","'.$order['payment_company'] . " " . $order['payment_firstname'] . " " . $order['payment_lastname']
							.'","'.$order['payment_address_1']
							.'","'.$order['payment_address_2']
							.'","'.""
							.'","'.$order['payment_city']
							.'","'.$order['payment_zone']
							.'","'.$order['payment_postcode']
							.'","'.$order['payment_country']
							.'","'.$order['shipping_company'] . " " . $order['shipping_firstname'] . " " . $order['shipping_lastname']
							.'","'.$order['shipping_address_1']
							.'","'.$order['shipping_address_2']
							.'","'.""
							.'","'.$order['shipping_city']
							.'","'.$order['shipping_zone']
							.'","'.$order['shipping_postcode']
							.'","'.$order['shipping_country']
							.'","'.$order['email']
							.'","'.$order['telephone']						
							.'","'.""				
							.'","'.""
							.'","'.$order['shipping_method']
							.'","'."1"
							.'","'."GSN-Retail Shipper"
							.'","'."GSN-Retail Shipper"
							.'","'."0.00"
							.'","'."0.00"
							.'","'."0.00";
							
							$hold_line .= '"';				
							$hold_line .= "\015\012";
							
							$file_text_warepak .= $hold_line;
							
							$hold_order_id = "";
						}
						
						$hold_line = '"'.$order['date_added']
						.'","'.$order['invoice_no']
						.'","'.$order['payment_company'] . " " . $order['payment_firstname'] . " " . $order['payment_lastname']
						.'","'.$order['payment_address_1']
						.'","'.$order['payment_address_2']
						.'","'.""
						.'","'.$order['payment_city']
						.'","'.$order['payment_zone']
						.'","'.$order['payment_postcode']
						.'","'.$order['payment_country']
						.'","'.$order['shipping_company'] . " " . $order['shipping_firstname'] . " " . $order['shipping_lastname']
						.'","'.$order['shipping_address_1']
						.'","'.$order['shipping_address_2']
						.'","'.""
						.'","'.$order['shipping_city']
						.'","'.$order['shipping_zone']
						.'","'.$order['shipping_postcode']
						.'","'.$order['shipping_country']
						.'","'.$order['email']
						.'","'.$order['telephone']							
						.'","'.""				
						.'","'.""
						.'","'.$order['shipping_method']
						.'","'.$order['quantity']
						.'","'.$order['warepak_model']
						.'","'.$order['description']
						.'","'.$order['purchase_price']
						.'","'.$order['line_total']
						.'","'.$order['item_tax'];
						
						$hold_line_gsn = '"'.$order['date_added']
						.'","'.$order['invoice_no']
						.'","'.$order['payment_company'] . " " . $order['payment_firstname'] . " " . $order['payment_lastname']
						.'","'.$order['payment_address_1']
						.'","'.$order['payment_address_2']
						.'","'.""
						.'","'.$order['payment_city']
						.'","'.$order['payment_zone']
						.'","'.$order['payment_postcode']
						.'","'.$order['payment_country']
						.'","'.$order['shipping_company'] . " " . $order['shipping_firstname'] . " " . $order['shipping_lastname']
						.'","'.$order['shipping_address_1']
						.'","'.$order['shipping_address_2']
						.'","'.""
						.'","'.$order['shipping_city']
						.'","'.$order['shipping_zone']
						.'","'.$order['shipping_postcode']
						.'","'.$order['shipping_country']
						.'","'.$order['email']
						.'","'.$order['telephone']		
						.'","'.""				
						.'","'.""
						.'","'.$order['shipping_method']
						.'","'.$order['quantity']
						.'","'.$order['model']
						.'","'.$order['description']
						.'","'.$order['purchase_price']
						.'","'.$order['line_total']
						.'","'.$order['item_tax'];
						
						// copy the first part of the common values to the gsn hold and then append the extra shipping column
						$hold_line_gsn .= '","'.$order['coupon_code'];
						$hold_line_gsn .= '","'.$order['tax_code'];
						
						if (strcmp($order['invoice_no'], $invoice_no_test) !== 0) {									
							$hold_line_gsn .= '","'.$order['affiliate_amount'];
							$invoice_no_test = $order['invoice_no'];
						} else {
							$hold_line_gsn .= '","'."";
							$hold_line_gsn .= '","'."";
						}
						
						// end the rows
						$hold_line .= '"';				
						$hold_line .= "\015\012";
						
						$hold_line_gsn .= '"';				
						$hold_line_gsn .= "\015\012"; 
						
						$file_text_warepak .= $hold_line;
						$file_text_gsn .= $hold_line_gsn;						
						
					}
					
					if ($hold_order_id == "") {
						$hold_order_id = $order['order_id'];
					}
				}
				
				// write data to file	
				fputs($d_fh_warepak,"$file_text_warepak\n");
				fclose($d_fh_warepak);
				fputs($d_fh_gsn,"$file_text_gsn\n");
				fclose($d_fh_gsn);
			}

			unset($order);

			if (is_array($orders)) {
			
				// Create Payment file var to write data to.
				print "Creating: " . $base_path."/orders/".$payments_file . "\n";
				$d_fh2 = fopen($base_path."/orders/".$payments_file, 'w+') or die($php_errormsg);
			
				// Write Payment data to variable
				$payment_file_text = '"OrderID","OrderDate","ISBN/UPC","QtyOrdered","ListPrice","Net Price","ShipVia","ShipCost","TaxAmt","OrderTot","TaxCode","Source code","list code","comments","Bill2ID","Bill2Name","Bill2addr1","Bill2addr2","Bill2city","bill2state","bill2zip","bill2country","bill2phone","bill2email","ship2id","ship2name","ship2first","ship2addr1","ship2addr2","ship2city","ship2state","ship2zip","ship2country","ship2phone","ship2email","cctype","ccnum","ccexp","ccamt","group","payment_flag","bank_code"'; 
				$payment_file_text .= "\015\012";  
			
				foreach ( $orders as $order ) {
					
					if (($order['comment'] == "wc-completed" || $order['comment'] == "wc-processing") && $order['invoice_no'] != "" && $order['order_id'] != "") {
						
						if ($order['payment_record'] == "P") {

							$payment_file_text .=   '"'.$order['invoice_no']
							.'","'.$order['date_added']
							.'","'.$order['model']
							.'","'.$order['quantity']
							.'","'.$order['purchase_price']
							.'","'.$order['line_total']
							.'","'.$order['shipping_method']
							.'","'.$order['shipping_fee']
							.'","'.$order['order_tax']
							.'","'.$order['total']
							.'","'.$order['tax_code']							
							.'","'."WEB"
							.'","'.""
							.'","'.$order['comment']
							.'","'.$order['customer_id']
							.'","'.$order['payment_firstname'].' '.$order['payment_lastname']							
							.'","'.$order['payment_address_1']
							.'","'.$order['payment_address_2']
							.'","'.$order['payment_city']
							.'","'.$order['payment_zone']
							.'","'.$order['payment_postcode']
							.'","'.$order['payment_country']
							.'","'.$order['telephone']
							.'","'.$order['email']
							.'","'.$order['customer_id']
							.'","'.$order['shipping_lastname']
							.'","'.$order['shipping_firstname']
							.'","'.$order['shipping_address_1']
							.'","'.$order['shipping_address_2']
							.'","'.$order['shipping_city']
							.'","'.$order['shipping_zone']
							.'","'.$order['shipping_postcode']
							.'","'.$order['shipping_country']
							.'","'.$order['telephone']
							.'","'.$order['email']
							.'","'.$order['cardtype']			
							.'","'.$order['cardnumber']	
							.'","'.$order['cardexpiry']	
							.'","'.$order['cardtransamt']
							.'","'.$order['customer_group']
							.'","'.$order['payment_record']
							.'","'."HSBC CDN Account"
							.'"';
							
							$payment_file_text .= "\015\012";
						}
					}
				}
				// write data to file	
				fputs($d_fh2,"$payment_file_text\n");
				fclose($d_fh2);
			
			}
			
			if (is_array($orders)) {
			
				// Send data file to FTP site
				/*$conn = ftp_connect($ftp_host) or die("Could not connect - $ftp_host\n");
				ftp_login($conn,$ftp_usr,$ftp_pw);
				ftp_pasv($conn, true);
				
				print "\n" . $destination_file . "\n";
				print $base_path."/orders/".$orders_warepak_file . "\n";
				$ftp_status = ftp_put( $conn , $destination_file , $base_path."/orders/".$orders_warepak_file ,FTP_ASCII);
				ftp_close($conn);				
				print "FTP Status: " . $ftp_status . "\n";*/
								
				// Move data file to sent folder if successful File transfer	
				if ( $ftp_status == TRUE ) {
					print "FTP data transfer complete\n";
				} else {
					print "FTP data transfer FAILED\n";
				}
			}
		} // End if statement - Trap empty export with no data from sending to Ware-pak
	}

	function sendEmail ($email_from, $email_to, $email_cc, $base_path, $payments_file, $orders_gsn_file, $orders_warepak_file) {
	
		$bodytext = "";
		$bodytext = "See attached file - " . date ('ymd-H-i-s');
		
		$email = new PHPMailer();
		$email->From      = $email_from;
		$email->FromName  = "GSN Orders CA Site";
		$email->Subject   = "GSN Orders CA Files " . date ('ymd-H-i-s');
		$email->Body      = $bodytext;
		$email->AddAddress( $email_to );
		$email->AddCC("");
		$email->AddBCC($email_cc);

		$file_to_attach = $base_path."/orders/".$payments_file;
		$email->AddAttachment( $file_to_attach , $payments_file );
		
		$file_to_attach = $base_path."/orders/".$orders_gsn_file;
		$email->AddAttachment( $file_to_attach , $orders_gsn_file );
		
		//$file_to_attach = $base_path."/orders/".$orders_warepak_file;
		//$email->AddAttachment( $file_to_attach , $orders_warepak_file );

		$email->Send();
		
		rename ( $base_path."/orders/".$payments_file , $base_path."/orders/sent-orders/".$payments_file );
		rename ( $base_path."/orders/".$orders_gsn_file , $base_path."/orders/sent-payments/".$orders_gsn_file );
		
		return true;
	}

?>
