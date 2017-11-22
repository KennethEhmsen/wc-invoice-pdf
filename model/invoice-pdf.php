<?php
namespace WCInvoicePdf\Model;

include_once WCINVOICEPDF_PLUGIN_DIR . 'pdf-php/src/CpdfExtension.php';

use ROSPDF\Cpdf as Cpdf;
use ROSPDF\CpdfExtension as Cpdf_Extension;
use ROSPDF\CpdfLineStyle as Cpdf_LineStyle;
use ROSPDF\CpdfTable as Cpdf_Table;

class InvoicePdf { 
    /**
     * Used to build a pdf invoice using the WC_Order object
     * @param {WC_Order} $order - the woocommerce order object
     * @param {Array} $invoice-> list of extra data passed as array (E.g. invoice_number, created, due date, ...)
     */
    public function BuildInvoice($invoice, $isOffer = false, $stream = false){
        setlocale(LC_ALL, get_locale());
        $order = $invoice->Order();

        $items = $order->get_items();

        // if its first invoice, use shipping item as one-time fee
        if($invoice->isFirst)
            $items = array_merge($items, $order->get_items('shipping'));

        //error_log(print_r($items, true));

        $billing_info = str_replace('<br/>', "\n", $order->get_formatted_billing_address());
                    
        Cpdf::$DEBUGLEVEL = Cpdf::DEBUG_MSG_ERR;
        //Cpdf::$DEBUGLEVEL = Cpdf::DEBUG_ALL;
                
        $pdf = new Cpdf_Extension(Cpdf::$Layout['A4']);
        $pdf->Compression = 0;
        //$pdf->ImportPage(1);
        if($isOffer) {
            $headlineText =  __('Offer', 'wc-invoice-pdf') . ' ' . $invoice->offer_number;
        } else {
            $headlineText =  __('Invoice', 'wc-invoice-pdf') . ' ' . $invoice->invoice_number;
        }
            
        $pdf->Metadata->SetInfo('Title', sprintf(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_title'], $headlineText) );
        
        $ls = new Cpdf_LineStyle(1, 'butt', 'miter');
        
        // Logo
        $mediaId = intval(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_logo']);
        if($mediaId > 0) {
            $mediaUrl = wp_get_attachment_url($mediaId);
            if($mediaUrl !== false) {
                $logo = $pdf->NewAppearance();
                $logo->AddImage('right',-30, $mediaUrl, 280);
            }
        }
                
        // billing info
        $billing_text = $pdf->NewAppearance(['uy'=> 650, 'addlx' => 20, 'ly' => 520, 'ux'=> 300]);
        if(!empty(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_addressline'])) {
            $billing_text->SetFont('Helvetica', 8);
            $billing_text->AddText( "<strong>" . \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_addressline'] . "</strong>\n");
            $billing_text->AddLine(0, -11, 177, 0, $ls);
        }
        
        $billing_text->SetFont('Helvetica', 10);
        $billing_text->AddText($billing_info);
        
        // Rechnung info
        $billing_text = $pdf->NewAppearance(['uy'=> 650, 'lx' => 400, 'ly' => 520, 'addux' => -20]);
        $billing_text->SetFont('Helvetica', 10);
        $billing_text->AddText(sprintf( \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_info'], strftime('%x',strtotime($invoice->created))) );
        
        if($order->get_date_paid() && !$isOffer) {
            $billing_text->AddColor(1,0,0);
            $billing_text->SetFont('Helvetica', 12);
            $billing_text->AddText("\n" . sprintf(__('Paid at', 'wc-invoice-pdf') . ' %s', strftime('%x',strtotime($order->get_date_paid())) ) );
        }

        // Zahlungsinfo und AGB
        $payment_text = $pdf->NewAppearance(['uy'=> 130, 'addlx' => 20, 'addux' => -20]);
        $payment_text->SetFont('Helvetica', 8);
        $payment_text->AddText("<strong>" .  \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_condition'] . "</strong>",0, 'center');

        // Firmeninfo (1)
        $billing_text = $pdf->NewAppearance(['uy'=> 100, 'addlx' => 20, 'ux' => 200]);
        $billing_text->SetFont('Helvetica', 8);
        $billing_text->AddText( \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block1'] );
        // firmeninfo (2)
        $billing_text = $pdf->NewAppearance(['uy'=> 100, 'lx' => 200, 'ux' => 370]);
        $billing_text->SetFont('Helvetica', 8);
        $billing_text->AddText( \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block2']);
        // firmeninfo (3)
        $billing_text = $pdf->NewAppearance(['uy'=> 100, 'lx' => 430, 'addux' => -20]);
        $billing_text->SetFont('Helvetica', 8);
        $billing_text->AddText(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block3']);
        
        // Rechnungsnummer
        $text = $pdf->NewText(['uy' => 510, 'ly' => 490, 'addlx' => 20, 'addux' => -20]);
        $text->SetFont('Helvetica', 15);
        $text->AddText("$headlineText");
        
        $table = $pdf->NewTable(array('uy'=>480, 'addlx' => 20, 'addux' => -20,'ly' => 120), 4, null, $ls, Cpdf_Table::DRAWLINE_HEADERROW);
        
        $table->SetColumnWidths(30,240);
        $table->Fit = true;

        $table->AddCell("<strong>". __('No#', 'wc-invoice-pdf') ."</strong>");
        $table->AddCell("<strong>". __('Description', 'wc-invoice-pdf') ."</strong>");
        $table->AddCell("<strong>". __('Qty', 'wc-invoice-pdf') ."</strong>", 'right');
        $table->AddCell("<strong>". __('Net', 'wc-invoice-pdf') ."</strong>", 'right');

        $i = 1;
        $summary = 0;
        $summaryTax = 0;
        
        // add fees and possible discounts (if value is negative)
        $fees = $order->get_fees();
        $items = array_merge($items, $fees);
        
        foreach($items as $v){
            $product = null;
            // check if product id is available and fetch the ISPCONFIG tempalte ID
            if(!empty($v['product_id']))
                $product = wc_get_product($v['product_id']);
                
            if(!isset($v['qty'])) $v['qty'] = 1;

            if($product instanceof WC_Product_Webspace) {
                // if its an ISPCONFIG Template product
                $current = new DateTime($invoice->created);
                $next = clone $current;
                if($v['qty'] == 1) {
                    $next->add(new DateInterval('P1M'));
                } else if($v['qty'] == 12) {
                    // overwrite the QTY to be 1 MONTH
                    $next->add(new DateInterval('P12M'));
                }
                $qtyStr = number_format($v['qty'], 0, ',',' ') . ' Monat(e)';
                if(!$isOffer)
                    $v['name'] .= "\n<strong>Zeitraum: " . $current->format('d.m.Y')." - ".$next->format('d.m.Y')."</strong>\n";
            } else if($product instanceof WC_Product_Hour) {
                // check if product type is "hour" to output hours instead of Qty
                $qtyStr = number_format($v['qty'], 1, ',',' ');
			    $qtyStr .= ' Std.';
			} else {
			    $qtyStr = number_format($v['qty'], 2, ',',' ');
			}

            $total = round($v['total'], 2);
            $tax = round($v['total_tax'], 2);

            $table->AddCell("$i", null, [], ['top' => 5]);
            $table->AddCell($v['name'], null, [], ['top' => 5]);
            $table->AddCell($qtyStr, 'right', [], ['top' => 5]);
            $table->AddCell(number_format($total, 2, ',',' ') . ' ' . $order->get_currency(), 'right', [], ['top' => 5]);

            // display discount
            if(isset($v['subtotal']) && ($subtotal = round($v['subtotal'], 2)) > $total)
            {
                $table->AddCell("", null, [], ['top' => 5]);
                $table->AddCell(" - " . __("Discount", 'wc-invoice-pdf'), null, [], ['top' => 5]);
                $table->AddCell("", 'right', [], ['top' => 5]);
                $table->AddCell(number_format($total - $subtotal, 2, ',',' ') . ' ' . $order->get_currency(), 'right', [], ['top' => 5]);
            }
            
            $summary += $total;
            $summaryTax += $tax;

            if($v instanceof WC_Order_Item_Product) {
                $meta = $v->get_meta_data();
                if(!empty($meta)) {
                    $table->AddCell('');
                    $mdcontent = "\n" . implode('',array_map(function($m){ return "<strong>".$m->key."</strong>\n".$m->value; }, $meta));
                    $table->AddCell($mdcontent);
                    $table->AddCell('');
                    $table->AddCell('');
                } 
            }

            $i++;
        }

        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("<strong>".__('Summary', 'wc-invoice-pdf')."</strong>", 'right', [], ['top' => 15]);
        $table->AddCell("<strong>".number_format($summary, 2,',',' '). ' ' . $order->get_currency()."</strong>", 'right', [], ['top' => 15]);

        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("<strong>+ 19% ".__('Tax', 'wc-invoice-pdf')."</strong>", 'right', [], ['top' => 5]);
        $table->AddCell("<strong>".number_format($summaryTax, 2,',',' '). ' ' . $order->get_currency() ."</strong>", 'right', [], ['top' => 5]);
        
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("<strong>".__('Total', 'wc-invoice-pdf')."</strong>", 'right', [], ['top' => 15]);
        $table->AddCell("<strong>".number_format($summary + $summaryTax, 2,',',' '). ' ' . $order->get_currency()."</strong>", 'right', [], ['top' => 15]);
        
        $table->EndTable();
        
        if($stream)
        {
            $pdf->Stream($invoice->invoice_number.'.pdf');
            return;
        }
        return $pdf->OutputAll();
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
