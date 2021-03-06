<?php
  require 'vendor/autoload.php';
  require_once 'Constants.php';
  use net\authorize\api\contract\v1 as AnetAPI;
  use net\authorize\api\controller as AnetController;

  define("AUTHORIZENET_LOG_FILE", "phplog");

  function updateHeldTransaction(){
      // Common setup for API credentials
      $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
      $merchantAuthentication->setName(Constants::MERCHANT_LOGIN_ID);
      $merchantAuthentication->setTransactionKey(Constants::MERCHANT_TRANSACTION_KEY);
      $refId = 'ref' . time();

      // Create the payment data for a credit card
      $creditCard = new AnetAPI\CreditCardType();
      $creditCard->setCardNumber("4111111111111111");
      $creditCard->setExpirationDate("1226");
      $creditCard->setCardCode("123");
      $paymentOne = new AnetAPI\PaymentType();
      $paymentOne->setCreditCard($creditCard);

      $order = new AnetAPI\OrderType();
      $order->setDescription("New Item");

      //create a transaction
      $transactionRequestType = new AnetAPI\HeldTransactionRequestType();
      $transactionRequestType->setAction("approve"); //other possible value: decline
      $transactionRequestType->setRefTransId("412121");
      

      $request = new AnetAPI\UpdateHeldTransactionRequest();
      $request->setMerchantAuthentication($merchantAuthentication);
      $request->setHeldTransactionRequest( $transactionRequestType);
      $controller = new AnetController\UpdateHeldTransactionController($request);
      $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
      

      if ($response != null)
      {
        if($response->getMessages()->getResultCode() == 'Ok')
        {
          $tresponse = $response->getTransactionResponse();
          
	        if ($tresponse != null && $tresponse->getMessages() != null)   
          {
            echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
            echo "Charge Credit Card AUTH CODE : " . $tresponse->getAuthCode() . "\n";
            echo "Charge Credit Card TRANS ID  : " . $tresponse->getTransId() . "\n";
            echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n"; 
	          echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
          }
          else
          {
            echo "Transaction Failed \n";
            if($tresponse->getErrors() != null)
            {
              echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
              echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";            
            }
          }
        }
        else
        {
          echo "Transaction Failed \n";
          $tresponse = $response->getTransactionResponse();
          if($tresponse != null && $tresponse->getErrors() != null)
          {
            echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
            echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";                      
          }
          else
          {
            echo " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
            echo " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
          }
        }      
      }
      else
      {
        echo  "No response returned \n";
      }

      return $response;
  }
  if(!defined('DONT_RUN_SAMPLES'))
      updateHeldTransaction();
?>
