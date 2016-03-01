<?php

// ,
use diversen\lang;


$_INSTALL['MAIN_MENU_ITEMS'] = array ();
$_INSTALL['MAIN_MENU_ITEMS'][] = array (
    'title' => lang::translate('Mailing list', null, array('no_translate' => true)),
    'url' => '/mlist/index',
    'module_name' => 'mlist',
    'weight' => 100,
    'auth' => 'admin',


);
