<?php defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); ?>
<fieldset id="finance-checkout" class="finance-calculator" data-<?php print $environment;?>-amount="<?php print $amount; ?>" data-<?php print $environment;?>-plans="<?php print $plans; ?>">
<h1>
        <?php print (isset($this->settings['description'])) ? $this->settings['description']:""; ?>
    </h1>
<div id="financeWidget" data-<?php print $environment;?>-widget data-<?php print $environment;?>-plans="<?php print $plans; ?>" data-<?php print $environment;?>-amount="<?php print $amount; ?>" ></div>
<script type="text/javascript">
        jQuery(document).ready(function($) {
                waitForElementToDisplay('#financeWidget', 1000);
        });
        </script>
<div class="clear"></div>
        <p style="font-style:italic;margin-top:10px;"><?php _e( 'You will be redirected  to complete this finance application when you place your order', 'woothemes' ); ?></p>
</fieldset>
<?php wp_nonce_field( 'submit-payment-form','submit-payment-form-nonce' ); ?>
