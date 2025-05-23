<?php
include 'libs/FileSync.php';
include 'libs/TextLogger.php';
include 'libs/class.Diff.php';

use pclib\Str;

class DeployController extends PCController {

/*var TextLogger */
protected $logger;

/* var array Upload results. */
protected $result;

function indexAction() {
  $grid = new PCGrid('tpl/tasks.tpl');
  $grid->setArray($this->getTasks());
  return $grid;
}

function historyAction($task)
{
  $script = "<script> window.scrollTo(0, document.body.scrollHeight); </script>";
  return '<pre>'.@file_get_contents('./data/log/'.$task.'.log').'</pre>' . $script;
}

/** Run if no deploy has been done yet - add files to monitoring. */
function initAction($task)
{
  $this->logger = new TextLogger($task);

  $task = sanitize($task, 'file-id');

  $config = $this->getConfig($task);

  if (!is_dir($config['local'])) {
    $this->app->error($config['local'] .' is not valid directory.');
  }

  $fs = new FileSync;
  $files = $fs->getList($config['local'], $config);

  $hashes = $this->createHashArray($files, true);

  if (isset($_POST['save'])) {
    $this->saveHashFile($task, $hashes);
    $this->app->message('Soubory byly přidány.');
    $this->logger->log('Init '.now()." Úloha inicializována.\n");
    $this->app->redirect('deploy/preview/task:'.$task);
  }
  elseif(isset($_POST['no_save'])) {
    $this->saveHashFile($task, []);
    $this->logger->log('Init '.now()." Úloha inicializována.\n");
    $this->app->redirect('deploy/preview/task:'.$task);    
  }

  $grid = new PCGrid('tpl/preview_init.tpl');

  $allFiles = $this->getDiff($config['local'], array(), $hashes);
  $grid->setArray($allFiles);
  $grid->values['TASK'] = $task;
  $grid->values['TOTAL'] = count($allFiles);

  return $grid;
}

/** Show list of modified or deleted files. */
function previewAction($task)
{
  include PCLIB_DIR.'extensions/GridForm.php';

  $task = sanitize($task, 'file-id');
  if (!file_exists('data/'.$task.'.md5')) {
    $this->app->redirect('deploy/init/task:'.$task);
  }

  $this->app->layout->values['TITLE'] = $task;

  $config = $this->getConfig($task);
  $fs = new FileSync;
  $files = $fs->getList($config['local'], $config);

  $hashes = $this->createHashArray($files);
  $savedHashes = $this->loadHashFile($task);

  $grid = new pclib\Extensions\GridForm('tpl/preview.tpl');
  $data = $this->getDiff($config['local'], $savedHashes, $hashes);
  $grid->setArray($data);
  $grid->values['TASK'] = $task;
  $grid->values['TOTAL'] = count($data);

  $datasource = parse_url($config['remote']) + ['host' => '', 'scheme' => '', 'path' => ''];
  $grid->values['HOST'] = $datasource['host'];

  if ($datasource['scheme'] =='ftps') {
    $grid->values['FTPS'] = 'ftps';
  }
  elseif ($datasource['scheme'] =='sftp') {
    $grid->values['SFTP'] = 'sftp';
  }

  $grid->values['REMOTEDIR'] = $datasource['path'];

  return $grid;
}

function skipAction($task)
{
  $rows = (array)$_POST['rowdata'];
  $task = sanitize($task, 'file-id');
  $config = $this->getConfig($task);
  $hashes = $this->loadHashFile($task);
  $sourcedir = $config['local'];

  foreach ($rows as $row) {
    list($fileName, $status) = explode('*', $row['FILE']);

    if (file_exists($sourcedir.'/'.$fileName)) {
      $hashes[$fileName] = $this->hash($sourcedir.'/'.$fileName);
    }
    else {
      unset($hashes[$fileName]);
    }
  }

  $this->saveHashFile($task, $hashes);
  $this->app->message('Označení dokončeno.');
  $this->app->redirect("deploy/preview/task:$task");
}

/** Copy or delete selected files to remote server. */
function commitAction($task)
{
  $this->logger = new TextLogger($task);

  $data = $_POST['data'];
  $task = sanitize($task, 'file-id');
  $config = $this->getConfig($task);
  if ($config['password'] != $data['PASSWORD']) {
    $this->app->error('Chybné heslo.');
  }

  $this->logger->log('Deploy '.now().' '.$data['COMMENT']);

  list($modified, $deleted, $unwatch) = $this->prepareData($config, $_POST['rowdata']);

  $fs = new FileSync;
  $fs->connect($config['remote']);

  set_time_limit(0);
  $startTime = microtime(true);

  $this->result = array('ok' => 0, 'failed' => 0, 'time' => 0);
  
  $hashes = $this->loadHashFile($task);
  $this->remoteCopy($fs, $modified, $hashes);
  $this->remoteDelete($fs, $deleted, $hashes);

  foreach ($unwatch['files'] as $fileName) {
    unset($hashes[$fileName]);
  }

  $this->result['time'] = round(microtime(true) - $startTime, 2);
  $this->logger->log(paramstr("Aktualizováno {ok} souborů, {failed} chyb ({time}s)\n", $this->result));

  //dump($modified, $deleted);
  $this->saveHashFile($task, $hashes);
  return $this->logger->getHtmlOutput();
}

function remoteCopy($fs, $modified, &$hashes)
{
  foreach ($modified['files'] as $fileName) {
    $ok = $fs->copyFile(
      $modified['sourcedir'].'/'.$fileName, $fileName
    );
    $status = $ok? 'ok' : 'failed';
    $this->logger->log($status.' copy '.$fileName);

    $this->result[$status]++;

    if ($ok) {
      $hashes[$fileName] = $this->hash($modified['sourcedir'].'/'.$fileName);
    }
  }
}

protected function hash($path, $withMd5 = true)
{
  return [filemtime($path), $withMd5? md5_file($path) : ''];
}

function remoteDelete($fs, $deleted, &$hashes)
{
  foreach ($deleted['files'] as $fileName) {
    $ok = $fs->deleteFile($fileName);
    $status = $ok? 'ok' : 'failed';
    $this->logger->log($status.' delete '.$fileName);

    $this->result[$status]++;

    if ($ok) {
      unset($hashes[$fileName]);
    }
  }
}

protected function prepareData($config, $data)
{
  $modified = array();
  $deleted = array();
  $unwatch = array();

  foreach ((array)$data as $row) {
    list($fileName, $status) = explode('*', $row['FILE']);
    switch ($status) {
      case 'deleted':
        $deleted[] = $fileName;
        break;

      case 'created':
      case 'modified':
        $modified[] = $fileName;
        break;
    
      case 'unwatch':
        $unwatch[] = $fileName;
        break;
      
      default:
        $this->logger->log(paramstr("Unknown status '{0}' of file '{1}'", [$status, $fileName]));
        break;
    }
  }

  return array(
    array(
      'sourcedir' => $config['local'],
      'files' => $modified,
    ),
    array(
      'sourcedir' => $config['local'],
      'files' => $deleted,
    ),
    array(
      'sourcedir' => $config['local'],
      'files' => $unwatch,
    ),
  );
}

protected function getDiff($sourceDir, $savedHashes, $hashes)
{  
  $diffArray = array();
  foreach ($hashes as $fileName => $hash) {
    $savedHash = array_get($savedHashes, $fileName, null);
    unset($savedHashes[$fileName]);

    if (is_array($savedHash)) {
      if ($savedHash[0] == $hash[0]) continue;
      $hash = $this->hash($sourceDir.'/'.$fileName);
      if ($savedHash[1] == $hash[1]) continue;
    }
    else {
      $hash = $this->hash($sourceDir.'/'.$fileName);
      if ($savedHash == $hash[1]) continue;
    }

    $row = array();
    $row['FILENAME'] = $fileName;
    $row['STATUS'] = $savedHash? 'modified' : 'created';
    $diffArray[] = $row;
  }

  foreach ($savedHashes as $fileName => $hash) {
    $row = array();
    $row['FILENAME'] = $fileName;
    $row['STATUS'] = file_exists($sourceDir.'/'.$fileName)? 'unwatch' : 'deleted';
    $diffArray[] = $row; 
  }

  return $diffArray;
}

protected function loadHashFile($task)
{
  $hashFile = './data/'.$task.'.md5';
  return file_exists($hashFile)? json_decode(file_get_contents($hashFile), true) : array();
}

protected function getConfig($task)
{
  $options = include('config/'.$task.'.php');

  $include = array_get($options, 'include', []);
  $exclude = array_get($options, 'exclude', []);
  $dir = rtrim(str_replace("\\", "/", $options['local']), '/');

  $pathFunc = function($s) use($dir) { return str_replace('~', $dir, $s); };

  $options['include'] = array_map($pathFunc, $include);
  $options['exclude'] = array_map($pathFunc, $exclude);

  return $options;
}

protected function saveHashFile($task, $hashes)
{
  file_put_contents('./data/'.$task.'.md5', json_encode($hashes));  
}

protected function createHashArray($files, $withMd5 = false)
{
  $hashes = array();
  $sourceDir = $files['sourcedir'];

  foreach ($files['files'] as $fileName) {
    // if (!file_exists($sourceDir.'/'.$fileName)) {
    //   $app->message("File '$fileName' not found.", 'warning');
    // }
    $hashes[$fileName] = $this->hash($sourceDir.'/'.$fileName, $withMd5);
  }

  return $hashes;
}

protected function getTasks()
{
  $tasks = array();
  foreach(glob('./config/*') as $fileName) {
    if (is_dir($fileName)) {
      $tasks[]['DIR'] = Str::extractPath($fileName, "%f");
    }
    elseif(Str::extractPath($fileName, "%e") == 'php') {
      $tasks[]['TASK'] = Str::extractPath($fileName, "%f");
    }
  }

  return $tasks;
}

/**
 * Return diff html content
 * @param string $file
 * @param string $repository
 * @return string
 * @throws Exception
 */
public function diffAction($file, $repository)
{  
  $config = include './config/' . $repository . '.php';

  $tofileName = $config['local'] . '/' . $file;
  $to_file = file_exists($tofileName)? file_get_contents($tofileName) : '';

  $fs = new FileSync;
  $fs->connect($config['remote']);
  $from_file = $fs->getFile($file);

  if (!empty($config['charset'])) {
    $from_file = iconv($config['charset'], 'utf-8', $from_file);
    $to_file = iconv($config['charset'], 'utf-8', $to_file);

  }

  return '<h2 style="padding-left:1em;position: sticky;top: 0;background-color:white;width:auto">' . $file . '</h2><hr>' . Diff::toTable(Diff::compare($from_file, $to_file));
}

}

?>