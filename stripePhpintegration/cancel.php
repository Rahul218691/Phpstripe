<?php
require_once 'config.php'; 
require_once 'stripe-php-7.27.1/init.php'; 
\Stripe\Stripe::setApiKey(STRIPE_API_KEY); 
$subdata = "sub_Gy35XeRbbX0jEa"; //subscription id to be made cancel currently its static u can make it dynamic
$subscription = \Stripe\Subscription::retrieve($subdata);
$subscription->cancel();

?>