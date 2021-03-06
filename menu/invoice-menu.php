<?php

namespace WCInvoicePdf\Menu;

use WCInvoicePdf\Model\InvoiceList;
use WCInvoicePdf\WCInvoicePdf;

class InvoiceMenu {
    public function __construct(){
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    }

    public function admin_menu(){
        add_menu_page( 'WC-' . __('Invoices', 'wc-invoice-pdf'), 'WC-' . __('Invoices', 'wc-invoice-pdf'), 'null', 'wcinvoicepdf_menu',  null, WCINVOICEPDF_PLUGIN_URL.'invoicepdf.png', 3);
        add_submenu_page('wcinvoicepdf_menu', __('Invoices', 'wc-invoice-pdf'), __('Invoices', 'wc-invoice-pdf'), 'edit_themes', 'wcinvoicepdf_invoices',  [$this, 'DisplayInvoices'] );
        add_submenu_page('wcinvoicepdf_menu', __('Settings'), __('Settings'), 'edit_themes', 'wcinvoicepdf_settings',  [$this, 'DisplaySettings'] );
    }

    public function DisplayInvoices(){
        $invList = new InvoiceList();
        
        $a = $invList->current_action();
        $invList->prepare_items();
        ?>
        <div class='wrap'>
            <h1><?php _e('Invoices', 'wc-invoice-pdf') ?></h1>
            <h2></h2>
            <form action="" method="GET">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']) ?>" />
                <input type="hidden" name="action" value="filter" />
                <label class="post-attributes-label" for="user_login"><?php _e('Customer', 'woocommerce') ?></label>
                <select name="customer_id" style="min-width: 200px">
                    <option value=""></option>
                <?php  
                $users = get_users(['role' => 'customer']);
                foreach ($users as $u) {
                    $company = get_user_meta($u->ID, 'billing_company', true);
                    $selected = (isset($_GET['customer_id']) && $u->ID == intval($_GET['customer_id']))?'selected':'';
                    echo '<option value="'.$u->ID.'" '.$selected.'>'. $company . ' (' .$u->user_login.')</option>';
                }
                ?>
                </select>
                <input type="checkbox" id="recur_only" name="recur_only" value="1" <?php echo (!empty($_GET['recur_only'])?'checked':'') ?> /> <label for="recur_only"><?php _e('Recurring payments', 'wc-invoice-pdf') ?></label>
                <input type="submit" class="button" value="<?php _e('Filter &#187;') ?>">
            </form>
            <form action="" method="POST">
                <?php $invList->display(); ?>
            </form>
        </div>
        <?php
    }

    public function DisplaySettings(){
        $oldConf = get_option('WPISPConfig3_Options');

        if(!empty($_GET['migrate']) && isset($oldConf['wc_pdf_title'])) {
            foreach(WCInvoicePdf::$OPTIONS as $k => &$v){
                if(!empty($oldConf[$k])) {
                    $v = $oldConf[$k];
                    unset($oldConf[$k]);
                }
            }

            if(WCInvoicePdf::save_options()) {
                ?><div class="updated"><p>ISPConfig settings migrated</p></div><?php
                update_option('WPISPConfig3_Options', $oldConf);
            }
        }

        if ( 'POST' === $_SERVER[ 'REQUEST_METHOD' ] ) {
            WCInvoicePdf::$OPTIONS = $_POST;           
            if(WCInvoicePdf::save_options()) {
                ?><div class="updated"><p> <?php _e( 'Settings saved', 'wc-invoice-pdf' );?></p></div><?php
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('WC-Invoice Settings', 'wc-invoice-pdf' );?></h1>

            <?php if( wp_get_schedule( 'invoice_reminder' )): ?>
                <div class="notice notice-info"><p>The schedule is properly installed and running</p></div>
            <?php else: ?>
                <div class="notice notice-error"><p>The scheduled task is NOT INSTALLED - Try to reactivate the plugin</p></div>
            <?php endif; ?>
            <?php
                if(!empty($oldConf) && isset($oldConf['wc_pdf_title'])):
            ?>
                <div class="notice notice-info"><p>ISPCONFIG Settings found - <a href="?page=wcinvoicepdf_settings&migrate=1">Click here</a> to migrate relevant data</p></div>
            <?php endif; ?>

            <form method="post" action="">
                <div id="poststuff" class="metabox-holder has-right-sidebar">
                    <div id="post-body">
                        <ul id="wcinvoicepdf-tabs" class="category-tabs">
                            <li class="hide-if-no-js"><a href="#wcinvoicepdf-invoice"><?php _e('Invoices', 'wc-invoice-pdf')?></a></li>
                            <li class="hide-if-no-js"><a href="#wcinvoicepdf-scheduler"><?php _e('Task Scheduler', 'wc-invoice-pdf') ?></a></li>
                            <li class="hide-if-no-js"><a href="#wcinvoicepdf-template"><?php _e('Templates', 'wc-invoice-pdf') ?></a></li>
                            <li class="hide-if-no-js"><a href="#wcinvoicepdf-export"><?php _e('Export', 'wc-invoice-pdf') ?></a></li>
                        </ul>
                        <div class="postbox inside">
                            <div id="wcinvoicepdf-invoice" class="inside tabs-panel" style="display: none;">
                                <h3><?php _e('Invoice template (PDF)', 'wc-invoice-pdf') ?></h3>
                                <?php
                                WCInvoicePdf::addField('wc_pdf_title', 'Document Title');
                                WCInvoicePdf::addField('wc_pdf_logo', 'Logo Image', 'media');
                                WCInvoicePdf::addField('wc_pdf_addressline', 'Address line');
                                WCInvoicePdf::addField('wc_pdf_condition', 'Conditions', 'textarea');
                                WCInvoicePdf::addField('wc_pdf_info', 'Info Block', 'textarea');
                                WCInvoicePdf::addField('wc_pdf_block1', 'Block #1', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
                                WCInvoicePdf::addField('wc_pdf_block2', 'Block #2', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
                                WCInvoicePdf::addField('wc_pdf_block3', 'Block #3', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
                                ?>
                            </div>
                            <div id="wcinvoicepdf-scheduler" class="inside tabs-panel" style="display: none;">
                                <h3><?php _e('Run commands', 'wc-invoice-pdf') ?></h3>
                                <p>
                                    <a href="javascript:void(0)" onclick="WCInvoicePdfAdmin.RunTask(this, 'reminder')" class="button">Run Payment Reminder</a><br />
                                    Execute the payment reminder being sent to <strong>Admin Email</strong>.<br />This reminder usually occurs DAILY whenever an invoice is due.
                                </p>
                                <p>
                                    <a href="javascript:void(0)" onclick="WCInvoicePdfAdmin.RunTask(this, 'recurring')" class="button">Test Recurr Payment</a><br />
                                    Test the recurring payments (which is usually send to customer)<br />by overwriting the recipient addresses to <strong>Admin Email</strong>
                                </p>
                                <p>
                                    <a href="javascript:void(0)" onclick="WCInvoicePdfAdmin.RunTask(this, 'recurring_reminder')" class="button">Run Recurr Reminder</a><br />
                                    Run the recurring reminder now
                                </p>
                                <h3><?php _e('Sender info', 'wc-invoice-pdf') ?></h3>
                                <?php
                                WCInvoicePdf::addField('wc_mail_reminder', '<strong>Admin Email</strong><br />used for payment reminders and testing purposes');
                                WCInvoicePdf::addField('wc_mail_sender', '<strong>Sender Email</strong><br />Customer will see this address');
                                ?>
                                <h3><?php _e('Payments', 'wc-invoice-pdf') ?></h3>
                                <?php
                                WCInvoicePdf::addField('wc_payment_reminder', '<strong>'. __('Payment report', 'wc-invoice-pdf') .'</strong><br />send a daily report of unpaid invoices to "Admin Email"','checkbox');
                                WCInvoicePdf::addField('wc_recur', '<strong>' . __('Recurring payments', 'wc-invoice-pdf').'</strong><br />Submit every invoice to the customer based on the recurring payment period','checkbox');
                                WCInvoicePdf::addField('wc_recur_test', '<span style="color: red; font-weight: bold">Test Recurring</span><br />replace the recipient email with the admin email to test recurring PAYMENTS and REMINDERS','checkbox');
                                WCInvoicePdf::addField('wc_recur_reminder', '<strong>'. __('Payment reminder', 'wc-invoice-pdf').'</strong><br />Send payment reminders to customer when invoice is due','checkbox');
                                WCInvoicePdf::addField('wc_recur_reminder_age', '<strong>' . __('First reminder (days)', 'wc-invoice-pdf') . '</strong><br />The number of days (after due date) when a reminder should be sent to customer');
                                WCInvoicePdf::addField('wc_recur_reminder_interval', '<strong>'. __('Reminder interval', 'wc-invoice-pdf') .'</strong><br />The number of days (after first occurence) a reminder should be resent to customer');
                                WCInvoicePdf::addField('wc_recur_reminder_max', '<strong>'. __('Max reminders', 'wc-invoice-pdf') .'</strong><br />How many reminders should be sent for a single invoice to the customer');
                                ?>
                                <input type="hidden" name="wc_enable" value="1" />
                            </div>
                            <div id="wcinvoicepdf-template" class="inside tabs-panel" style="display: none;">
                                <p>
                                    Customize your templates being sent internally or to the customer<br />
                                    <strong>PLEASE NOTE: The changes will immediatly take effect once you pressed "Save"</strong>
                                </p>
                                <h3><?php _e('Payment report', 'wc-invoice-pdf')  ?></h3>
                                <?php
                                $attr = [
                                    'label_attr' => [ 'style' => 'width: 200px; display:inline-block;vertical-align:top;'],
                                    'input_attr' => ['style' => 'margin-left: 1em; width:50em;height: 200px']
                                ];
                                WCInvoicePdf::addField('wc_payment_message', '<strong>'. __('Payment report', 'wc-invoice-pdf') .'</strong><br />Inform the administrator (see "Admin Email") about outstanding invoices','textarea', $attr);
                                ?>
                                <h3><?php _e('Payments', 'wc-invoice-pdf') ?></h3>
                                <?php
                                WCInvoicePdf::addField('wc_recur_message', '<strong>' . __('Recurring payments', 'wc-invoice-pdf').'</strong><br />Submit the recurring invoice to the customer containing this message', 'textarea', $attr);
                                ?>
                                <div style="font-size: smaller; margin-left: 220px;">
                                    Placeholder: 
                                    <strong>{CUSTOMER_NAME}</strong> |
                                    <strong>{INVOICE_NO}</strong> |
                                    <strong>{DUE_DATE}</strong> |
                                    <strong>{DUE_DAYS}</strong>
                                </div>
                                <?php
                                WCInvoicePdf::addField('wc_recur_reminder_message', '<strong>'. __('Payment reminder', 'wc-invoice-pdf').'</strong><br />Submit the recurring invoice to the customer containing this message', 'textarea', $attr);
                                ?>
                                <div style="font-size: smaller;margin-left: 220px;">
                                    Placeholder: 
                                    <strong>{CUSTOMER_NAME}</strong> |
                                    <strong>{INVOICE_NO}</strong> |
                                    <strong>{DUE_DATE}</strong> |
                                    <strong>{DUE_DAYS}</strong> |
                                    <strong>{NEXT_DUE_DAYS}</strong>
                                </div>
                                <input type="hidden" name="wc_enable" value="1" />
                            </div>
                            <div id="wcinvoicepdf-export" class="inside tabs-panel" style="display: none;">
                                <p>
                                    The export feature currently supports GnuCash *.csv format to import invoices.</br />
                                    Please make sure the CUSTOMER ID (in GnuCash) matches the user id in wordpress
                                </p>
                                <?php
                                WCInvoicePdf::addField('wc_export_locale', '<strong>Locale</strong><br />Example: de_DE en_US');
                                WCInvoicePdf::addField('wc_export_notes', '<strong>Notes</strong><br />Invoice notes');
                                WCInvoicePdf::addField('wc_export_account', '<strong>Account name</strong><br />Name of the account an invoce is booked');
                                WCInvoicePdf::addField('wc_export_account_posted', '<strong>Account name</strong><br />Name of the account invoce is posted');
                                WCInvoicePdf::addField('wc_export_account_tax', '<strong>Tax Account (optional)</strong><br />Name of the tax account');
                                ?>
                            </div>
                        </div>
                        <div class="inside">
                            <p></p>
                            <p><input type="submit" class="button-primary" name="submit" value="<?php _e('Save', 'wc-invoice-pdf');?>" /></p>
                            <p></p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}