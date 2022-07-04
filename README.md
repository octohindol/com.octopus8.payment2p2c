# com.octopus8.payment2c2p

![Screenshot](/images/screenshot.png)

This extension provides access to 2c2p Payment gateway

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.2+
* CiviCRM (5.48.0)

## Installation (Web UI)

Learn more about installing CiviCRM extensions in the [CiviCRM Sysadmin Guide](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/).

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl com.octopus8.payment2c2p@https://github.com/FIXME/com.octopus8.payment2c2p/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/FIXME/com.octopus8.payment2c2p.git
cv en payment2c2p
```

## Getting Started

All configuration is in the standard Payment Processors settings area in CiviCRM admin.
You should enter your "Merchant ID", "Secret", "Gateway URL", "Thank You Page URL" and "Failure Page URL".


## Known Issues

- 0.1.5 - fixed error when Processor Type Name and Processor Name are different

- 0.1.6 - transaction id len = 10. 

- 0.1.7 - User name and email autofilled.

    Canceled Payment -> failed

- 0.1.8 - Thank you page absolute redirect

- 0.1.9 - Payment status ID fixed

- 0.1.10 - Failure URL

- 0.1.11 - NRIC added

- 0.1.12 - NRIC field is shown only for tax-deductable contributions

- 0.1.13 - new NRIC algorithm:
    1) If user is present but has empty NRIC - NRIC will be added to this user
    
    2) If user is new - NRIC will be added to this user
    
    3) If user has NRIC but it differs 
    from the given NRIC and no new user 
    is created by profile - new user with noname will be created

- 0.1.14 - 
    1) If user cancels the contribution it gets 'Canceled' status
        ![Screenshot](/images/screenshot-0.1.14-1.png)
    2) If user does not get back to CiviCRM and the status of Contribution is Pending,
    administrator now can Update status from 2c2p Server
        ![Screenshot](/images/screenshot-0.1.14-2.png)
    
- 0.1.15 - Absent NRIC is ignored. User should use 'Create on behalf' to create user with a new NRIC    - 0.1.15 - Absent NRIC is ignored. User should use 'Create on behalf' to create user with a new NRIC    

- 0.1.16 beta - Started procs to load-unload commands for recurring payment.
    Recurring payments can be created, but they are managed yet only via 2c2p admin dashboard

- 0.1.17 beta - 3D forced payments.

- 0.1.18 beta - NRIC is freezed for users with NRIC. To pay for somebody else user should use link.

- 0.1.19 beta - Response to the server is loaded via backend URL, so that system does not wait for user to go back to the site.

if you use ngrock to test your CiviCRM, you may need to put this to the civicrm.settings.php as well 
```
define( 'CIVICRM_UF_BASEURL'      , 'https://########.ngrok.io');
```

- 0.1.20 beta - added proc to unsign-unencrypt response from 2c2p server
    developing proc to send signed-encrypted request to 2c2p server

- 0.1.21 beta - changing the way of checking the payment status from 2c2p
    Redirect after check is not working yet, should redirect manually
    Refunded status check to do
    Recurring payments status check to do
    Void / Cancelled status OK

- 0.1.22 beta - Cancelling transaction via EDIT cancels it in the 2c2p system

- 0.1.22 beta - Cancelling transaction via Update status from 2c2p
   refunds payments in CiviCRM system

- 0.1.24 Checking cancelled transactions - lots of logging

- 0.1.25 Url Api Added

- 0.1.27 Omitted checking payment via Key-Signature for 0000 and 0003 resp codes
