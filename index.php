<?php
/**
 * \file
 * Aplikace ftpdeploy.
 * \author -dk- <lenochware@gmail.com>
 */
include 'vendor/autoload.php';
include 'libs/func.php';

session_start();

$app = new PCApp('ftpdeploy');

$app->addConfig('./config.php');
$app->setLayout(isset($_GET['popup'])? 'tpl/popup.tpl' : 'tpl/website.tpl');

$app->layout->_VERSION = 'v1.6.0';
$app->layout->_MENU = file_get_contents('tpl/menu.tpl');

if (!$app->controller) $app->controller = 'deploy';
$app->run();
$app->out();

?>