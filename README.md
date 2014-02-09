#Kohana preroute module
==============

##Getting Started

##Sample use:


### Set lang preroute

```php
Route::preroute('(<lang>)(/)', array('lang' => 'ru-ru|en-us'), function($params)
{
  I18n::lang($params['lang']);
}, array(
   'lang' => 'ru-ru',
   ));

