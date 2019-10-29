<?php defined( 'ABSPATH' ) or die( 'Denied' ); ?>
<h1>
        <?php print (isset($this->settings['description'])) ? $this->settings['description']:""; ?>
    </h1>
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
        <p style="font-style:italic;margin-top:10px;"><?php _e( 'You will be redirected  to complete this finance application when you place your order', 'woothemes' ); ?></p>
</fieldset>
<?php wp_nonce_field( 'submit-payment-form','submit-payment-form-nonce' ); ?>
