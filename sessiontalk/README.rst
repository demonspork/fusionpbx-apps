*********
SessionTalk
*********

SessionTalk's CloudPhone is a softphone available for iOS and Android devices with PUSH notification available. This app is designed to work with their cloud provisioning services. 

Prerequisites
^^^^^^^^^^^^^^

* Working install of FusionPBX
* Create an App at https://cloud.sessiontalk.co.uk/
* Use "Username, Password & Subdomain" for the Login Fields
* Provider Name (Same as FusionPBX Default Settings)
* External Provisioning URL: https://mydomain.com/app/sessiontalk/provision.php
* Set everything else up according to your needs


Install Steps
^^^^^^^^^^^^^^

On your server

::

  cd /usr/src
  git clone https://github.com/fusionpbx/fusionpbx-apps
  Move the directory 'sessiontalk' into your main FusionPBX directory
  mv fusionpbx-apps/sessiontalk /var/www/fusionpbx/app
  chown -R www-data:www-data /var/www/fusionpbx/app/sessiontalk

::

 Log into the FusionPBX webpage
 Advanced -> Upgrade
 Schema Defaults
 App Defaults
 Menu Defaults and Permission Defaults.
 Log out and back in.
 
::

 Set your Sessiontalk provider ID in Default Settings
 Advanced -> Default Settings
 Provision -> sessiontalk_provider_id
 
::

Assign the sessiontalk_view permission to users who need to veiw their own extension (or extensions their user is added to)

The app generates a single-use QR code that is saved in the database. Upon provisioning, it creates a device with a vendor sessiontalk and uses the last 10 digits of the device_id as the MAC address

Upon initial provision, it uses the extension that was selected for the QR Code. It will assign extensions to the device based on the lines configured in that extension.



TO DO
^^^^^^^^
* Fix index.php never showing the full extension list despite the superadmin having sessiontalk_view_all permissions. It always fails the permission check, at least on my test system, and uses the else statement to SELECt the list of extensions.
* Fix the loop near the end of provision.php that allows multiple device lines to be delivered in the json payload. 
* Add enabled = true checks for all extension and devices SELECT and updates (completely forgot about this)
* Create app_defaults.php and include code to ALTER TABLE and create relations including ON DELETE CASCADE for the v_sessiontalk_devices to link to the v_devices table and the v_sessiontalk_keys to link to the v_extensions table
* Need to do some experimenting on how to convince the app to update its credentials/add additional accounts. Theoretically update: true should be set in the json but I need to set up some logic on the FusionPBX side that somehow detects if the device line settings have been changed.
* Their Documentation: https://www.sessiontalk.co.uk/help-articles/using-qr-codes-with-your-provisioning-server
* Contact support and see why I get 318 server error when attempting to provision.
