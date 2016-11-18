sms_twilio
~~~~~~~~~~

sms_twilio is a drupal module to add the twilio.com gateway to the sms framework module.

INSTALL
~~~~~~~

1. Download the twilio PHP library https://github.com/twilio/twilio-php/archive/master.zip and unzip into sites/all/libraries (this path can be edited when setting up the gateway).

2. See the getting started guild on installing drupal modules:
http://drupal.org/getting-started/install-contrib/modules

3. Go to admin/smsframework/gateways to setup the twilio gateway.

REQUIREMENTS
~~~~~~~~~~~~

1. The 7.x-2.x version of sms_twilio requires that you are using the 2010-04-01 API version.
If you are still using the 2008-08-01 version you can change it in your console.  For more
information see https://www.twilio.com/blog/2010/08/announcing-the-new-twilio-api-version-2010-04-01.html
2. The 7.x-2.x version requires PHP >= 5.3

Credits
*******
Chris Hood (http://www.univate.com.au/)

LICENSE
~~~~~~~

This software licensed under the GNU General Public License 2.0.
