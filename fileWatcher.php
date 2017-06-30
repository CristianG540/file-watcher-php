#!/usr/local/bin/php
<?php
require __DIR__.'/vendor/autoload.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Esta funcion se encarga de abrirme una conexion nueva cada vez que la cierro
 * con "ORM::set_db(null)"
 */
function getNewConnection() {
    ORM::configure([
        'connection_string' => 'mysql:host=localhost;dbname=prueba_mr_1',//'mysql:host=localhost;dbname=i3620810_ps2',
        'username' => 'webmaster_mr',//'i3620810_ps2',
        'password' => 'Webmaster2017#@'//'X^XnEwiJq~C8vbi1b*[16^*3'
    ]);
}

// create a log channel
$logger = new Logger('SoapSapConnect');
$logger->pushHandler(new StreamHandler(__DIR__.'/logs/info.log', Logger::DEBUG));

$logger->info('Inicio Script');

$loop = React\EventLoop\Factory::create();
$inotify = new MKraemer\ReactInotify\Inotify($loop);

$inotify->add('observados/', IN_CLOSE_WRITE | IN_CREATE | IN_DELETE);
//$inotify->add('/var/log/', IN_CLOSE_WRITE | IN_CREATE | IN_DELETE);

$inotify->on(IN_CLOSE_WRITE, function ($path) use($logger) {
    $logger->info('File closed after writing: '.$path.PHP_EOL);
});

$inotify->on(IN_CREATE, function ($path) use($logger) {
    $logger->info('***********************************************************************************');
    // Abro una conexion nueva
    getNewConnection();
    //ORM::get_db()->setAttribute(PDO::ATTR_PERSISTENT, FALSE);
    //ORM::get_db()->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);
    // Start a transaction
    ORM::get_db()->beginTransaction();
    try {
        /**
         * creo nuevos datos en la bd
         */
        $createData = ORM::for_table('test1')->create();
        $createData->lorem1 = "Lorem Ipsum";
        $createData->lorem2 = (int)19;
        $createData->save();

        /**
         * leo los datos de la bd
         */
        $data = ORM::for_table('test1')->findArray();
        $logger->info('los datos en BD son', $data);
        // Commit a transaction
        ORM::get_db()->commit();
    } catch (\PDOException $e) {
        $logger->error($e->getMessage());
        // Roll back a transaction
        ORM::get_db()->rollBack();
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        // Roll back a transaction
        ORM::get_db()->rollBack();
    } finally {
        /**
         * El peor cancer del mundo, como este script no se deja de ejecutar
         * la base de datos me botaba y me decia "MySQL server has gone away"
         * lo que hago con esto setear el objeto PDO de idiorm y elimanarlo
         * de esta manera PDO deja de existir y me cierra la conexion
         */
        ORM::set_db(null);
    }

    $logger->info('File created: '.$path.PHP_EOL);
    $logger->info('***********************************************************************************');
});

$inotify->on(IN_DELETE, function ($path) use($logger) {
    $logger->info('File deleted: '.$path.PHP_EOL);
});

$loop->run();
