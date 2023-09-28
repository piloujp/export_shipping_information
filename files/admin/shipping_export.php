<?php
/**
 * Exports Order / Shipping Information From Zen Cart in various chosen formats
 *
 * @package Export Shipping and Order Information
 * @copyright Copyright 2009, Eric Leuenberger http://www.zencartoptimization.com
 * @copyright Portions Copyright 2003-2006 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: shipping_export.php, v 1.3.2 08.05.2010 11:41 Eric Leuenberger econcepts@zencartoptimization.com$
 * Thanks to dhcernese and Scott Wilson (That Software Guy) for contributing various portions that contained several bug-fixes.
  Version: 1.4.2 2023-09-28 BMH (OldNGrey)
 */
// BMH Version Number - set on line 53
// BMH 2021-10 correct version number
// BMH 2022-05-10 line 321 added fields to group by
// BMH  line 53 date format
// BMH  variables
// BMH  2022-10-02  ln172 ANY_VALUE not supported in MariaDB
//                  ln 871 undefined offset
//                  ln148 isset; ln707 isset; ln977 isset($_POST['order_status_setting'])
//      2022-11-30  ln Undefined index: dload_include
//BMH 2023-03-03 reorder to match reconciliation spreadsheet;
//              add line tax space; negative symbol for discount
//                  ln1034 default most commonly used check boxes to checked
// BMH ln633 include other discount types - only picks up first one
// BMH ln633 included ot_paymentmodulefee; accumulate discounts and make negative number
//               select DISTINCT to obtain only one values where multiple discounts are applied and remove orders products from inner join
//      ln128 +ln509 included products_tax/100
//      ln319 moved shipping total to follow after subtotal heading
//      ln636 moved shipping value to follow after subtotal value
// 2023-08-29 shipping method heading, comments, status correct placement
//       clean product name if double quotes included as per abbr for inch
// 2023-09-28 ln347 use case to determine words in name; 1 word = STOREPICKUP
//

 if (!isset($success_message)) {$success_message ='';}
 if (!isset($linevalue)) {$linevalue ='';} // BMH line 450
 if (!isset($html_msg )) {$html_msg  ='';} // BMH line 676
 if (!isset($export_test_checked)) {$export_test_checked ='';} // BMH line 894
 if (!isset($export_split_checked)) {$export_split_checked ='';} // BMH line 895
 if (!isset($date_status)) {$date_status ='';} // BMH line 896
 if (!isset($export_header_row_checked)) {$export_header_row_checked ='';} // BMH line 897
 if (!isset($order_status_setting)) {$order_status_setting ='';} // BMH line 934
 if (!isset($order_status_setting_checked)) {$order_status_setting_checked ='';} // BMH line 948
 if (!isset($order_status)) {$order_status ='';} // BMH line 949
 if (!isset($dload_include_checked)) {$dload_include_checked ='';} // BMH line 960
 if (!isset($shipping_total_checked)) {$shipping_total_checked ='';} // BMH line 969
 if (!isset($order_total_checked)) {$order_total_checked ='';} // BMH line 970
 if (!isset($date_purchased_checked)) {$date_purchased_checked ='';} // BMH line 971
 if (!isset($order_tax_checked)) {$order_tax_checked ='';} // BMH line 972
 if (!isset($order_subtotal_checked)) {$order_subtotal_checked ='';} // BMH line 973
 if (!isset($order_discount_checked)) {$order_discount_checked ='';} // BMH line 974
 if (!isset($order_pmethod_checked)) {$order_pmethod_checked ='';} // BMH line 975
 if (!isset($shipping_method_checked)) {$shipping_method_checked ='';} // BMH line 977
 if (!isset($order_comments_checked)) {$order_comments_checked ='';} // BMH line 978
 if (!isset($phone_number_checked)) {$phone_number_checked ='';} // BMH line 979
 if (!isset($order_status_checked)) {$order_status_checked ='';} // BMH line 980
 if (!isset($iso_country2_code_checked)) {$iso_country2_code_checked ='';} // BMH line 981
 if (!isset($iso_country3_code_checked)) {$iso_country3_code_checked ='';} // BMH line 982
 if (!isset($prod_details_checked)) {$prod_details_checked ='';} // BMH line 987
 if (!isset($dload_include)) {$dload_include ='';} // BMH line 144,574,615

define('VERSION', '1.4.2');
define('ESIVERSION', '1.4.2');
require('includes/application_top.php');
require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();
include(DIR_WS_CLASSES . 'order.php');

$fullname = '';
$first = '';
$middle = '';
$last = '';

// change destination here for path when using "save to file on server"
if (!defined('DIR_FS_EMAIL_EXPORT')) define('DIR_FS_EMAIL_EXPORT', DIR_FS_CATALOG . 'images/uploads/');

/** Set Available Export Formats **/
$available_export_formats[0] = array('id' => '0', 'text' => 'CSV');
$available_export_formats[1] = array('id' => '1', 'text' => 'TXT');
//  $available_export_formats[2]=array('id' => '2', 'text' => 'HTML');
//  $available_export_formats[3]=array('id' => '3', 'text' => 'XML');
/**********************************/
/** Set Variables **/
$save_to_file_checked = (isset($_POST['savetofile']) && zen_not_null($_POST['savetofile']) ? $_POST['savetofile'] : 0);
$post_format = (isset($_POST['format']) && zen_not_null($_POST['format']) ? $_POST['format'] : 1);
$format = $available_export_formats[$post_format]['text'];
/* Get file types */
if ($format == 'CSV') {
   $file_extension = '.csv';
   $FIELDSTART = '"';
   $FIELDEND = '"';
   $FIELDSEPARATOR = ',';
   $LINESTART = '';
   $LINEBREAK = "\n";
   $ATTRIBSEPARATOR = ' | '; //Be Careful with this option. Setting it to a 'comma' for example could throw off the remaining fields.
}
if ($format == 'TXT') {
   $file_extension = '.txt';
   $FIELDSTART = '';
   $FIELDEND = '';
   $FIELDSEPARATOR = "\t"; // Tab separated
   //$FIELDSEPARATOR = ','; // Comma separated
   $LINESTART = '';
   $LINEBREAK = "\n";
   $ATTRIBSEPARATOR = ' | '; //Be Careful with this option. Setting it to a 'comma' for example could throw off the remaining fields.
  }// BMH change file name date format from 'mdy-Hi' to 'Ymd-Hi'
  $file = (isset($_POST['filename']) ? $_POST['filename'] : "Orders".date('Ymd-Hi'). $file_extension ."");
//$file = (isset($_POST['filename']) ? $_POST['filename'] : "Orders". $file_extension ."");
$to_email_address = (isset($_POST['auto_email_supplier']) ? $_POST['auto_email_supplier'] : "" . EMAIL_EXPORT_ADDRESS . "");
$email_subject = (isset($_POST['auto_email_subject']) ? $_POST['auto_email_subject'] : "Order export from " . STORE_NAME . "");
/*******************/


if (isset($_POST['download_csv']))
{ // If form was submitted then do processing.
    // begin streaming file contents
   /*
       Header('Content-type: application/csv');
       Header("Content-disposition: attachment; filename=\"". $file ."");
   */
    // if date_range is set then gather form vars for SQL processing
   if ($_POST['start_date'] != '') {
      $start_date = $_POST['start_date'] . ' 00:00';
   }
   if ($_POST['end_date'] != '') {
      $end_date = $_POST['end_date'] . ' 23:59';
   }
//**************************************************************

   if (isset($_POST['filelayout']) == 2)  { // 1 Product Per row RADIO

      $order_info = "SELECT o.orders_id, customers_email_address, delivery_name, delivery_company, delivery_street_address, delivery_suburb,
        delivery_city, delivery_postcode, delivery_state, delivery_country, shipping_method, customers_telephone, order_total, op.products_model,
        products_name, op.products_price, final_price, op.products_quantity, op.products_tax, date_purchased, ot.value, orders_products_id, order_tax,
        o.orders_status, o.payment_method";
      if (isset($_POST['iso_country2_code']) == 1) {         $order_info = $order_info . ", cc.countries_iso_code_2";      };
      if (isset($_POST['iso_country3_code']) == 1) {         $order_info = $order_info . ", cc.countries_iso_code_3";      };
      /*
      if (ACCOUNT_STATE == 'true') {
         $order_info = $order_info . ", z.zone_code";
      }
      */
      $order_info = $order_info . " FROM (" . TABLE_ORDERS . " o LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " op ON o.orders_id = op.orders_id), " . TABLE_ORDERS_TOTAL . " ot";
      if (isset($_POST['iso_country2_code']) == 1 || isset($_POST['iso_country3_code']) == 1) { $order_info = $order_info . ", " . TABLE_COUNTRIES . " cc";   };
      /*
      if (ACCOUNT_STATE == 'true') {
         $order_info = $order_info . ", " . TABLE_ZONES . " z";
      }
      */
    // BMH    if ($_POST['iso_country2_code'] == 1 || $_POST['iso_country3_code'] == 1) { $order_info = $order_info . " AND cc.countries_name = o.delivery_country "; };
    // BMH    $order_info = $order_info . " WHERE o.orders_id = ot.orders_id ";
     $order_info = $order_info . " WHERE o.orders_id = ot.orders_id ";
    if (isset($_POST['iso_country2_code']) == 1 || isset($_POST['iso_country3_code']) == 1) {   // BMH isset
        $order_info = $order_info . " AND cc.countries_name = o.delivery_country ";
        };


      /*
      if (ACCOUNT_STATE == 'true') { // If states are used on account form then find out which type.
         if (ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true') { // If using state drop down then match against the abbreviation
            $order_info = $order_info . "AND z.zone_code = o.delivery_state ";
         } else { //Not using state drop down so match against full state name.
            $order_info = $order_info . "AND z.zone_name = o.delivery_state ";
         } // end if state drop down or not.
      } // end if for determining if states are used on account form.
      */
      $order_info = $order_info . "AND ot.class = 'ot_shipping'";
      if ($_POST['dload_include'] != 1) {
         $order_info = $order_info . " AND downloaded_ship='no'";
      }
      if ($_POST['status_target'] == 2) {
         $order_info = $order_info . " AND o.orders_status = '" . $_POST['order_status'] . "'";
      }
       // if ($_POST['start_date'] != '' && $_POST['end_date'] != '') {     // BMH isset
       if (isset($_POST['start_date']) != '' && isset($_POST['end_date']) != '') {     // BMH isset
         $order_info = $order_info . " AND date_purchased BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
         //$order_info = $order_info . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
      }
      $order_info = $order_info . " ORDER BY orders_id ASC";
      //echo $order_info;

   } else  { // Default 1 Order Per row (filelayout1=1)
     //   $order_info = "SELECT o.orders_id, customers_email_address, delivery_name, delivery_company, delivery_street_address, delivery_suburb, delivery_city, delivery_postcode, delivery_state, delivery_country, shipping_method, customers_telephone, order_total, date_purchased, ot.value, order_tax, o.orders_status, o.payment_method"; // BMH remove os.comments
      $order_info = "SELECT o.orders_id, customers_email_address, delivery_name, delivery_company, delivery_street_address, delivery_suburb, delivery_city, delivery_postcode, delivery_state, delivery_country, shipping_method, customers_telephone, order_total, date_purchased, ot.value, ANY_VALUE(os.comments), order_tax, o.orders_status, o.payment_method"; // BMH ANY_VALUE comments NO support in MariaDB
      if (isset($_POST['iso_country2_code']) == 1) { $order_info = $order_info . ", cc.countries_iso_code_2";     };    // BMH isset
      if (isset($_POST['iso_country3_code']) == 1) {$order_info = $order_info . ", cc.countries_iso_code_3";       };   // BMH isset
      /*
      if (ACCOUNT_STATE == 'true') {
         $order_info = $order_info . ", z.zone_code";
      }
      */
      $order_info = $order_info . " FROM " . TABLE_ORDERS . " o, " . TABLE_ORDERS_STATUS_HISTORY . " os, " . TABLE_ORDERS_TOTAL . " ot";
      if (isset($_POST['iso_country2_code']) == 1 || isset($_POST['iso_country3_code']) == 1) { // BMH isset
         $order_info = $order_info . ", " . TABLE_COUNTRIES . " cc";    // BMH isset
      };
      /*
      if (ACCOUNT_STATE == 'true') {
         $order_info = $order_info . ", " . TABLE_ZONES . " z";
      }
      */
      $order_info = $order_info . " WHERE o.orders_id = ot.orders_id
    AND ot.class = 'ot_shipping' ";
      if (isset($_POST['iso_country2_code']) == 1 || isset($_POST['iso_country3_code']) == 1) { // BMH isset
         $order_info = $order_info . " AND cc.countries_name = o.delivery_country ";
      };
      /*
      if (ACCOUNT_STATE == 'true') { // If states are used on account form then find out which type.
         if (ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true') { // If using state drop down then match against the abbreviation
            $order_info = $order_info . "AND z.zone_code = o.delivery_state ";
         } else { //Not using state drop down so match against full state name.
            $order_info = $order_info . "AND z.zone_name = o.delivery_state ";
         } // end if state drop down or not.
      } // end if for determining if states are used on account form.
      */
      $order_info = $order_info . "AND o.orders_id = os.orders_id";
      if (isset($_POST['dload_include']) != 1) {    // BMH isset
         $order_info = $order_info . " AND downloaded_ship='no'";
      }
      if ($_POST['status_target'] == 2) {
         $order_info = $order_info . " AND o.orders_status = '" . $_POST['order_status'] . "'";
      }
      if ($_POST['start_date'] != '' && $_POST['end_date'] != '') {
         $order_info = $order_info . " AND date_purchased BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
         //$order_info = $order_info . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
      }
    $order_info = $order_info . " GROUP BY o.orders_id, ot.value ASC"; // BMH add o. & ot.value
      //echo $order_info;

      $max_num_products = "SELECT COUNT( * ) AS max_num_of_products
    FROM (" . TABLE_ORDERS . " o LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " op ON o.orders_id = op.orders_id), " . TABLE_ORDERS_TOTAL . " ot
    WHERE o.orders_id = ot.orders_id
    AND ot.class = 'ot_shipping'";
      if (isset($_POST['dload_include']) != 1) {    // BMH isset
         $max_num_products = $max_num_products . " AND downloaded_ship='no'";
      }
      if ($_POST['status_target'] == 2) {
         $max_num_products = $max_num_products . " AND o.orders_status = '" . $_POST['order_status'] . "'";
      }
      if ($_POST['start_date'] != '' && $_POST['end_date'] != '') {
         $max_num_products = $max_num_products . " AND date_purchased BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
         //$max_num_products = $max_num_products . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
      }
      $max_num_products = $max_num_products . " GROUP BY o.orders_id ASC
    ORDER BY max_num_of_products DESC
    LIMIT 1";
      $max_num_products_result = $db->Execute($max_num_products);
      $max_products = $max_num_products_result->fields['max_num_of_products'];

   } // End File layout sql

   $order_details = $db->Execute($order_info);
   /******************Begin Set Header Row Information*****************************/
   $str_header = "Order ID,Customer Email";
   if ($_POST['split_name'] == 1) { //If name split is desired then split it.
      $str_header = $str_header . ",First Name,Last Name";
   } else {
      $str_header = $str_header . ",Delivery Name";
   }
   $str_header = $str_header . ",Company,Delivery Street,Delivery Suburb,Delivery City,Delivery State,Delivery Post Code,Delivery Country,Ship Dest Type"; // swguy
   if (isset($_POST['shipmethod']) == 1) {  // BMH isset
      $str_header = $str_header . ",Shipping Method";
   };
/*   if (isset($_POST['shiptotal']) == 1) {   // BMH isset
      $str_header = $str_header . ",Shipping Total";
   }; */
   if (isset($_POST['customers_telephone']) == 1) { // BMH isset
      $str_header = $str_header . ",Customers Telephone";
   };
      if (isset($_POST['orders_status_export']) == 1) {    // BMH isset
      $str_header = $str_header . ",Order Status";
   };
 /*     if (isset($_POST['shipmethod']) == 1) {  // BMH isset
      $str_header = $str_header . ",Shipping Method";
   }; // BMH remove as redundant
   */
/*   if (isset($_POST['order_total']) == 1) { // BMH isset
      $str_header = $str_header . ",Order Total";
   }; */

/*   if (isset($_POST['order_tax']) == 1) {   // BMH isset
      $str_header = $str_header . ",Order Tax";
   }; */
/*   if (isset($_POST['order_subtotal']) == 1) {  // BMH isset
      $str_header = $str_header . ",Order Subtotal";
   }; */
/*   if (isset($_POST['order_discount']) == 1) {  // BMH isset
      $str_header = $str_header . ",Order Discount";
   }; */
/*   if (isset($_POST['payment_method']) == 1) {  // BMH isset
      $str_header = $str_header . ",Payment Method";
   };
 */
   if (isset($_POST['iso_country2_code']) == 1) {   // BMH isset
      $str_header = $str_header . ",ISO Country Code 2";
   };
   if (isset($_POST['iso_country3_code']) == 1) {   // BMH isset
      $str_header = $str_header . ",ISO Country Code 3";
   };
   if (isset($_POST['order_comments']) == 1) {  // BMH isset
      $str_header = $str_header . ",Order Notes";
   };
    if (isset($_POST['date_purchased']) == 1) {  // BMH isset
      $str_header = $str_header . ",Order Date";
   };
//        if ($_POST['abbr_state_code'] == 1) { $str_header = $str_header . ",Abbr State Code"; };
   if (isset($_POST['product_details']) == 1) { // add to header row    // BMH isset
      if ($_POST['filelayout'] == 2) { // 1 Product Per row RADIO
                $str_header = $str_header . ",Product Qty,Product Model,Product Name,Products Price,Product Attributes";
            } else
            { // File layout is 1 OPR
         /**************the following exports 1 OPR attribs****************/
         $oID = zen_db_prepare_input($order_details->fields['orders_id']);
         $oIDME = $order_details->fields['orders_id'];
                $this->order = new order($oID); //BMH 2019 correction
                for ($i = 0, $n = $max_products; $i < $n; $i++)
                { // BMH rearrange order
            $str_header = $str_header . ",Product " . $i . " Qty";
                    $str_header = $str_header . ",Product " . $i . " Model";
                    $str_header = $str_header . ",Product " . $i . " Name";
            $str_header = $str_header . ",Product " . $i . " Price";
            $str_header = $str_header . ",Product " . $i . " Attributes";
         }
         /*****************************************************************/
      } // End if to determine which header to use
   } // end Row header if product details selected
    // BMH add reordered headings
        $str_header = $str_header . ",Line cost";  // BMH ADDITION
        $str_header = $str_header . ",Line tax";  // BMH ADDITION
        //if (isset($_POST['order_tax']) == 1) { $str_header = $str_header . ",Order Tax"; }; // BMH isset
        if (isset($_POST['order_subtotal']) == 1) { $str_header = $str_header . ",Order Subtotal"; };   // BMH isset
        if (isset($_POST['shiptotal']) == 1) { $str_header = $str_header . ",Shipping Total"; };    // BMH isset
        if (isset($_POST['order_discount']) == 1) { $str_header = $str_header . ",Order Discount"; };   // BMH isset
        if (isset($_POST['order_total']) == 1) { $str_header = $str_header . ",Order Total"; }; // BMH isset
        if (isset($_POST['order_tax']) == 1) { $str_header = $str_header . ",Order Tax"; }; // BMH isset

        if (isset($_POST['payment_method']) == 1) { $str_header = $str_header . ",Payment Method"; };   // BMH isset
    // Print header row
   $str_header = $str_header . "\n";
   /******************End Header Row Information*****************************/

   /* dhc */
//    $str_header = $str_header . $order_info . "<br />\n" . $order_details->RecordCount() . "<br />\n";
   $str_full_export = "";
/* bof prepare data lines  */
   while (!$order_details->EOF) {

         $str_export = $FIELDSTART . $order_details->fields['orders_id'] . $FIELDEND . $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['customers_email_address'] . $FIELDEND;
         if (isset($_POST['split_name']) == 1) {   // BMH isset
            $fullname = $order_details->fields['delivery_name'];

            switch (str_word_count($fullname) ) {
                case 3:
                    list($first, $middle, $last) = preg_split("/[\s,]+/", $fullname);
                    break;
                case 2:    
                    list($first, $last) = preg_split("/[\s,]+/", $fullname);   // BMH remove middle
                    break;
                case 1:
                    list($last) = preg_split("/[\s,]+/", $fullname);
                    break;
            }
         
         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $first . $FIELDEND . $FIELDSEPARATOR . $FIELDSTART . $last . $FIELDEND;
      } else {
         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_name'] . $FIELDEND;
      };
// swguy
      if ($order_details->fields['delivery_company'] == '') {
         $dest_type = 'Residential';
      } else {
         $dest_type = 'Commercial';
      }
// end swguy
      $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_company'] . $FIELDEND . $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_street_address'] . $FIELDEND .
         $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_suburb'] . $FIELDEND . $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_city'] . $FIELDEND .
         $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_state'] . $FIELDEND . $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_postcode'] . $FIELDEND .
         $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['delivery_country'] . $FIELDEND .
         $FIELDSEPARATOR . $FIELDSTART . $dest_type . $FIELDEND;
// swguy last line changed
      if (isset($_POST['shipmethod']) == 1) {
         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['shipping_method'] . $FIELDEND;
      };
//      if ($_POST['shiptotal'] == 1) {         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['value'] . $FIELDEND;      };
      if (isset($_POST['customers_telephone']) == 1) {         $str_export .= $FIELDSEPARATOR . $FIELDSTART . "'" .$order_details->fields['customers_telephone'] . $FIELDEND;      }; //BMH prepend single quote for excel
//      if ($_POST['order_total'] == 1) {         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['order_total'] . $FIELDEND;      };
// BMH moved        if ($_POST['date_purchased'] == 1) { $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['date_purchased'] . $FIELDEND; };
/* moved after iso code
        if (isset($_POST['order_comments']) == 1)
        {
            if ($_POST['filelayout'] == 2)
            { // 1 Product Per row RADIO
                // BMH MySQLD added extra fields to avoid group by error
                $orders_comments_query="SELECT *  FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = " . $order_details->fields['orders_id'] . "
                GROUP BY orders_id, orders_status_id, orders_status_history_id, date_added, customer_notified, comments, updated_by
                ORDER BY orders_status_history_id ASC";
            $orders_comments = $db->Execute($orders_comments_query);
            $str_safequotes = str_replace('"', "'", $orders_comments->fields['comments']); // replace quotes with single quotes if present
            //$str_safequotes = $str_safequotes . str_replace(","," ",$str_safequotes); // replace commas with blank space if present
            // dhc 16-Nov-2007 array("\r\n","\r","\n")
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . str_replace(array("\r\n", "\r", "\n"), " ", $str_safequotes) . $FIELDEND; // Remove any line breaks in first comment and print to export string
         } else {
            $str_safequotes = str_replace('"', "'", $order_details->fields['comments']); // replace quotes with single quotes if present
            //$str_safequotes = $str_safequotes . str_replace(","," ",$str_safequotes); // replace commas with blank space if present
            // dhc 16-Nov-2007 array("\r\n","\r","\n")
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . str_replace(array("\r\n", "\r", "\n"), " ", $str_safequotes) . $FIELDEND; // Remove any line breaks in first comment and print to export string
         }
      }
*/
      /***********************************************************************************************************************************/
/* BMH copy to end      if ($_POST['order_tax'] == 1) {         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['order_tax'] . $FIELDEND;       };

        // bof sub-totals
/*      if ($_POST['order_subtotal'] == 1) {

         $orders_subtotal_query = "SELECT o.orders_id, customers_email_address, delivery_name, delivery_company, delivery_street_address, delivery_suburb, delivery_city, delivery_postcode, delivery_state, delivery_country, shipping_method, customers_telephone, order_total, products_model, products_name, products_price, final_price, products_quantity, date_purchased, ot.value, orders_products_id, order_tax
    FROM (" . TABLE_ORDERS . " o LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " op ON o.orders_id = op.orders_id), " . TABLE_ORDERS_TOTAL . " ot
    WHERE o.orders_id = ot.orders_id
    AND ot.class = 'ot_subtotal'
    AND ot.orders_id = " . $order_details->fields['orders_id'] . "";
         if ($_POST['dload_include'] != 1) {
            $orders_subtotal_query = $orders_subtotal_query . " AND downloaded_ship='no'";
         }
         if ($_POST['status_target'] == 2) {
            $orders_subtotal_query = $orders_subtotal_query . " AND o.orders_status = '" . $_POST['order_status'] . "'";
         }
         if ($_POST['start_date'] != '' && $_POST['end_date'] != '') {
            $orders_subtotal_query = $orders_subtotal_query . " AND date_purchased BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
//$orders_subtotal_query = $orders_subtotal_query . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
         }
         $orders_subtotal_query = $orders_subtotal_query . " ORDER BY orders_id ASC";

         $orders_subtotal = $db->Execute($orders_subtotal_query);

         $num_rows = $orders_subtotal->RecordCount();
         if ($num_rows > 0) {
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $orders_subtotal->fields['value'] . $FIELDEND; //add discount amt to export string
         } else { // add a BLANK field to the export file for "consistancy"
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $FIELDEND; // add blank space for filler
         } // end if


        } // eof sub-totals
*/
    /*    if ($_POST['order_discount'] == 1)         { // if order discount was selected, then run the query to pull the data for adding it to the export string.
// Run a query to pull the Order Discount total if present
         $orders_discount_query = "SELECT o.orders_id, ot.value
    FROM (" . TABLE_ORDERS . " o LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " op ON o.orders_id = op.orders_id), " . TABLE_ORDERS_TOTAL . " ot
    WHERE o.orders_id = ot.orders_id
    AND ot.class = 'ot_coupon'
    AND ot.orders_id = " . $order_details->fields['orders_id'] . "";
         if ($_POST['dload_include'] != 1) {
            $orders_discount_query = $orders_discount_query . " AND downloaded_ship='no'";
         }
         if ($_POST['status_target'] == 2) {
            $orders_discount_query = $orders_discount_query . " AND o.orders_status = '" . $_POST['order_status'] . "'";
         }
         if ($_POST['start_date'] != '' && $_POST['end_date'] != '') {
            $orders_discount_query = $orders_discount_query . " AND date_purchased BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
//$orders_discount_query = $orders_discount_query . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
         }
         $orders_discount_query = $orders_discount_query . " ORDER BY orders_id ASC";

         $orders_discount = $db->Execute($orders_discount_query);

         $num_rows = $orders_discount->RecordCount();
         if ($num_rows > 0) { // if records were found
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $orders_discount->fields['value'] . $FIELDEND; //add discount amt to export string
         } else { // add a BLANK field to the export file for "consistancy"
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $FIELDEND; // add blank space for filler
         } // end if
      } // End if for determining if order discount was selected to export.
*/
//*********Add Payment Method if selected***************/
// BMH copy to end     if ($_POST['payment_method'] == 1) { $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['payment_method'] . $FIELDEND; };
//******************************************************/

    if (isset($_POST['orders_status_export']) == 1)     // BMH isset
    {     // if order status was selected, then run the query to pull the data for adding it to the export string.
        // Run a query to pull the Order Status if present
    $orders_status_query = "SELECT orders_status_name
        FROM (" . TABLE_ORDERS_STATUS . ")
        WHERE orders_status_id=" . $order_details->fields['orders_status'] . "";
         $orders_status = $db->Execute($orders_status_query);

         $num_rows = $orders_status->RecordCount();
         if ($num_rows > 0)
         { // if records were found
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $orders_status->fields['orders_status_name'] . $FIELDEND; //add discount amt to export string
         } else
         { // add a BLANK field to the export file for "consistency"
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $FIELDEND; // add blank space for filler
         } // end if
      } // End if for determining if order discount was selected to export.

//*************bof ISO Country Codes********************//
      if (isset($_POST['iso_country2_code']) == 1) { // if iso country 2 was selected, then add it to the export string.
         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['countries_iso_code_2'] . $FIELDEND; //add ISO country code to export string
      }
      if (isset($_POST['iso_country3_code']) == 1) { // BMH isset //if iso country 3 was selected, then add it to the export string.
         $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['countries_iso_code_3'] . $FIELDEND; //add ISO country code to export string
      }
//*************eof ISO Country Codes********************//
//*************bof State Abbr Codes********************//
      /*
      if ($_POST['abbr_state_code'] == 1) { // if state abbr code was selected, then add it to the export string.
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['zone_code'] . $FIELDEND; //add State Abbrv. to export string
      }
      */
//*************eof State Abbr Codes********************//
// bof comments
        if (isset($_POST['order_comments']) == 1)
        {
            if ($_POST['filelayout'] == 2)
            { // 1 Product Per row RADIO
                // BMH MySQLD added extra fields to avoid group by error
                $orders_comments_query="SELECT *  FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = " . $order_details->fields['orders_id'] . "
                GROUP BY orders_id, orders_status_id, orders_status_history_id, date_added, customer_notified, comments, updated_by
                ORDER BY orders_status_history_id ASC";
            $orders_comments = $db->Execute($orders_comments_query);
            $str_safequotes = str_replace('"', "'", $orders_comments->fields['comments']); // replace quotes with single quotes if present
            //$str_safequotes = $str_safequotes . str_replace(","," ",$str_safequotes); // replace commas with blank space if present
            // dhc 16-Nov-2007 array("\r\n","\r","\n")
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . str_replace(array("\r\n", "\r", "\n"), " ", $str_safequotes) . $FIELDEND; // Remove any line breaks in first comment and print to export string
         } else {
            $str_safequotes = str_replace('"', "'", $order_details->fields['comments']); // replace quotes with single quotes if present
            //$str_safequotes = $str_safequotes . str_replace(","," ",$str_safequotes); // replace commas with blank space if present
            // dhc 16-Nov-2007 array("\r\n","\r","\n")
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . str_replace(array("\r\n", "\r", "\n"), " ", $str_safequotes) . $FIELDEND; // Remove any line breaks in first comment and print to export string
         }
      }
// eof comments
      /***********************************************************************************************************************************/
    if ($_POST['date_purchased'] == 1)
        { $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['date_purchased'] . $FIELDEND; };

    if (isset($_POST['product_details']) == 1)    // BMH isset
      { // Order details should be added to the export string.
        if (isset($_POST['filelayout']) == 2)  // BMH isset
            { // 1 PPR RADIO
            // bmh CHANGE ORDER
            if (!defined($linevalue)) $linevalue = 0; // BMH calc line cost
            if (!isset($linetax)) {$linetax = 0;} // BMH calc line tax

            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['products_quantity'] . $FIELDEND;
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['products_model'] . $FIELDEND;
            //$str_safequotes = str_replace('"', "'", $orders_comments->fields['comments']); // replace quotes with single quotes if present
            //$str_safequotes = $str_safequotes . str_replace(","," ",$str_safequotes); // replace commas with blank space if present
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . (str_replace('"', " ",$order_details->fields['products_name'])) . $FIELDEND; // replace quotes with space if present
            //$str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['products_name'] . $FIELDEND;
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['final_price'] . $FIELDEND . $FIELDSEPARATOR;
            // BMH
            $linevalue = $order_details->fields['products_quantity'] * $order_details->fields['final_price'];
            $linetax = $order_details->fields['products_tax']/100 * $order_details->fields['final_price'];
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $linevalue . $FIELDEND;
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $linetax .  $FIELDEND;
            // BMH
            $product_attributes_rows = "SELECT Count(*) as num_rows
                FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . "
                WHERE orders_id = " . $order_details->fields['orders_id'] . "
                AND orders_products_id = " . $order_details->fields['orders_products_id'] . "";
            $attributes_query_rows = $db->Execute($product_attributes_rows);
            $num_rows = $attributes_query_rows->fields['num_rows'];

            If ($num_rows > 0)
            {
               $product_attributes_query = "SELECT *
                    FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . "
                    WHERE orders_id = " . $order_details->fields['orders_id'] . "
                    AND orders_products_id = " . $order_details->fields['orders_products_id'] . "";
               $attributes_query_results = $db->Execute($product_attributes_query);
               $str_export .= $FIELDSTART;
               for ($i = 0, $n = $num_rows; $i < $n; $i++)
               {
                  //dhc
                  $str_safequotes = str_replace('"', "'", $attributes_query_results->fields['products_options_values']);
                  $str_export .= $attributes_query_results->fields['products_options'] . ': ' . str_replace(array("\r\n", "\r", "\n"), " ", $str_safequotes) . $ATTRIBSEPARATOR;
                  $attributes_query_results->MoveNext();
               }
               $str_export .= $FIELDEND;
            }

         } else
            { // 1 OPR default

            /**************the following exports 1 OPR w/ attributes) ****************/
            $oID = zen_db_prepare_input($order_details->fields['orders_id']);
            $oIDME = $order_details->fields['orders_id'];
            $order = new order($oID);
            if (!defined($linevalue)) $linevalue = 0; // BMH calc line cost
            if (!defined($linetax)) $linetax = 0; // BMH calc line tax

            for ($i = 0, $n = sizeof($order->products); $i < $n; $i++)
            {
               $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order->products[$i]['qty'] . $FIELDEND;
                $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order->products[$i]['model'] . $FIELDEND . $FIELDSEPARATOR;
                $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order->products[$i]['name'] . $FIELDEND;
                // BMH
               $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order->products[$i]['final_price'] . $FIELDEND;
                // BMH
                //echo " 2qty=" .qty;
                $linevalue = $order->products[$i]['qty'] * $order->products[$i]['final_price'];
                    $str_export .= $FIELDSEPARATOR . $FIELDSTART . $linevalue . $FIELDEND;
                    $str_export .= $FIELDSEPARATOR . $FIELDSTART . $linetax . $FIELDEND;  // BMH leave a space for it
                // BMH
                if (isset($order->products[$i]['attributes']) && (($k = sizeof($order->products[$i]['attributes'])) > 0))
                {
                  $str_export .= $FIELDSTART;
                  for ($j = 0; $j < $k; $j++)
                  {
                    //erl
                    //$str_export .= $order->products[$i]['attributes'][$j]['option'] . ': ' . nl2br($order->products[$i]['attributes'][$j]['value']) . $ATTRIBSEPARATOR;
                     //dhc
                     $str_safequotes = str_replace('"', "'", $order->products[$i]['attributes'][$j]['value']);
                     $str_export .= $order->products[$i]['attributes'][$j]['option'] . ': ' . str_replace(array("\r\n", "\r", "\n"), " ", $str_safequotes) . $ATTRIBSEPARATOR;
                  }
                  $str_export .= $FIELDEND;
                  }
            }
            /*************************************************************************/
         } // End if for determining type of export

      } // End if to determine if the order details should be added to the export string.
    // BMH extend the export string XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX



/*    if (isset($_POST['order_tax']) == 1) {
        $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['order_tax'] . $FIELDEND;
        };  // BMH isset
*/

        // bof sub-totals
        if (isset($_POST['order_subtotal']) == 1)   // BMH isset
        {

            $orders_subtotal_query = "SELECT o.orders_id, customers_email_address, delivery_name, delivery_company,
                delivery_street_address, delivery_suburb, delivery_city, delivery_postcode, delivery_state, delivery_country,
                shipping_method, customers_telephone, order_total, products_model, products_name, products_price, final_price,
                products_quantity, date_purchased, ot.value, orders_products_id, order_tax
                FROM (". TABLE_ORDERS ." o LEFT JOIN ". TABLE_ORDERS_PRODUCTS ." op ON o.orders_id = op.orders_id), ". TABLE_ORDERS_TOTAL ." ot
                WHERE o.orders_id = ot.orders_id
                AND ot.class = 'ot_subtotal'
                AND ot.orders_id = " . $order_details->fields['orders_id'] . "";
            if ($_POST['dload_include'] != 1)
            {
                $orders_subtotal_query = $orders_subtotal_query . " AND downloaded_ship='no'";
            }
            if ($_POST['status_target'] == 2)
            {
                $orders_subtotal_query = $orders_subtotal_query . " AND o.orders_status = '" . $_POST['order_status'] . "'";
            }
            if ($_POST['start_date'] != '' && $_POST['end_date'] != '')
                {
                    $orders_subtotal_query = $orders_subtotal_query . " AND date_purchased BETWEEN '". $start_date ."' AND '". $end_date ."'";
                    //$orders_subtotal_query = $orders_subtotal_query . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
                }
            $orders_subtotal_query = $orders_subtotal_query . " ORDER BY orders_id ASC";

            $orders_subtotal = $db->Execute($orders_subtotal_query);

            //$recordcount = mysql_query($orders_subtotal_query);
            $num_rows = $orders_subtotal->RecordCount();
            //if( !isset($orders_subtotal->fields['o.orders_id']) ) {
            //if (mysql_num_rows($result) > 0) { // if records were found
            //$num_rows = mysql_num_rows($orders_subtotal_query);
            if ( $num_rows > 0)
            {
                $str_export .= $FIELDSEPARATOR . $FIELDSTART . $orders_subtotal->fields['value'] . $FIELDEND; //add subtotal amt to export string
            } else
            { // add a BLANK field to the export file for "consistancy"
                $str_export .= $FIELDSEPARATOR . $FIELDSTART . $FIELDEND; // add blank space for filler
            } // end if


        } // eof sub-totals

    if (isset($_POST['shiptotal']) == 1) {
        $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['value'] . $FIELDEND;
        };   // BMH isset


        if (isset($_POST['order_discount']) == 1)   // BMH isset
        { // if order discount was selected, then run the query to pull the data for adding it to the export string.
            // Run a query to pull the Order Discount total if present ; no discount on order-product so remove from inner join not all discounts are negaitive numbers for force by -ABS
           $orders_discount_query = "SELECT DISTINCT o.orders_id,  sum(-ABS(round(ot.value,2))) AS value
                FROM (". TABLE_ORDERS ." o LEFT JOIN  ". TABLE_ORDERS_TOTAL ." ot ON o.orders_id = ot.orders_id)
                WHERE o.orders_id = ot.orders_id
                AND ot.class IN  ('ot_coupon' , 'ot_custom', 'ot_group_pricing', 'ot_payment_type', 'ot_paymentmodulefee')
                AND ot.orders_id = " . $order_details->fields['orders_id'] . ""; /* BMH changed AND ot.class = 'ot_coupon' TO include other discounts
                                                                                    AND ot.class IN  ('ot_coupon' , 'ot_custom', 'ot_group_pricing',
                                                                                    'ot_payment_type' , 'ot_paymentmodule) */
            if ($_POST['dload_include'] != 1)
            {
                $orders_discount_query = $orders_discount_query . " AND downloaded_ship='no'";
            }
            if ($_POST['status_target'] == 2)
            {
                $orders_discount_query = $orders_discount_query . " AND o.orders_status = '" . $_POST['order_status'] . "'";
            }
            if ($_POST['start_date'] != '' && $_POST['end_date'] != '')
            {
                $orders_discount_query = $orders_discount_query . " AND date_purchased BETWEEN '". $start_date ."' AND '". $end_date ."'";
                //$orders_discount_query = $orders_discount_query . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
            }
            $orders_discount_query = $orders_discount_query . " ORDER BY orders_id ASC";

            $orders_discount = $db->Execute($orders_discount_query);

            //$recordcount = mysql_query($orders_discount_query);
            $num_rows = $orders_discount->RecordCount();
            if ($num_rows > 0)
            { // if records were found
                $str_export .= $FIELDSEPARATOR . $FIELDSTART .  $orders_discount->fields['value'] . $FIELDEND; //add discount amt to export string // BMH add negative symbol
            } else
            { // add a BLANK field to the export file for "consistancy"
                $str_export .= $FIELDSEPARATOR . $FIELDSTART . $FIELDEND; // add blank space for filler
            } // end if
        } // End if for determining if order discount was selected to export.

        if (isset($_POST['order_total']) == 1)  {    // BMH isset
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['order_total'] . $FIELDEND;
        };
        if (isset($_POST['order_tax']) == 1) {
            $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['order_tax'] . $FIELDEND;
        };  // BMH isset

    //*********Add Payment Method if selected***************/
    if (isset($_POST['payment_method']) == 1)   // BMH isset
    { $str_export .= $FIELDSEPARATOR . $FIELDSTART . $order_details->fields['payment_method'] . $FIELDEND;
    };


/* ZZZZZZZZZZZZZZZZZZZZZZZZZ */

/* print the export string  *********************** */
      $str_export = $str_export . "\n";
      $str_full_export .= $str_export;
      //echo $str_export . "<br />\n"; //dhc
      //If order status is to be updated, then update it for this order now.
      if (isset($_POST['status_setting']) == 1)     // BMH isset
      { //Update the order status upon export
         $db->execute('UPDATE ' . TABLE_ORDERS . ' SET orders_status="' . $_POST['order_status_setting'] .
            '" WHERE orders_id="' . $order_details->fields['orders_id'] . '"');
      }
      //********************************************************************
      $order_details->MoveNext();
   } // End Outer While statement to loop through all non downloaded orders.

   /**************************************Process the export file**************************************************/
   if ($save_to_file_checked == 1) { // saving to a file for email attachement, so write and ready else do regular output (prompt for download)
      // Do not set headers becuase we are going to email the file to the supplier.
      //open output file for writing
      $f = fopen(DIR_FS_EMAIL_EXPORT . $file, 'w+');
      //fwrite($f,$str_export);
// swguy
      if ($_POST['include_header_row'] == 1) { //Include the Header Row In The Export Else Leave out
         fwrite($f, $str_header);
      }
// End swguy
      fwrite($f, $str_full_export);
      fclose($f);
      unset($f);
      //Email File to Supplier
      // send the email
      zen_mail('Supplier Name', $to_email_address, $email_subject, EMAIL_EXPORT_BODY, STORE_NAME, EMAIL_FROM, $html_msg, 'default', DIR_FS_EMAIL_EXPORT . $file);
      //Set Success Message
      $success_message = "<span style='color:#ff0000;font-weight:bold;font-size:14px;'>File processed successfully!</span>";
      /***************************Begin Update records in db if selected by user***************************/
      if ($_POST['export_test'] != 1) { //Not testing so update
         //Original Code
         //$db->execute('UPDATE '. TABLE_ORDERS .' SET downloaded_ship="yes" WHERE downloaded_ship="no"');
         $orders_update_query = "UPDATE " . TABLE_ORDERS . " SET downloaded_ship='yes' WHERE downloaded_ship='no'";
         if ($_POST['start_date'] != '' && $_POST['end_date'] != '') {
            $orders_update_query = $orders_update_query . " AND date_purchased BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
            //$orders_discount_query = $orders_discount_query . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
         }
         $db->Execute($orders_update_query);
      }
      //Moved the below to line 383 to update each order as it is rotated through.
      /*if ($_POST['status_setting'] == 1) { //Update the order status upon export
       $db->execute('UPDATE '. TABLE_ORDERS .' SET orders_status="'. $_POST['order_status_setting'] . '" WHERE orders_status!="' . $_POST['order_status_setting'] . '"');
     }*/
      /***************************End Update records in db if selected by user***************************/
   } else { // This export should be in the format of a file download so set page headers.
      Header('Content-type: application/csv');
      Header("Content-disposition: attachment; filename=" . $file . "");
      if (isset($_POST['include_header_row']) == 1) { //Include the Header Row In The Export Else Leave out  // BMH isset
         echo $str_header;
      }
      //echo $str_export;
      echo $str_full_export;
      /***************************Begin Update records in db if selected by user***************************/
      if ($_POST['export_test'] != 1) { //Not testing so update
         //$db->execute('UPDATE '. TABLE_ORDERS .' SET downloaded_ship="yes" WHERE downloaded_ship="no"');
         $orders_update_query = "UPDATE " . TABLE_ORDERS . " SET downloaded_ship='yes' WHERE downloaded_ship='no'";
         if ($_POST['start_date'] != '' && $_POST['end_date'] != '') {
            $orders_update_query = $orders_update_query . " AND date_purchased BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
            //$orders_discount_query = $orders_discount_query . " AND date_purchased >= '". $start_date ."' AND date_purchased <= '". $end_date ."'";
         }
         $db->Execute($orders_update_query);
      }
      //Moved the below to line 383 to update each order as it is rotated through.
      /*if ($_POST['status_setting'] == 1) { //Update the order status upon export
       $db->execute('UPDATE '. TABLE_ORDERS .' SET orders_status="'. $_POST['order_status_setting'] . '" WHERE orders_status!="' . $_POST['order_status_setting'] . '"');
     }*/
      /***************************End Update records in db if selected by user***************************/
      exit;
   }
   /*******************************************************************************************************/
}

// build arrays for dropdowns in order status search menu
    $status_array = array();
    $status_table = array();
    $orders_status = $db->Execute("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . "
                                   where language_id = '" . (int)$_SESSION['languages_id'] . "'
                                   order by orders_status_id asc");
    while (!$orders_status->EOF) {
       $status_array[] = array('id' => $orders_status->fields['orders_status_id'],
          'text' => $orders_status->fields['orders_status_name'] . ' [' . $orders_status->fields['orders_status_id'] . ']');
       $status_table[$orders_status->fields['orders_status_id']] = $orders_status->fields['orders_status_name'];
       $orders_status->MoveNext();
    }
//

?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
    <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <script language="javascript" src="includes/menu.js"></script>
    <script language="javascript" src="includes/general.js"></script>
    <link rel="stylesheet" type="text/css" href="includes/javascript/spiffyCal/spiffyCal_v2_1.css">
    <script language="javascript" src="includes/javascript/spiffyCal/spiffyCal_v2_1.js"></script>
    <script type="text/javascript">
        <!--
        function init() {
            cssjsmenu('navbar');
            if (document.getElementById) {
                var kill = document.getElementById('hoverJS');
                kill.disabled = true;
            }
        }

        // -->
    </script>
    <style>
        #zentips { color: #000000; }
        #zentips H2 { font-size: 14;  font-family: Verdana;  margin-bottom: 5px;  color: #336600; border-bottom: 1px solid #336600; }
        #zentips p { margin-top: 0px; margin-bottom: 0px; }
    </style>
    <!-- bof Check / Uncheck all Script -->
    <script type="text/javascript">
        checked = false;

        function checkedAll(download_csv) {
            var aa = document.getElementById('download_csv');
            if (checked == false) {
                checked = true
            } else {
                checked = false
            }
            for (var i = 0; i < aa.elements.length; i++) {
                aa.elements[i].checked = checked;
            }
        }
    </script>
    <!-- eof Check / Uncheck all Script -->


</head>
<body onLoad="init()">
<div id="spiffycalendar" class="text"></div>
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<script language="javascript">
    var StartDate = new ctlSpiffyCalendarBox("StartDate", "download_csv", "start_date", "btnDate1", "", scBTNMODE_CALBTN);
    var EndDate = new ctlSpiffyCalendarBox("EndDate", "download_csv", "end_date", "btnDate2", "", scBTNMODE_CALBTN);
</script>
<table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>
        <td width="100%" valign="top">
            <table border="0" width="100%" cellspacing="0" cellpadding="2">
                <tr>
                    <td>

                        <table border="0" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td valign="top" style="padding-right:7px;">
                                    <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td valign="top"><span class="pageHeading"><?php echo HEADING_SHIPPING_EXPORT_TITLE; ?></span>
                                                <td align="right">ESIVERSION: <?php // BMH
                                                echo ESIVERSION; ?> </td>
                                            </td>
                                            <td align="right"><?php echo zen_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT); ?></td>
                                        </tr>
                                        <?php if ($success_message != "") { ?>
                                            <tr>
                                                <td colspan=2 valign="top"><?php echo $success_message; ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan=2 valign="top">&nbsp;</td>
                                            </tr>
                                        <?php } //end if Success Message ?>

                                        <tr> <!-- BMH included style  export_instr -->
                                            <td class="export_instr" colspan=2 valign="top">
                                                <?php echo TEXT_SHIPPING_EXPORT_INSTRUCTIONS; ?>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                        <tr>
                                            <td valign="top">

                                            <table border="0">
                                                <tr>
                                                    <td valign="top">

                                                        <table border="0" width="100%" cellspacing="2" cellpadding="0">
                                                            <tr class="dataTableHeadingRow">
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Order<br>ID</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Email</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Customer<br>Name</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Company</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Delivery<br>Street</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Delivery<br>Suburb</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Delivery<br>City</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Post<br>Code</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">State</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">Country</td>
                                                                <td class="dataTableHeadingContent" align="center" valign="top">&nbsp;</td>
                                                            </tr>
                                                        <?php
                                                        $query = "SELECT o.orders_id, customers_email_address, delivery_name, delivery_company,
                                                            delivery_street_address, delivery_suburb, delivery_city, delivery_postcode,
                                                            delivery_state, delivery_country, shipping_method, customers_telephone,
                                                            order_total, date_purchased
                                                            FROM " . TABLE_ORDERS . " o
                                                            WHERE downloaded_ship='no'
                                                            ORDER BY orders_id ASC";

                                                        $query = strtolower($query);

                                                        $order_pages = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS_REPORTS, $query, $rows);
                                                        $order = $db->execute($query);
// BMH DEBUG echo '<br> ln864 shipping_export $order= '; var_dump($order);        // BMH ** DEBUG

                                                        while (!$order->EOF) {
                                                            list($order_id, $cust_email, $delivery_name, $delivery_company, $delivery_street,
                                                                $delivery_suburb, $delivery_city, $delivery_postcode, $delivery_state,
                                                                $delivery_country, $shipping_method, $customers_telephone, $order_total) //, // BMH
                                                                //$product_model, $product_name, $product_price, $product_qty, $date_purchased, // BMH
                                                                //$comments) // BMH
                                                                = array_values($order->fields);   // BMH added isset
                                                        ?>
                                                        <!--<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="window.open('<?php echo zen_href_link(FILENAME_ORDERS, 'page=1&oID=' . $order_id . '&action=edit', 'NONSSL'); ?>')">-->
                                                        <!-- BMH   <tr class="dataTableRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" >
                                                        -->
                                                        <tr class="dataTableRow"  >
                                                            <td class="dataTableContent" align="right"><?php echo $order_id; ?>&nbsp;&nbsp;</td>
                                                            <td class="dataTableContent"><?php echo $cust_email; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_name; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_company; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_street; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_suburb; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_city; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_postcode; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_state; ?></td>
                                                            <td class="dataTableContent"><?php echo $delivery_country; ?></td>
                                                            <td class="dataTableContent"> <a href="<?php echo zen_href_link(FILENAME_ORDERS, 'page=1&oID=' . $order_id . '&action=edit', 'NONSSL'); ?>">
                                                                <img  src="images/icons/preview.gif" border="0" ALT="Preview Order Details"></a></td>
                                                        </tr>
                                                        <?php

                                                        $order->MoveNext();
                                                   }

                                                        if (!isset($order_id)) {
                                                        ?>
                                                        <tr class="dataTableRow">
                                                            <td class="dataTableContent" align="center" colspan="30"><b>No new orders were found!</b></td>
                                                       </tr>
                                                        <?php } ?>
                                                        <?php
                                                        $SUBMIT_BUTTON = "<input style=\"font-weight: bold\" name=\"download_csv\" type=\"submit\" value=\"Export to Excel Spreadsheet\" />";
                                                        ?>
                                                    </td>
                                                    </tr>
                                                </table>

                                            </td>
                                            <td width="25%" valign="top">

                                                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                                    <tr class="infoBoxHeading">
                                                        <td class="infoBoxHeading">
                                                            <b><?php echo HEADING_ADDITIONAL_FIELDS_TITLE; ?></b></td>
                                                    </tr>
                                                </table>
                                                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                                    <tr>
                                                        <td class="infoBoxContent"><?php echo TEXT_RUNIN_TEST; ?><br>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="infoBoxContent">
                                                            <form name="download_csv" id="download_csv" method="post">
                                                                <input type='button' name='checkall' value="Check / Uncheck All" onclick='checkedAll(download_csv);'><br/><br/>
                                                               <?php echo zen_draw_checkbox_field('export_test', '1', $export_test_checked . 'checked'); ?> &nbsp;<?php echo TEXT_RUNIN_TEST_FIELD; ?><br/>
                                                               <?php echo zen_draw_checkbox_field('split_name', '1', $export_split_checked . 'checked'); ?> &nbsp;<?php echo TEXT_SPLIT_NAME_FIELD; ?><br/>
                                                                    <!-- Order Status: <?php echo zen_draw_pull_down_menu('date_status', $status_array, $_POST['date_status'] ?? '', 'id="date_status"'); ?>-->
                                                                <?php echo zen_draw_checkbox_field('include_header_row', '1', $export_header_row_checked . 'checked'); ?>    &nbsp;<?php echo TEXT_HEADER_ROW_FIELD; ?><br/><br> <!-- BMH -->
                                                               <?php echo TEXT_EMAIL_EXPORT_FORMAT; ?><?php echo zen_draw_pull_down_menu('format', $available_export_formats, $format); ?>

                                                                <hr/>
                                                                <table border="0" cellspacing="0" cellpadding="2">
                                                                    <tr>
                                                                        <td><strong>Automatic Email Options</strong>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>
                                                                            <input type="checkbox" name="savetofile"   value="0">Save file to server and  automatically email to supplier.<br/>
                                                                            (if not saving to server you will be promoted to download the file to your computer.)
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Suppliers Email Address:&nbsp;<input  type="text"   name="auto_email_supplier"  value="<?php echo EMAIL_EXPORT_ADDRESS ?>"> </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Email Subject Line:&nbsp;<input type="text"  name="auto_email_subject"  value="<?php echo EMAIL_EXPORT_SUBJECT ?>">  </td>
                                                                    </tr>
                                                                </table>
                                                                <hr/>
                                                                <table border="0" cellspacing="0" cellpadding="2">
                                                                    <tr>
                                                                        <td><strong>Update Order Status on Export</strong><br/>(If this is set then  the order status will update to what you  select here after a successful export.)                                                                         </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>
                                                                           <?php echo zen_draw_checkbox_field('status_setting' , '', $order_status_setting_checked); ?>
                                                                           <?php //echo zen_draw_checkbox_field('status_setting' ?? '' , ' ', $order_status_setting_checked); // BMH ?>
                                                                           <!-- BMH //  <?php //echo zen_draw_checkbox_field('status_setting' ?? '' , '1', $order_status_setting_checked); ?>  ;-->
                                                                           Set Order Status After Export to&nbsp;
                                                                            <!--</td>
                                                                          </tr>
                                                                          <tr>
                                                                            <td> --><?php echo zen_draw_pull_down_menu('order_status_setting' ?? '', $status_array, isset($_POST['order_status_setting']) , '1','id="order_status_setting"'); ?></td> <!-- BMH isset($_POST['order_status_setting']) -->
                                                                    </tr>
                                                                </table>
                                                                <hr/>
                                                                <table border="0" cellspacing="0" cellpadding="2">
                                                                    <tr>
                                                                        <td><strong>Order Status Export Options</strong> </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>
                                                                            <input type="radio" name="status_target"  value="1" checked>Any Order Status<br/>
                                                                            <input type="radio" name="status_target"  value="2">Assigned Order Status (select below)
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><?php echo zen_draw_pull_down_menu('order_status' ?? '', $status_array, $_POST['order_status'] ?? '', 'id="order_status"'); ?></td>
                                                                        <!-- <td><?php //echo zen_draw_pull_down_menu('order_status', $status_array, $_POST['order_status'], 'id="order_status"'); ?></td>  BMH // -->
                                                                    </tr>
                                                                </table>
                                                                <hr>
                                                                <table border="0" cellspacing="0" cellpadding="2" width="100%" id="tbl_date_custom">
                                                                    <tr>
                                                                        <td colspan="2">  <strong><?php echo HEADING_PREVIOUS_EXPORTS_TITLE; ?></strong><br>
                                                                           <?php echo TEXT_PREVIOUS_EXPORTS; ?>
                                                                            <br></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><?php echo zen_draw_checkbox_field('dload_include', '1', $dload_include_checked . 'checked'); ?>
                                                                            &nbsp;<?php echo TEXT_PREVIOUS_EXPORTS_FIELD; ?> <br/><br/>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                                <hr>
                                                                <?php echo TEXT_FILE_LAYOUT; ?><br/>
                                                                <?php echo zen_draw_radio_field('filelayout', '1') ?> &nbsp;<?php echo TEXT_FILE_LAYOUT_OPR_FIELD; ?><br/>
                                                                <?php echo zen_draw_radio_field('filelayout', '2' , 'checked') ?> &nbsp;<?php echo TEXT_FILE_LAYOUT_PPR_FIELD; ?><br/>
                                                                <hr>
                                                                <?php echo TEXT_ADDITIONAL_FIELDS; ?><br />
                                                                <!-- BMH rearranged field order -->
                                                                <?php echo zen_draw_checkbox_field('shiptotal', '1', $shipping_total_checked . 'checked');?>&nbsp;<?php echo TEXT_SHIPPING_TOTAL_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('order_total', '1', $order_total_checked . 'checked');?>&nbsp;<?php echo TEXT_ORDER_TOTAL_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('date_purchased', '1', $date_purchased_checked . 'checked');?>&nbsp;<?php echo TEXT_ORDER_DATE_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('order_tax', '1', $order_tax_checked . 'checked');?>&nbsp;<?php echo TEXT_TAX_AMOUNT_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('order_subtotal', '1', $order_subtotal_checked . 'checked');?>&nbsp;<?php echo TEXT_SUBTOTAL_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('order_discount', '1', $order_discount_checked . 'checked');?>&nbsp;<?php echo TEXT_DISCOUNT_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('payment_method', '1', $order_pmethod_checked . 'checked');?>&nbsp;<?php echo TEXT_PAYMENT_METHOD_FIELD; ?><br />
                                                                <hr />
                                                                <?php echo zen_draw_checkbox_field('shipmethod', '1', $shipping_method_checked);?>&nbsp;<?php echo TEXT_SHIPPING_METHOD_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('order_comments', '1', $order_comments_checked);?>&nbsp;<?php echo TEXT_ORDER_COMMENTS_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('customers_telephone', '1', $phone_number_checked);?>&nbsp;<?php echo TEXT_PHONE_NUMBER_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('orders_status_export', '1', $order_status_checked);?>&nbsp;<?php echo TEXT_ORDER_STATUS_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('iso_country2_code', '1', $iso_country2_code_checked);?>&nbsp;<?php echo TEXT_ISO_COUNTRY2_FIELD; ?><br />
                                                                <?php echo zen_draw_checkbox_field('iso_country3_code', '1', $iso_country3_code_checked);?>&nbsp;<?php echo TEXT_ISO_COUNTRY3_FIELD; ?><br />
                                                                <?php //if (ACCOUNT_STATE == 'true') {
                                                                //echo zen_draw_checkbox_field('abbr_state_code', '1', $abbr_state_code_checked);?> <!-- nbsp; --><?php //echo TEXT_STATE_ABBR_FIELD;
                                                                //}?> <!-- <br/> -->
                                                                <hr>
                                                                <?php echo zen_draw_checkbox_field('product_details', '1', $prod_details_checked . 'checked'); ?> &nbsp;<?php echo TEXT_PRODUCT_DETAILS_FIELD; ?>
                                                                    <span style="color: #ff0000"><strong>*</strong></span><br/>
                                                                <hr/>
                                                                <table border="0" cellspacing="0" cellpadding="2" width="100%" id="tbl_date_custom">
                                                                    <tr>
                                                                        <!--<td class="smallText" colspan="2">-->
                                                                        <td colspan="2"> <strong><?php echo HEADING_CUSTOM_DATE_TITLE; ?></strong><br>
                                                                          <?php echo TEXT_CUSTOM_DATE; ?> <br>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="main"><?php echo TEXT_SPIFFY_START_DATE_FIELD; ?>
                                                                            &nbsp;
                                                                        </td>
                                                                        <td class="main">
                                                                            <script language="javascript">StartDate.writeControl();
                                                                                StartDate.dateFormat = "yyyy-MM-dd";</script>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="main"><?php echo TEXT_SPIFFY_END_DATE_FIELD; ?>
                                                                            &nbsp;
                                                                        </td>
                                                                        <td class="main">
                                                                            <script language="javascript">EndDate.writeControl();
                                                                                EndDate.dateFormat = "yyyy-MM-dd";</script>
                                                                        </td>
                                                                    </tr>
                                                                </table>

                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center" class="infoBoxContent"><br>
                                                           <?php echo $SUBMIT_BUTTON; ?>
                                                        <!--  BMH moved 6 lines up -->
                                                        </form>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="infoBoxContent"></td>
                                                    </tr>
                                                </table>


                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3">
                                                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                                    <td class="smallText"
                                                        valign="top"><?php echo $order_pages->display_count($rows, MAX_DISPLAY_SEARCH_RESULTS_REPORTS, $_GET['page'], 'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> orders)'); ?></td>
                                                    <td class="smallText"
                                                        align="right"><?php echo $order_pages->display_links($rows, MAX_DISPLAY_SEARCH_RESULTS_REPORTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
                                                    </tr>
                                                    </td>
                                                    <td colspan="3">&nbsp;</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                       <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
