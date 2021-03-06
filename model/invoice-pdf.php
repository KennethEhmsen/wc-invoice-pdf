<?php
namespace WCInvoicePdf\Model;

include_once WCINVOICEPDF_PLUGIN_DIR . 'vendor/rospdf/pdf-php/src/Cezpdf.php';

class InvoicePdf { 
    /**
     * Used to build a pdf invoice using the WC_Order object
     * @param {WC_Order} $order - the woocommerce order object
     * @param {Array} $invoice-> list of extra data passed as array (E.g. invoice_number, created, due date, ...)
     */
    public function BuildInvoice($invoice, $isOffer = false, $stream = false){
        setlocale(LC_ALL, get_locale());
        
        $order = $invoice->Order();

        $formatStyle = \NumberFormatter::DECIMAL;
        $formatter = new \NumberFormatter(get_locale(), $formatStyle);
        $formatter->setPattern("#0.00 " . $order->get_currency());

        $items = $order->get_items();

        // if its first invoice, use shipping item as one-time fee
        if($invoice->isFirst)
            $items = array_merge($items, $order->get_items('shipping'));

        //error_log(print_r($items, true));

        $billing_info = str_replace('<br/>', "\n", $order->get_formatted_billing_address());

        if($isOffer) {
            $headlineText =  __('Offer', 'wc-invoice-pdf') . ' ' . $invoice->offer_number;
        } else {
            $headlineText =  __('Invoice', 'wc-invoice-pdf') . ' ' . $invoice->invoice_number;
        }

                    
        $pdf = new \Cezpdf('a4');
        $pdf->ezSetMargins(50,110,50,50);

        $mediaId = intval(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_logo']);
        if($mediaId > 0) {
            $mediaUrl = wp_get_attachment_url($mediaId);
            if($mediaUrl !== false) {
                $pdf->ezImage($mediaUrl, 0, 250, 'none', 'right');
            }
        }


        $all = $pdf->openObject();
        $pdf->saveState();
        $pdf->setStrokeColor(0, 0, 0, 1);
        $pdf->line(50, 100, 550, 100);

        $pdf->addTextWrap(50, 90, 8,\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block1']);
        $pdf->addTextWrap(250, 90, 8,\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block2'], 0);
        $pdf->addTextWrap(550, 90, 8,\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block3'], 0, 'right');

        $pdf->restoreState();
        $pdf->closeObject();

        $pdf->addObject($all, 'all');


        $pdf->ezSetDy(-60);

        $y = $pdf->y;

        $pdf->ezText(sprintf( \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_info'], strftime('%x',strtotime($invoice->created))) , 0, ['justification' => 'right']);

        if($order->get_date_paid() && !$isOffer) {
            $pdf->saveState();
            $pdf->setColor(1,0,0);
            $pdf->ezText(sprintf(__('Paid at', 'wc-invoice-pdf') . ' %s', strftime('%x',strtotime($order->get_date_paid())) ) , 0,['justification' => 'right']);
            $pdf->restoreState();
        } else {
            $pdf->ezText('');
        }


        $pdf->y = $y;

        $pdf->ezText("<strong>" . \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_addressline'] . "</strong>\n", 8);
        $pdf->line($pdf->ez['leftMargin'],$pdf->y + 5, 200, $pdf->y + 5);

        $pdf->ezText($billing_info, 10);
        $pdf->ezSetDy(-60);

        $pdf->ezText("$headlineText", 14);



        $cols = array('num' => __('No#', 'wc-invoice-pdf'), 'desc' => __('Description', 'wc-invoice-pdf'), 'qty' => __('Qty', 'wc-invoice-pdf'), 'price' => __('Unit Price', 'wc-invoice-pdf'), 'total' => __('Amount', 'wc-invoice-pdf'));
        $colOptions = [
            'num' => ['width' => 32],
            'desc' => [],
            'qty' => ['justification' => 'right', 'width' => 75],
            'price' => ['justification' => 'right', 'width' => 70],
            'total' => ['justification' => 'right', 'width' => 80],
        ];

        $data = [];

        $i = 1;
        $summary = 0;
        $summaryTax = 0;

	$fees = $order->get_fees();
        $items = array_merge($items, $fees);

        foreach($items as $v){
            $row = [];

            $product_name = $v['name'];
            //error_log(print_r($v,true));
            $product = null;
            // check if product id is available and fetch the ISPCONFIG tempalte ID
            if(!empty($v['product_id']))
                $product = wc_get_product($v['product_id']);
                
            if(!isset($v['qty'])) $v['qty'] = 1;
           
            if($product instanceof \WC_Product_Webspace) {
                // if its an ISPCONFIG Template product
                $current = new \DateTime($invoice->created);
                $next = clone $current;
                if($v['qty'] == 1) {
                    $next->add(new \DateInterval('P1M'));
                } else if($v['qty'] == 12) {
                    // overwrite the QTY to be 1 MONTH
                    $next->add(new \DateInterval('P12M'));
                }
                $qtyStr = number_format($v['qty'], 0, ',',' ') . ' ' . $product->get_price_suffix('', $v['qty']);
                //if(!$isOffer)
                    $product_name .= "\n<strong>Zeitraum: " . $current->format('d.m.Y')." - ".$next->format('d.m.Y') . '</strong>';
            } else if($product instanceof \WC_Product_Hour) {
                // check if product type is "hour" to output hours instead of Qty
                $qtyStr = number_format($v['qty'], 1, ',',' ');
                $qtyStr.= ' ' . $product->get_price_suffix('', $v['qty']);
			} else {
			    $qtyStr = number_format($v['qty'], 2, ',',' ');
			}

            $total = round($v['total'], 2);
            $unitprice = $total / intval($v['qty']);
            $tax = round($v['total_tax'], 2);


            $mdcontent = '';
            if($v instanceof \WC_Order_Item_Product) {
                $meta = $v->get_meta_data();
                if(!empty($meta)) {
                    $mdcontent.= implode('',array_map(function($m){ return "\n<strong>".$m->key.":</strong> ".$m->value."\n"; }, $meta));
                } 
            }

            $row['num'] = "$i";
            $row['desc'] = $product_name . "\n" . $mdcontent;
            $row['qty'] = $qtyStr;
            $row['price'] = $formatter->format($unitprice);
            $row['total'] = $formatter->format($total);

            $summary += $total;
            $summaryTax += $tax;

            $data[] = $row;
            $i++;
        }

        
        $pdf->ezSetDy(-30);

        $pdf->ezTable($data, $cols, '', ['width' => '500','splitRows' => 1,'gridlines' => EZ_GRIDLINE_HEADERONLY, 'cols' => $colOptions]);
    
        $colOptions = [
            ['justification' => 'right'],
            ['justification' => 'right']
        ];

        $summaryData = [
            [
                "<strong>".__('Summary', 'wc-invoice-pdf')."</strong>",
                "<strong>".$formatter->format($summary)."</strong>"
            ],
            [
                "<strong>+ 19% ".__('Tax', 'wc-invoice-pdf')."</strong>",
                "<strong>".$formatter->format($summaryTax) ."</strong>"
            ],
            [
                "<strong>".__('Total', 'wc-invoice-pdf')."</strong>",
                "<strong>".$formatter->format($summary + $summaryTax)."</strong>"
            ]
        ];

        $pdf->ezSetDy(-20);

        $pdf->ezTable($summaryData, null, '', ['width' => 200, 'gridlines' => 0, 'showHeadings' => 0,'shaded' => 0 ,'xPos' => 'right', 'xOrientation' => 'left', 'cols' => $colOptions ]);

        $pdf->ezSetDy(-20);
        $pdf->ezText("<strong>" .  \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_condition'] . "</strong>", 8, ['justification' => 'center']);

        if($stream) {
            $pdf->ezStream();
        } else {
            return $pdf->ezOutput();
        }
    }

    /**
     * Used to trigger on specified parameters
     */
    public function Trigger(){
        global $wpdb, $pagenow, $current_user;

        // skip invoice output when no invoice id is defined (and continue with the default page call)
        if(empty($_GET['invoice'])) return;

        $invoice = new Invoice( intval($_GET['invoice']) );
        if(!$invoice->ID) die("Invoice not found");

        // invoice has been defined but user does not have the cap to display it
        if(!current_user_can('ispconfig_invoice')) die("You are not allowed to view invoices: Cap 'ispconfig_invoice' not set");
        if(!current_user_can('manage_options') && $invoice->customer_id != $current_user->ID) die("You are not allowed to open this invoice (Customer: {$invoice->customer_id} / ID: {$invoice->ID})");
        
        if(isset($_GET['preview'])) {
            //$order = new WC_Order($res['wc_order_id']);
            
            echo $this->BuildInvoice($invoice, true,true);
        } else {
            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=".$invoice->invoice_number .'.pdf');

            echo $invoice->document;
        }
        die;
    }
}
