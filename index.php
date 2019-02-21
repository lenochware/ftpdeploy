<?php
/**
 * \file
 * Aplikace ftpdeploy.
 * \author -dk- <lenochware@gmail.com>
 */
include 'libs/pclib/pclib.php';
include 'libs/func.php';

session_start();

$app = new PCApp('ftpdeploy');
$app->addConfig('./config.php');
//$app->debugMode = $app->config['padmin.debugmode'];
$app->setLayout($_GET['popup']? 'tpl/popup.tpl' : 'tpl/website.tpl');
$app->layout->_VERSION = 'v1.1.1';
$app->layout->_MENU = file_get_contents('tpl/menu.tpl');

if (!$app->controller) $app->controller = 'deploy';
$app->run();
$app->out();

?>