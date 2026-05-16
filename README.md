# 2fa-demo
A demo page for 2fa auth

Composer is needed for the otphp library:
```
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Create a project directory and install the library:
```
mkdir ~/2fa-demo
cd ~/2fa-demo
composer require spomky-labs/otphp
```
