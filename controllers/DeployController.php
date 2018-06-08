<?php
include 'libs/FileSync.php';
include 'libs/TextLogger.php';

class DeployController extends App_Controller {

/*var TextLogger */
protected $logger;

/* var array Upload results. */
protected $result;

function indexAction() {
  $grid = new Grid('tpl/tasks.tpl');
  $grid->setArray($this->getTasks());
  return $grid;
}

function historyAction($task)
{
  return '<pre>'.@file_get_contents('data/log/'.$task.'.log').'</pre>';
}

/** Run if no deploy has been done yet - add files to monitoring. */
function initAction($task)
{
  $this->logger = new TextLogger($task);

  $task = sanitize($task, 'file-id');

  $config = include('config/'.$task.'.php');
  $fs = new FileSync;
  $files = $fs->getList($config['local'], $config);  
  $hashes = $this->createHashArray($files);

  if ($_POST['save']) {
    file_put_contents('data/'.$task.'.md5', json_encode($hashes));
    $this->app->message('Soubory byly přidány.');
    $this->logger->log('Init '.now()." Úloha inicializována.\n");
    $this->app->redirect('deploy/preview/task:'.$task);
  }
  elseif($_POST['no_save']) {
    file_put_contents('data/'.$task.'.md5', json_encode(array()));
    $this->logger->log('Init '.now()." Úloha inicializována.\n");
    $this->app->redirect('deploy/preview/task:'.$task);    
  }

  $grid = new Grid('tpl/preview_init.tpl');

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

  $config = include('config/'.$task.'.php');
  $fs = new FileSync;
  $files = $fs->getList($config['local'], $config);

  $hashes = $this->createHashArray($files);
  $savedHashes = $this->loadHashFile($task);

  $grid = new GridForm('tpl/preview.tpl');
  $data = $this->getDiff($config['local'], $savedHashes, $hashes);
  $grid->setArray($data);
  $grid->values['TASK'] = $task;
  $grid->values['TOTAL'] = count($data);

  $datasource = parse_url($config['remote']);
  $grid->values['HOST'] = $datasource['host'];
  $grid->values['REMOTEDIR'] = $datasource['path'];

  if (!count($data)) {
    $grid->form->_commit->noedit = 1;    
  }

  return $grid;
}

/** Copy or delete selected files to remote server. */
function commitAction($task)
{
  $this->logger = new TextLogger($task);

  $data = $_POST['data'];
  $task = sanitize($task, 'file-id');
  $config = include('config/'.$task.'.php');
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
  return nl2br($this->logger->output);
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
      $hashes[$fileName] = md5_file($modified['sourcedir'].'/'.$fileName);
    }
  }
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
    list($fileName, $status) = explode(' ', $row['FILE']);
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
        $this->logger->log(paramstr("Unknown status '{0}' of file '{1}'", $status, $fileName));
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
    $savedHash = $savedHashes[$fileName];
    unset($savedHashes[$fileName]);

    if ($savedHash == $hash) continue;
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
  $hashFile = 'data/'.$task.'.md5';
  return file_exists($hashFile)? json_decode(file_get_contents($hashFile), true) : array();
}

protected function saveHashFile($task, $hashes)
{
  file_put_contents('data/'.$task.'.md5', json_encode($hashes));  
}

protected function createHashArray($files)
{
  $hashes = array();
  $sourceDir = $files['sourcedir'];

  foreach ($files['files'] as $fileName) {
    if (!file_exists($sourceDir.'/'.$fileName)) {
      $app->message("File '$fileName' not found.", 'warning');
    }
    $hashes[$fileName] = md5_file($sourceDir.'/'.$fileName);
  }

  return $hashes;
}

protected function getTasks()
{
  $tasks = array();
  foreach(glob('config/*') as $fileName) {
    $tasks[]['TASK'] = extractPath($fileName, "%f");
  }

  return $tasks;
}

}

?>