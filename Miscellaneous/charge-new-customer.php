<?php
require 'vendor/autoload.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

define("AUTHORIZENET_LOG_FILE", "phplog");

   
if(!defined('DONT_RUN_SAMPLES'))
    chargeNewCustomer("4111111111111111", 12.34,"mommyna@gmail.com", "Momina", "Mustesahn", "1234567890");

class MessageConstants
{
    const DUPLICATE_PROFILE_ID_MESSAGE_PREFIX = "A duplicate record with ID";    
}

function getDuplicateProfileId($message)
{
    $messageText = $message->getText();
    $index = strpos($messageText,MessageConstants::DUPLICATE_PROFILE_ID_MESSAGE_PREFIX);
    if($index == 0)
    {
        $remainingText = substr($messageText, strlen(MessageConstants::DUPLICATE_PROFILE_ID_MESSAGE_PREFIX) + 1); echo "starting text: ".MessageConstants::DUPLICATE_PROFILE_ID_MESSAGE_PREFIX.length + 1 . "\n"; echo "remainingText" . $remainingText . "\n";
        $profileId = explode(' ', trim($remainingText))[0]; echo "profileId" . $profileId . "\n";
        echo "Duplicate profile id: " . $profileId . "\n";
        return $profileId;
    }
    echo "index" . $index . "\n";
    
    return null;
}

function chargeNewCustomer($card, $amount, $email, $customerFirstName, $customerLastName, $phoneNumber)
{
    $profileId = null;
    $paymentProfileId = null;
    
    $cpResponse = createCustomerProfile($email, $customerFirstName, $customerLastName, $phoneNumber);
    if ($cpResponse != null)
    {
        if ($cpResponse->getMessages()->getResultCode() == "Ok"){
            $profileId = $cpResponse->getCustomerProfileId();
            $paymentProfiles = $cpResponse->getPaymentProfiles();
        }
        else if (false != $cpResponse->getMessages()->getMessage()[0]){
            $profileId = getDuplicateProfileId($cpResponse->getMessages()->getMessage()[0]);
            $getCpResponse = getCustomerProfile($profileId);
            $paymentProfiles = $getCpResponse->getPaymentProfiles();
        }
        
        echo $profileId;    
        foreach($paymentProfiles as $cpp)
        {
            if(substr($cpp->getPayment()->getCreditCard()->getCardNumber(), -4) == substr($card, -4))
            {
                $paymentProfileId = $cpp->getCustomerPaymentProfileId();
            }
        }
    }
    else
    {
        echo "ERROR :  Invalid response\n";
        $errorMessages = $response->getMessages()->getMessage();
        echo "Response : " . $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText() . "\n";
    }

    $chargeResponse = chargeCustomerProfile($profileId, $paymentProfileId, $amount);
}

function createCustomerProfile($email, $customerFirstName, $customerLastName, $phoneNumber){
  
    // Common setup for API credentials
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName(\SampleCode\Constants::MERCHANT_LOGIN_ID);
    $merchantAuthentication->setTransactionKey(\SampleCode\Constants::MERCHANT_TRANSACTION_KEY);
    $refId = 'ref' . time();

    // Create the payment data for a credit card
    $creditCard = new AnetAPI\CreditCardType();
    $creditCard->setCardNumber(  "4111111111111111");
    $creditCard->setExpirationDate( "2038-12");
    $paymentCreditCard = new AnetAPI\PaymentType();
    $paymentCreditCard->setCreditCard($creditCard);

    // Create the Bill To info
    $billto = new AnetAPI\CustomerAddressType();
    $billto->setFirstName($customerFirstName);
    $billto->setLastName($customerLastName);
    $billto->setCompany("Souveniropolis");
    $billto->setAddress("14 Main Street");
    $billto->setCity("Pecan Springs");
    $billto->setState("TX");
    $billto->setZip("44628");
    $billto->setCountry("USA");
    $billto->setPhoneNumber($phoneNumber);


    // Create a Customer Profile Request
    //  1. create a Payment Profile
    //  2. create a Customer Profile   
    //  3. Submit a CreateCustomerProfile Request
    //  4. Validate Profiiel ID returned

    $paymentprofile = new AnetAPI\CustomerPaymentProfileType();

    $paymentprofile->setCustomerType('individual');
    $paymentprofile->setBillTo($billto);
    $paymentprofile->setPayment($paymentCreditCard);
    $paymentprofiles[] = $paymentprofile;
    $customerprofile = new AnetAPI\CustomerProfileType();
    $customerprofile->setDescription("Customer 2 Test PHP");

    $customerprofile->setMerchantCustomerId("M_".$email);
    $customerprofile->setEmail($email);
    $customerprofile->setPaymentProfiles($paymentprofiles);

    $request = new AnetAPI\CreateCustomerProfileRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId( $refId);
    $request->setProfile($customerprofile);
    $controller = new AnetController\CreateCustomerProfileController($request);
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") )
    {
      echo "Succesfully create customer profile : " . $response->getCustomerProfileId() . "\n";
      $paymentProfiles = $response->getCustomerPaymentProfileIdList();
      echo "SUCCESS: PAYMENT PROFILE ID : " . $paymentProfiles[0] . "\n";
    }
    else
    {
      echo "ERROR :  Invalid response\n";
      $errorMessages = $response->getMessages()->getMessage();
      echo "Response : " . $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText() . "\n";
    }
    return $response;
}

function chargeCustomerProfile($profileid, $paymentprofileid, $amount){
    // Common setup for API credentials
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName(\SampleCode\Constants::MERCHANT_LOGIN_ID);
    $merchantAuthentication->setTransactionKey(\SampleCode\Constants::MERCHANT_TRANSACTION_KEY);
    $refId = 'ref' . time();

    $profileToCharge = new AnetAPI\CustomerProfilePaymentType();
    $profileToCharge->setCustomerProfileId($profileid);
    $paymentProfile = new AnetAPI\PaymentProfileType();
    $paymentProfile->setPaymentProfileId($paymentprofileid);
    $profileToCharge->setPaymentProfile($paymentProfile);

    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType( "authCaptureTransaction"); 
    $transactionRequestType->setAmount($amount);
    $transactionRequestType->setProfile($profileToCharge);

    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId( $refId);
    $request->setTransactionRequest( $transactionRequestType);
    $controller = new AnetController\CreateTransactionController($request);
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);

    if ($response != null)
    {
        if($response->getMessages()->getResultCode() == \SampleCode\Constants::RESPONSE_OK)
        {
            $tresponse = $response->getTransactionResponse();

            if ($tresponse != null && $tresponse->getMessages() != null)   
            {
            echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
            echo  "Charge Customer Profile APPROVED  :" . "\n";
            echo " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
            echo " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
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

function getCustomerProfile($profileIdRequested){
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName(\SampleCode\Constants::MERCHANT_LOGIN_ID);
    $merchantAuthentication->setTransactionKey(\SampleCode\Constants::MERCHANT_TRANSACTION_KEY);
    
    $request = new AnetAPI\GetCustomerProfileRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setCustomerProfileId($profileIdRequested);
    $controller = new AnetController\GetCustomerProfileController($request);
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") )
    {
        echo "GetCustomerProfile SUCCESS : " .  "\n";
        $profileSelected = $response->getProfile();
        return $profileSelected;
    }    
}
?>
