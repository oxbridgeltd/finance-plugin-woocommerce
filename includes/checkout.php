<?php defined( 'ABSPATH' ) or die( 'Denied' ); ?>
<p id="finance-payment-description">
        <?php print (isset($this->settings['description'])) ? $this->settings['description']:""; ?>
    </p>
<div id="financeWidget"
    data-calculator-widget
    data-mode="calculator"
    data-plans="<?php print $plans; ?>"
    data-amount="<?php print $amount; ?>"
    data-footnote="<?php print $footnote; ?>"
>
</div>

<script type="text/javascript">
        jQuery(document).ready(function($) {
                waitForElementToDisplay('#financeWidget', 1000);
        });
        </script>
<div class="clear"></div>
        <p style="font-style:italic;margin-top:10px;"><?php _e('frontend/paymentredirection_msg', 'woocommerce-finance-gateway'); ?></p>
</fieldset>
<?php wp_nonce_field( 'submit-payment-form','submit-payment-form-nonce' ); ?>
