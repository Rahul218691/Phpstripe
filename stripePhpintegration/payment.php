<?php 
// Include configuration file  
require_once 'config.php'; 
 
// Get user ID from current SESSION 
$userID = isset($_SESSION['loggedInUserID'])?$_SESSION['loggedInUserID']:1; 
 
$payment_id = $statusMsg = $api_error = ''; 
$ordStatus = 'error'; 
 
// Check whether stripe token is not empty 
if(!empty($_POST['subscr_plan']) && !empty($_POST['stripeToken'])){ 
     
    // Retrieve stripe token, card and user info from the submitted form data 
    $token  = $_POST['stripeToken']; 
    $name = $_POST['name']; 
    $email = $_POST['email']; 
    $card_number = preg_replace('/\s+/', '', $_POST['card_number']); 
    $card_exp_month = $_POST['card_exp_month']; 
    $card_exp_year = $_POST['card_exp_year']; 
    $card_cvc = $_POST['card_cvc']; 
     
    // Plan info 
    $planID = $_POST['subscr_plan']; 
    $planInfo = $plans[$planID]; 
    $planName = $planInfo['name']; 
    $planPrice = $planInfo['price']; 
    $planInterval = $planInfo['interval']; 
     
    // Include Stripe PHP library 
    require_once 'stripe-php-7.27.1/init.php'; 
     
    // Set API key 
    \Stripe\Stripe::setApiKey(STRIPE_API_KEY); 
     
    // Add customer to stripe 
    $customer = \Stripe\Customer::create(array( 
        'email' => $email, 
        'source'  => $token 
    )); 
     
    // Convert price to cents 
    $priceCents = round($planPrice*100); 
     
    // Create a plan 
    try { 
        $plan = \Stripe\Plan::create(array( 
            "product" => [ 
                "name" => $planName 
            ], 
            "amount" => $priceCents, 
            "currency" => $currency, 
            "interval" => $planInterval, 
            "interval_count" => 1 
        )); 
    }catch(Exception $e) { 
        $api_error = $e->getMessage(); 
    } 
     
    if(empty($api_error) && $plan){ 
        // Creates a new subscription 
        try { 
            $subscription = \Stripe\Subscription::create(array( 
                "customer" => $customer->id, 
                "items" => array( 
                    array( 
                        "plan" => $plan->id, 
                    ), 
                ), 
            )); 
        }catch(Exception $e) { 
            $api_error = $e->getMessage(); 
        } 
         
        if(empty($api_error) && $subscription){ 
            // Retrieve subscription data 
            $subsData = $subscription->jsonSerialize(); 
     
            // Check whether the subscription activation is successful 
            if($subsData['status'] == 'active'){ 
                // Subscription info 
                $subscrID = $subsData['id']; 
                $custID = $subsData['customer']; 
                $planID = $subsData['plan']['id']; 
                $planAmount = ($subsData['plan']['amount']/100); 
                $planCurrency = $subsData['plan']['currency']; 
                $planinterval = $subsData['plan']['interval']; 
                $planIntervalCount = $subsData['plan']['interval_count']; 
                $created = date("Y-m-d H:i:s", $subsData['created']); 
                $current_period_start = date("Y-m-d H:i:s", $subsData['current_period_start']); 
                $current_period_end = date("Y-m-d H:i:s", $subsData['current_period_end']); 
                $status = $subsData['status']; 
                 
                // Include database connection file  
                
     
                // Insert transaction data into the database 
                $sql = "INSERT INTO user_subscriptions(user_id,stripe_subscription_id,stripe_customer_id,stripe_plan_id,plan_amount,plan_amount_currency,plan_interval,plan_interval_count,payer_email,created,plan_period_start,plan_period_end,status) VALUES('".$userID."','".$subscrID."','".$custID."','".$planID."','".$planAmount."','".$planCurrency."','".$planinterval."','".$planIntervalCount."','".$email."','".$created."','".$current_period_start."','".$current_period_end."','".$status."')"; 
                $insert = $db->query($sql);  
                  
                // Update subscription id in the users table  
                if($insert && !empty($userID)){  
                    $subscription_id = $db->insert_id;  
                    $update = $db->query("UPDATE users SET subscription_id = '$subscription_id' WHERE id = '$userID'");  
                } 
                 
                $ordStatus = 'success'; 
                $statusMsg = 'Your Subscription Payment has been Successful!'; 
            }else{ 
                $statusMsg = "Subscription activation failed!"; 
            } 
        }else{ 
            $statusMsg = "Subscription creation failed! ".$api_error; 
        } 
    }else{ 
        $statusMsg = "Plan creation failed! ".$api_error; 
    } 
}else{ 
    $statusMsg = "Error on form submission, please try again."; 
} 
?>

<div class="container">
    <div class="status">
        <h1 class="<?php echo $ordStatus; ?>"><?php echo $statusMsg; ?></h1>
        <?php if(!empty($subscrID)){ ?>
            <h4>Payment Information</h4>
            <p><b>Reference Number:</b> <?php echo $subscription_id; ?></p>
            <p><b>Transaction ID:</b> <?php echo $subscrID; ?></p>
            <p><b>Amount:</b> <?php echo $planAmount.' '.$planCurrency; ?></p>
			
            <h4>Subscription Information</h4>
            <p><b>Plan Name:</b> <?php echo $planName; ?></p>
            <p><b>Amount:</b> <?php echo $planPrice.' '.$currency; ?></p>
            <p><b>Plan Interval:</b> <?php echo $planInterval; ?></p>
            <p><b>Period Start:</b> <?php echo $current_period_start; ?></p>
            <p><b>Period End:</b> <?php echo $current_period_end; ?></p>
            <p><b>Status:</b> <?php echo $status; ?></p>
        <?php } ?>
    </div>
    <a href="index.php" class="btn-link">Back to Subscription Page</a>
</div>